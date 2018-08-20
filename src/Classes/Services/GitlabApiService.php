<?php

namespace RobinJanke\GitlabCiAutoBuilder\Services;

require_once __DIR__ . '/../../../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Group;
use RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Project;
use RobinJanke\GitlabCiAutoBuilder\Models\Log\Logger;

class GitlabApiService
{

    protected $token = "";
    protected $gitlabUrl = "";
    protected $gitlabApiUrl = "";
    protected $registryUrl = "";
    protected $baseGroupIdentifier = "";

    protected $branchesToRunPipeline = [];
    protected $branchesToCheckForDockerfile = [];
    protected $pathToDockerfile = "/Dockerfile";
    protected $triggerChildrenIfPipelineFailed = true;
    protected $maxWaitTimeForPipeline = 0;
    protected $handleNotExistingBranchesAsSuccessfully = true;
    protected $checkTime;

    protected $logger;
    protected $requestClient;

    protected $authParams = [];
    protected $projects = [];


    /**
     * GitlabApiService constructor.
     * @param string $token
     * @param string $gitlabUrl
     * @param string $gitlabApiUrl
     * @param string $registryUrl
     * @param string $baseGroupIdentifier
     * @param array $branchesToRunPipeline
     * @param array $branchesToCheckForDockerfile
     * @param string $pathToDockerfile
     * @param bool $triggerChildrenIfPipelineFailed
     * @param int $maxWaitTimeForPipeline
     * @param bool $handleNotExistingBranchesAsSuccessfully
     * @param int $checkTime
     */
    public function __construct(
        $token,
        $gitlabUrl,
        $gitlabApiUrl,
        $registryUrl,
        $baseGroupIdentifier,
        $branchesToRunPipeline = [],
        $branchesToCheckForDockerfile = [],
        $pathToDockerfile = "",
        $triggerChildrenIfPipelineFailed = true,
        $maxWaitTimeForPipeline = 600,
        $handleNotExistingBranchesAsSuccessfully = true,
        $checkTime = 10
    )
    {
        $this->handleNotExistingBranchesAsSuccessfully = $handleNotExistingBranchesAsSuccessfully;
        $this->baseGroupIdentifier = $baseGroupIdentifier;
        $this->checkTime = $checkTime;
        $this->maxWaitTimeForPipeline = $maxWaitTimeForPipeline;
        $this->triggerChildrenIfPipelineFailed = $triggerChildrenIfPipelineFailed;
        $this->token = $token;
        $this->gitlabUrl = $gitlabUrl;
        $this->gitlabApiUrl = $gitlabApiUrl;
        $this->registryUrl = $registryUrl;
        $this->branchesToRunPipeline = $branchesToRunPipeline;
        $this->branchesToCheckForDockerfile = $branchesToCheckForDockerfile;
        if ($pathToDockerfile != "") {
            $this->pathToDockerfile = $pathToDockerfile;
        }
        $this->requestClient = new Client(['http_errors' => false]);
        $this->authParams = ["PRIVATE-TOKEN" => $token];
        $this->logger = Logger::instance();
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function getAllProjects()
    {

        $res = $this->requestClient->request(
            'GET',
            $this->gitlabApiUrl . "groups/" . $this->baseGroupIdentifier . "/subgroups",
            [
                'headers' => $this->authParams,
            ]
        );

        $groups = [];
        $groupsRaw = json_decode($res->getBody()->getContents());
        foreach ($groupsRaw as $groupRaw) {
            $group = new Group();
            $group->setName($groupRaw->name);
            $group->setId($groupRaw->id);
            $this->logger->logMessageStdout("Added group " . $group->getName(), 2);
            $groups[] = $group;
        }

        /** @var Group $group */
        foreach ($groups as $key => $group) {
            $newGroup = $this->getSubGroups($group);
            $groups[$key] = $newGroup;
        }

        return $this->projects;

    }

    /**
     * @param Group $mainGroup
     * @return Group
     * @throws GuzzleException
     */
    protected function getSubGroups(Group $mainGroup)
    {

        $res = $this->requestClient->request(
            'GET',
            $this->gitlabApiUrl . "groups/" . $mainGroup->getId() . "/subgroups",
            [
                'headers' => $this->authParams,
            ]
        );

        $subGroups = [];
        $groupsRaw = json_decode($res->getBody()->getContents());
        foreach ($groupsRaw as $groupRaw) {
            $group = new Group();
            $group->setName($groupRaw->name);
            $group->setId($groupRaw->id);
            $this->logger->logMessageStdout("Added subgroup " . $group->getName(), 2);
            $subGroups[] = $group;
        }

        $mainGroup->setSubGroups($subGroups);

        /** @var Group $subGroup */
        foreach ($subGroups as $subGroup) {
            $this->getSubGroups($subGroup);
        }
        $this->getProjects($mainGroup);
        return $mainGroup;

    }

    /**
     * @param Group $group
     * @throws GuzzleException
     */
    protected function getProjects(Group $group)
    {

        $res = $this->requestClient->request(
            'GET',
            $this->gitlabApiUrl . "groups/" . $group->getId() . "/projects",
            [
                'headers' => $this->authParams,
            ]
        );

        if ($res->getStatusCode() == 200) {
            $projectsRaw = json_decode($res->getBody()->getContents());
            foreach ($projectsRaw as $projectRaw) {
                $project = new Project();
                $path = $projectRaw->web_url;
                $fullpath = str_replace($this->gitlabUrl, "", $path);
                $dockerPath = str_replace($this->gitlabUrl, $this->registryUrl, $path);
                $project->setDockerPath($dockerPath);
                $project->setName($projectRaw->name);
                $project->setFullPath($fullpath);
                $project->setId($projectRaw->id);
                $this->projects[] = $project;
            }
        } else {
            $this->logger->logMessageStdout( "Failed loading projects of group " . $group->getName(), 1);
        }
    }


    /**
     * @param array $branches
     * @param Project $project
     * @return Project
     * @throws GuzzleException
     */
    protected function getDockerFrom(array $branches, Project $project)
    {
        foreach ($branches as $branch) {

            $res = $this->requestClient->request(
                'GET',
                $this->gitlabApiUrl . "projects/" . $project->getId() . "/repository/files" . $this->pathToDockerfile . "/raw?ref=" . $branch,
                [
                    'headers' => $this->authParams,
                ]
            );

            if ($res->getStatusCode() == 200) {
                $content = $res->getBody()->getContents();

                if (substr($content, 0, 4) == "FROM") {
                    $lines = explode("\n", $content);
                    $from = str_replace("FROM ", "", $lines[0]);

                    $from = explode(":", $from);
                    $from = $from[0];

                }
            } else {
                $from = "";
            }

            /** @var string $from */
            if ($from != "") {
                if (strpos($from, $this->registryUrl) !== false) {
                    $project->setExternal(false);
                }
                $project->setDockerFrom($from);
                break;
            }
        }

        return $project;
    }

    /**
     * @param Project $project
     * @return Project
     * @throws GuzzleException
     */
    public function setDockerDetails(Project $project)
    {
        // Docker base image (from)
        if (empty($this->branchesToCheckForDockerfile)) {
            $project = $this->getDockerFrom($project->getBranches(), $project);
        } else {
            $project = $this->getDockerFrom($this->branchesToCheckForDockerfile, $project);
        }

        return $project;
    }

    /**
     * @param Project $project
     * @return Project
     * @throws GuzzleException
     */
    public
    function setBranches(Project $project)
    {

        $res = $this->requestClient->request(
            'GET',
            $this->gitlabApiUrl . "projects/" . $project->getId() . "/repository/branches",
            [
                'headers' => $this->authParams,
            ]
        );

        if ($res->getStatusCode() == 200) {
            $branchesRaw = json_decode($res->getBody()->getContents());

            $branches = [];
            foreach ($branchesRaw as $branch) {
                $branches[] = $branch->name;
            }
            $project->setBranches($branches);
        }

        return $project;

    }

    /**
     * @param Project $project
     * @throws GuzzleException
     */
    public
    function runPipeline(Project $project)
    {

        $this->logger->logMessageStdout( "Processing pipeline for project: " . $project->getFullPath(), 1);

        /** @var Project $project */
        /** @var string $branch */
        if (empty($this->branchesToRunPipeline)) {
            $successCount = $this->loopBranchesForPipelines($project->getBranches(), $project);
            $branchesCount = count($project->getBranches());
        } else {
            $successCount = $this->loopBranchesForPipelines($this->branchesToRunPipeline, $project);
            $branchesCount = count($this->branchesToRunPipeline);
        }


        $this->logger->logMessageStdout( "Successfully: " . $successCount . "/" . $branchesCount, 1);

        if ($project->getChildProjects() != null) {
            if ($successCount == $branchesCount) {
                $this->logger->logMessageStdout( "All pipelines was successfully. Now processing child projects of " . $project->getFullPath() . " ...", 1);
                foreach ($project->getChildProjects() as $childProject) {
                    $this->runPipeline($childProject);
                }
            } else {
                if ($this->triggerChildrenIfPipelineFailed == true) {
                    $this->logger->logMessageStdout( "Not all pipelines where successfully. But triggerChildrenIfPipelineFailed is enabled so now processing child projects of " . $project->getFullPath() . " ...", 1);
                } else {
                    $this->logger->logMessageStdout( "Not all pipelines where successfully. triggerChildrenIfPipelineFailed is disabled so now stopping processing child projects of " . $project->getFullPath() . " ...", 1);
                }

            }
        }

    }

    /**
     * @param array $branches
     * @param Project $project
     * @param int $checkTime
     * @return int
     * @throws GuzzleException
     */
    protected function loopBranchesForPipelines(array $branches, Project $project)
    {
        $successCount = 0;

        foreach ($branches as $branch) {
            if (in_array($branch, $project->getBranches())) {
                $status = $this->triggerPipelineByBranch($branch, $project);
                if ($status == "success") {
                    $successCount += 1;
                }
            } else {
                $this->logger->logMessageStdout( "Branch " . $branch . " for project " . $project->getFullPath() . " not exists!", 1);
                if ($this->handleNotExistingBranchesAsSuccessfully == true) {
                    $successCount += 1;
                }
            }
        }

        return $successCount;
    }

    /**
     * @param string $branch
     * @param Project $project
     * @return str
     * ing
     * @throws GuzzleException
     */
    protected function triggerPipelineByBranch(string $branch, Project $project)
    {

        $res = $this->requestClient->request(
            'POST',
            $this->gitlabApiUrl . "projects/" . $project->getId() . "/pipeline?ref=" . $branch,
            [
                'headers' => $this->authParams,
            ]
        );

        $response = json_decode($res->getBody()->getContents());
        $pipelineIdentifier = $response->id;

        $this->logger->logMessageStdout( "Waiting for the pipeline #" . $pipelineIdentifier . " to complete...", 1);

        $runningTime = 0;
        $running = true;
        while ($running == true) {

            $runningTime += $this->checkTime;
            if ($runningTime >= $this->maxWaitTimeForPipeline) {
                $this->logger->logMessageStdout( "The pipeline needed more than" . $this->maxWaitTimeForPipeline . " seconds to complete. Skipping...", 1);
                $status = "Running to long";
                break;
            }

            sleep($this->checkTime);

            $res = $this->requestClient->request(
                'GET',
                $this->gitlabApiUrl . "projects/" . $project->getId() . "/pipelines/" . $pipelineIdentifier,
                [
                    'headers' => $this->authParams,
                ]
            );

            $response = json_decode($res->getBody()->getContents());
            $status = $response->status;

            if ($status != "running") {
                $running = false;
            }

        }

        if ($status == "success") {
            $this->logger->logMessageStdout( "Pipeline #" . $pipelineIdentifier . " done in " . ($runningTime - $this->checkTime) . " - " . $runningTime . " seconds!", 1);
        } else {
            $this->logger->logMessageStdout( "Pipeline for branch " . $branch . " is done in " . ($runningTime - $this->checkTime) . " - " . $runningTime . " seconds but has the status " . $status . "!", 1);
        }

        return $status;

    }


}