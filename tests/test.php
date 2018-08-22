<?php

namespace testProgram;
require_once __DIR__ . '/../vendor/autoload.php';
use RobinJanke\GitlabCiAutoBuilder\Builder;

$gitlabBuilder = new Builder([
    'gitlabUrl' => 'https://gitlab.com/',
    'gitlabToken' => '',
    'gitlabApiUrl' => 'https://gitlab.com/api/v4/',
    'dockerRegistryUrl' => 'registry.gitlab.com/',
    'baseGroupIdentifier' => '',
    'branchesToRunPipeline' => [],
    'branchesToCheckForDockerfile' => [],
    'pathToDockerfile' => '/Dockerfile',
    'triggerChildrenIfPipelineFailed' => true,
    'maxWaitTimeForPipeline' => 600,
    'handleNotExistingBranchesAsSuccessfully' => true,
    'logLevel' => 6,
    'dateFormat' => 'Y-m-d H:i:s',
    'checkTime' => 10
]);

$gitlabBuilder->buildAll();