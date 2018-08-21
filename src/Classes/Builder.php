<?php

namespace RobinJanke\GitlabCiAutoBuilder;

#use gitlab_ci_builder\Services\GitLabService;

use GuzzleHttp\Exception\GuzzleException;
use RobinJanke\GitlabCiAutoBuilder\Logger\Logger;
use RobinJanke\GitlabCiAutoBuilder\Logger\LogLevel;
use RobinJanke\GitlabCiAutoBuilder\Services\GitlabApiService;
use RobinJanke\GitlabCiAutoBuilder\Services\SortService;


class Builder
{

    // Default Config
    protected $config = [
        'gitlabUrl' => '',
        'gitlabToken' => '',
        'gitlabApiUrl' => '',
        'dockerRegistryUrl' => '',
        'baseGroupIdentifier' => '',

        'branchesToRunPipeline' => [],
        'branchesToCheckForDockerfile' => [],
        'pathToDockerfile' => '/Dockerfile',
        'triggerChildrenIfPipelineFailed' => true,
        'maxWaitTimeForPipeline' => 600,
        'handleNotExistingBranchesAsSuccessfully' => true,
        'logLevel' => 7,
        'dateFormat' => 'Y-m-d H:i:s',
        'checkTime' => 10
    ];

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
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = array_merge($this->config, $config);

        $this->gitlabApiService = new GitlabApiService(
            $this->config
        );

        $this->logger = Logger::instance();
        $this->logger->setDateFormat($this->config['dateFormat']);
        $this->logger->setLogLevel($this->config['logLevel']);

        $this->sortService = new SortService();
    }

    /**
     * @throws GuzzleException
     */
    public function buildAll()
    {

        $this->logger->log(LogLevel::INFO, "Started application");
        $this->logger->log(LogLevel::INFO, "Loading all projects...");
        $projects = $this->gitlabApiService->getAllProjects();

        /** @var Models\Gitlab\Project $project */
        foreach ($projects as $key => $project) {
            $this->logger->log(LogLevel::DEBUG, "Loading branches for project " . $project->getFullPath() . "...");
            $projects[$key] = $this->gitlabApiService->setBranches($project);

            $this->logger->log(LogLevel::DEBUG, "Loading docker details for project " . $project->getFullPath() . "...");
            $projects[$key] = $this->gitlabApiService->setDockerDetails($project);
        }

        $this->logger->log(LogLevel::DEBUG, "Loading child projects...");
        $projects = $this->sortService->setChildProjects($projects);

        $this->logger->log(LogLevel::DEBUG, "Removing projects without docker base image from array...");
        $projects = $this->sortService->removeProjectsWithoutDockerFrom($projects);

        $this->logger->log(LogLevel::DEBUG, "Removing projects with internal docker base image from array...");
        $projects = $this->sortService->removeNonExternalProjects($projects);

        $this->logger->log(LogLevel::DEBUG, "Starting processing pipelines");
        foreach ($projects as $project) {
            $this->gitlabApiService->runPipeline($project);
        }
        $this->logger->log(LogLevel::INFO, "Application done");
    }


}

