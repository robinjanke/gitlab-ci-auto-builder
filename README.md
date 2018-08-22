## Automatically trigger gitlab pipelines with dependency considerations

### Overview

This composer package can be used to automatically launch gitlab pipelines in combination with docker. If there is a dockerfile in one of the projects then the pipelines are started accordingly. This depends on the Docker dependencies and the selected branches.
There should be a main group in which all projects and subgroups exist.

### Installation

1.  Run `composer require robinjanke/gitlab-ci-auto-builder dev-master`
2.  Create gitlab account for pipelines with required permissions (optional)
3.  Get gitlab api token (e.g. https://gitlab.com/profile/applications)
4.  Create a base group group for all subprojects and get the id under 
    Settings->General->Group ID
    
### Usage example
    
    namespace testProgram;
    require_once __DIR__ . '/vendor/autoload.php';
    use RobinJanke\GitlabCiAutoBuilder\Builder; 
    
    $gitlabBuilder = new Builder([
     'gitlabUrl' => 'https://gitlab.com/',
     'gitlabToken' => 'XXX',
     'gitlabApiUrl' => 'https://gitlab.com/api/v4/',
     'dockerRegistryUrl' => 'registry.gitlab.com/',
     'baseGroupIdentifier' => 'XXX',
     'branchesToRunPipeline' => ['release', 'master'],
     'branchesToCheckForDockerfile' => ['release', 'master', 'beta', 'dev'],
     'pathToDockerfile' => '/Dockerfile',
     'triggerChildrenIfPipelineFailed' => true,
     'maxWaitTimeForPipeline' => 600,
     'handleNotExistingBranchesAsSuccessfully' => true,
     'logLevel' => 7,
     'dateFormat' => 'Y-m-d H:i:s',
     'checkTime' => 10
    ]);
    
    $gitlabBuilder->buildAll();