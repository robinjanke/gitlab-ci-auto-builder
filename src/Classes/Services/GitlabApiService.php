<?php

namespace RobinJanke\GitlabCiAutoBuilder\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RobinJanke\GitlabCiAutoBuilder\Logger\Logger;
use RobinJanke\GitlabCiAutoBuilder\Logger\LogLevel;
use RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Group;
use RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Project;

class GitlabApiService
{

    protected $gitlabToken = "";
    protected $gitlabUrl = "";
    protected $gitlabApiUrl = "";
    protected $dockerRegistryUrl = "";
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
     * @param array $config
     */
    public function __construct($config)
    {

        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        $this->requestClient = new Client(['http_errors' => false]);
        $this->authParams = ["PRIVATE-TOKEN" => $this->gitlabToken];
        $this->logger = Logger::instance();
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function getAllProjects()
    {


        $request = $this->requestClient->request(
            'GET',
            $this->gitlabApiUrl . "groups/" . $this->baseGroupIdentifier . "/subgroups",
            [
                'headers' => $this->authParams,
            ]
        );

        $groups = [];

        if ($request->getStatusCode() != 200) {
            $this->logger->log(LogLevel::CRITICAL, "Cannot load projects of main group with identifier #" . $this->baseGroupIdentifier);
            $this->logger->log(LogLevel::DEBUG, "Message from gitlab: " . $request->getBody()->getContents());
            return [];
        }

        $groupsRaw = json_decode($request->getBody()->getContents());
        foreach ($groupsRaw as $groupRaw) {
            $group = new Group();
            $group->setName($groupRaw->name);
            $group->setId($groupRaw->id);
            $this->logger->log(LogLevel::DEBUG, "Added group " . $group->getName());
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
            $this->logger->log(LogLevel::DEBUG, "Added subgroup " . $group->getName());
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
                $dockerPath = str_replace($this->gitlabUrl, $this->dockerRegistryUrl, $path);
                $project->setDockerPath($dockerPath);
                $project->setName($projectRaw->name);
                $project->setFullPath($fullpath);
                $project->setId($projectRaw->id);
                $this->projects[] = $project;
            }
        } else {
            $this->logger->log(LogLevel::ALERT, "Failed loading projects of group " . $group->getName());
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
                if (strpos($from, $this->dockerRegistryUrl) !== false) {
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

        $this->logger->log(LogLevel::INFO, "Processing pipeline for project: " . $project->getFullPath());

        /** @var Project $project */
        /** @var string $branch */
        if (empty($this->branchesToRunPipeline)) {
            $successCount = $this->loopBranchesForPipelines($project->getBranches(), $project);
            $branchesCount = count($project->getBranches());
        } else {
            $successCount = $this->loopBranchesForPipelines($this->branchesToRunPipeline, $project);
            $branchesCount = count($this->branchesToRunPipeline);
        }


        $this->logger->log(LogLevel::INFO, "Successfully: " . $successCount . "/" . $branchesCount);

        if ($project->getChildProjects() != null) {
            if ($successCount == $branchesCount) {
                $this->logger->log(LogLevel::INFO, "All pipelines was successfully. Now processing child projects of " . $project->getFullPath() . " ...");
                foreach ($project->getChildProjects() as $childProject) {
                    $this->runPipeline($childProject);
                }
            } else {
                if ($this->triggerChildrenIfPipelineFailed == true) {
                    $this->logger->log(LogLevel::INFO, "Not all pipelines where successfully. But triggerChildrenIfPipelineFailed is enabled so now processing child projects of " . $project->getFullPath() . " ...");
                } else {
                    $this->logger->log(LogLevel::WARNING, "Not all pipelines where successfully. triggerChildrenIfPipelineFailed is disabled so now stopping processing child projects of " . $project->getFullPath() . " ...");
                }

            }
        }

    }

    /**
     * @param array $branches
     * @param Project $project
     * @return int
     * @throws \GuzzleHttp\Exception\GuzzleException
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
                $this->logger->log(LogLevel::WARNING, "Branch " . $branch . " for project " . $project->getFullPath() . " not exist!");
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
     * @return string
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

        $this->logger->log(LogLevel::INFO, "Waiting for the pipeline #" . $pipelineIdentifier . " to complete...");

        $runningTime = 0;
        $running = true;
        while ($running == true) {

            $runningTime += $this->checkTime;
            if ($runningTime >= $this->maxWaitTimeForPipeline) {
                $this->logger->log(LogLevel::WARNING, "The pipeline needed more than" . $this->maxWaitTimeForPipeline . " seconds to complete. Skipping...");
                $status = "Running to long";
                break;
            }

            sleep($this->checkTime);

            $request = $this->requestClient->request(
                'GET',
                $this->gitlabApiUrl . "projects/" . $project->getId() . "/pipelines/" . $pipelineIdentifier,
                [
                    'headers' => $this->authParams,
                ]
            );

            if ($request->getStatusCode() != 200) {
                $this->logger->log(LogLevel::CRITICAL, "Cant acccess pipelines of project " . $project->getFullPath());
                $status = "HTTP Error";
            }

            $response = json_decode($request->getBody()->getContents());
            $status = $response->status;

            if ($status != "running") {
                $running = false;
            }

        }

        if ($status == "success") {
            $this->logger->log(LogLevel::INFO, "Pipeline #" . $pipelineIdentifier . " done in " . ($runningTime - $this->checkTime) . " - " . $runningTime . " seconds!");
        } else {
            $this->logger->log(LogLevel::INFO, "Pipeline for branch " . $branch . " is done in " . ($runningTime - $this->checkTime) . " - " . $runningTime . " seconds but has the status " . $status . "!");
        }

        return $status;

    }


}