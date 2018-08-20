<?php

namespace RobinJanke\GitlabCiAutoBuilder\Models\Gitlab;

class Group
{

    protected $name = "";
    protected $id = "";
    protected $subGroups = [];
    protected $projects;

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
     * @return array
     */
    public function getSubGroups(): array
    {
        return $this->subGroups;
    }

    /**
     * @param array $subGroups
     */
    public function setSubGroups(array $subGroups)
    {
        $this->subGroups = $subGroups;
    }

    public function addSubGroup(array $subGroup)
    {
        $this->subGroups[] = $subGroup;
    }

}