<?php

namespace RobinJanke\GitlabCiAutoBuilder;

#use gitlab_ci_builder\Services\GitLabService;

use GuzzleHttp\Exception\GuzzleException;
use RobinJanke\GitlabCiAutoBuilder\Models\Log\Logger;
use RobinJanke\GitlabCiAutoBuilder\Services\GitlabApiService;
use RobinJanke\GitlabCiAutoBuilder\Services\SortService;


class Builder
{

    /**
     * @var GitlabApiService
     */
    protected $gitlabApiService;

    /**
     * @var SortService
     */
    protected $sortService;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Builder constructor.
     * @param string $gitlabUrl
     * @param string $gitlabToken
     * @param string $gitlabApiUrl
     * @param string $dockerRegistryUrl
     * @param string $baseGroupIdentifier
     * @param array $branchesToRunPipeline
     * @param array $branchesToCheckForDockerfile
     * @param string $pathToDockerfile
     * @param bool $triggerChildrenIfPipelineFailed
     * @param int $maxWaitTimeForPipeline
     * @param bool $handleNotExistingBranchesAsSuccessfully
     * @param int $logLevel
     * @param bool $logDateTimeToStdout
     * @param string $dateFormat
     * @param int $checkTime
     */
    public function __construct(
        $gitlabUrl,
        $gitlabToken,
        $gitlabApiUrl,
        $dockerRegistryUrl,
        $baseGroupIdentifier,
        $branchesToRunPipeline = [],
        $branchesToCheckForDockerfile = [],
        $pathToDockerfile = "",
        $triggerChildrenIfPipelineFailed = true,
        $maxWaitTimeForPipeline = 600,
        $handleNotExistingBranchesAsSuccessfully = true,
        $logLevel = 0,
        $logDateTimeToStdout = true,
        $dateFormat = "Y-m-d H:i:s",
        $checkTime = 10
    )
    {
        $this->gitlabApiService = new GitlabApiService(
            $gitlabToken,
            $gitlabUrl,
            $gitlabApiUrl,
            $dockerRegistryUrl,
            $baseGroupIdentifier,
            $branchesToRunPipeline,
            $branchesToCheckForDockerfile,
            $pathToDockerfile,
            $triggerChildrenIfPipelineFailed,
            $maxWaitTimeForPipeline,
            $handleNotExistingBranchesAsSuccessfully,
            $checkTime
        );
        $this->sortService = new SortService();

        $this->logger = Logger::instance();
        $this->logger->setDateFormat($dateFormat);
        $this->logger->setLogDateTimeToStdout($logDateTimeToStdout);
        $this->logger->setLogLevel($logLevel);
    }

    /**
     * @throws GuzzleException
     */
    public function buildAll()
    {

        $this->logger->logMessageStdout("Started application", 0);
        $this->logger->logMessageStdout("Loading all projects...", 1);
        $projects = $this->gitlabApiService->getAllProjects();

        /** @var \RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Project $project */
        foreach ($projects as $project) {
            $this->logger->logMessageStdout("Loading branches for project " . $project->getFullPath() . "...", 1);
            $project = $this->gitlabApiService->setBranches($project);

            $this->logger->logMessageStdout("Loading docker details for project " . $project->getFullPath() . "...", 1);
            $project = $this->gitlabApiService->setDockerDetails($project);
        }

        $this->logger->logMessageStdout("Loading child projects...", 1);
        $projects = $this->sortService->setChildProjects($projects);

        $this->logger->logMessageStdout("Removing projects without docker base image from array...", 1);
        $projects = $this->sortService->removeProjectsWithoutDockerFrom($projects);

        $this->logger->logMessageStdout("Removing projects with internal docker base image from array...", 1);
        $projects = $this->sortService->removeNonExternalProjects($projects);

        $this->logger->logMessageStdout("Starting processing pipelines", 0);
        foreach ($projects as $project) {
            $this->gitlabApiService->runPipeline($project);
        }
        $this->logger->logMessageStdout("Application done", 0);
    }


}

