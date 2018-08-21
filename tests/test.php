<?php

namespace testProgram;
use RobinJanke\GitlabCiAutoBuilder\Builder;

require_once __DIR__ . '/../vendor/autoload.php';

$config = [
    'gitlabUrl' => 'https://gitlab.com/',
    'gitlabToken' => '',
    'gitlabApiUrl' => 'https://gitlab.com/api/v4/',
    'dockerRegistryUrl' => 'registry.gitlab.com/',
    'baseGroupIdentifier' => '',

    'branchesToRunPipeline' => ['release', 'master'],
    'branchesToCheckForDockerfile' => ['release', 'master', 'beta', 'dev'],
    'pathToDockerfile' => '/Dockerfile',
    'triggerChildrenIfPipelineFailed' => true,
    'maxWaitTimeForPipeline' => 600,
    'handleNotExistingBranchesAsSuccessfully' => true,
    'logLevel' => 7,
    'dateFormat' => 'Y-m-d H:i:s',
    'checkTime' => 10
];

$gitlabBuilder = new Builder($config);
$gitlabBuilder->buildAll();