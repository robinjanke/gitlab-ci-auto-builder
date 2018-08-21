<?php

namespace RobinJanke\GitlabCiAutoBuilder\Services;

use RobinJanke\GitlabCiAutoBuilder\Models\Gitlab\Project;

class SortService
{

    /**
     * @param array $projects
     * @return array
     */
    public function setChildProjects(array $projects)
    {
        /** @var Project $project */
        foreach ($projects as $key => $project) {
            /** @var Project $childProject */
            foreach ($projects as $childProject) {
                if ($project->getDockerPath() == $childProject->getDockerFrom()) {
                    $project->addChildProject($childProject);
                }
            }

            $projects[$key] = $project;
        }

        return $projects;
    }

    /**
     * @param array $projects
     * @return array
     */
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

    /**
     * @param array $projects
     * @return array
     */
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