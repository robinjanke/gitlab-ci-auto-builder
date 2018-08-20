<?php

namespace RobinJanke\GitlabCiAutoBuilder\Models\Gitlab;

class Project
{

    public $name = "";
    public $id = "";
    public $dockerFrom = "";
    public $dockerUsages = 0;
    public $dockerPath = "";
    public $branches = [];
    public $childProjects = [];
    public $external = true;
    public $fullPath = "";

    /**
     * @return array
     */
    public function getChildProjects(): array
    {
        return $this->childProjects;
    }

    public function addChildProject(Project $childProject)
    {
        $this->childProjects[] = $childProject;
    }

    /**
     * @param array $childProjects
     */
    public function setChildProjects(array $childProjects)
    {
        $this->childProjects = $childProjects;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getDockerFrom(): string
    {
        return $this->dockerFrom;
    }

    /**
     * @param string $dockerFrom
     */
    public function setDockerFrom(string $dockerFrom)
    {
        $this->dockerFrom = $dockerFrom;
    }

    /**
     * @return int
     */
    public function getDockerUsages(): int
    {
        return $this->dockerUsages;
    }

    /**
     * @param int $dockerUsages
     */
    public function setDockerUsages(int $dockerUsages)
    {
        $this->dockerUsages = $dockerUsages;
    }

    /**
     * @return string
     */
    public function getDockerPath(): string
    {
        return $this->dockerPath;
    }

    /**
     * @param string $dockerPath
     */
    public function setDockerPath(string $dockerPath)
    {
        $this->dockerPath = $dockerPath;
    }

    /**
     * @return array
     */
    public function getBranches(): array
    {
        return $this->branches;
    }

    /**
     * @param array $branches
     */
    public function setBranches(array $branches)
    {
        $this->branches = $branches;
    }

    /**
     * @return bool
     */
    public function isExternal(): bool
    {
        return $this->external;
    }

    /**
     * @param bool $external
     */
    public function setExternal(bool $external)
    {
        $this->external = $external;
    }

    /**
     * @return string
     */
    public function getFullPath(): string
    {
        return $this->fullPath;
    }

    /**
     * @param string $fullPath
     */
    public function setFullPath(string $fullPath)
    {
        $this->fullPath = $fullPath;
    }

}