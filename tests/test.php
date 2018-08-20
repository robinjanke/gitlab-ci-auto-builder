<?php

namespace testProgram;
require_once __DIR__ . '/../vendor/autoload.php';
use RobinJanke\GitlabCiAutoBuilder\Builder;

$gitlabBuilder = new Builder(
    "https://gitlab.com/",
    "YOURAPIKEYHERE",
    "https://gitlab.com/api/v4/",
    "registry.gitlab.com/",
    "YOURBASEGROUPIDHERE",
    ['master', 'release'],
    ['master', 'release'],
    "/Dockerfile",
    true,
    600,
    true,
    1,
    true,
    "Y-m-d H:i:s",
    10
);

/** @var Builder $gitlabBuilder */
$gitlabBuilder->buildAll();