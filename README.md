## Automatically trigger gitlab pipelines with dependency considerations

### Installation

1.  Run `composer install`
2.  Create gitlab account for pipelines with required permissions (optional)
3.  Get gitlab api token (e.g. https://gitlab.com/profile/applications)
4.  Create a base group group for all subprojects and get the id under 
    Settings->General->Group ID
    
### Usage
    
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
        5
    );
    
    $gitlabBuilder->buildAll();