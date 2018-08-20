<?php

namespace RobinJanke\GitlabCiAutoBuilder\Services;

use McDev\GitlabCiBuilder\Models\Gitlab\Project;

class SortService
{

    public function setChildProjects(array $projects)
    {
        /** @var \McDev\GitlabCiBuilder\Models\Gitlab\Project $project */
        foreach ($projects as $key => $project) {
            /** @var \McDev\GitlabCiBuilder\Models\Gitlab\Project $projectInside */
            foreach ($projects as $childProject) {
                if ($project->getDockerPath() == $childProject->getDockerFrom()) {
                    $project->addChildProject($childProject);
                }
            }

            $projects[$key] = $project;
        }

        return $projects;
    }

    public function removeNonExternalProjects(array $projects)
    {

        /** @var Project $project */
        foreach ($projects as $key => $project) {
            if ($project->isExternal() == false) {
                unset($projects[$key]);
            }
        }

        return $projects;

    }

    public function removeProjectsWithoutDockerFrom(array $projects)
    {
        /** @var Project $project */
        foreach ($projects as $key => $project) {
            if ($project->getDockerFrom() == "") {
                unset($projects[$key]);
            }

        }

        return $projects;
    }
}