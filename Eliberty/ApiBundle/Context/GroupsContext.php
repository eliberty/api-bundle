<?php

namespace Eliberty\ApiBundle\Context;

/**
 * Interface GroupsContextInterface
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContext implements GroupsContextInterface
{
    /**
     * Property of the GroupsContext is removeing to the data
     */
    const STRATEGY_REMOVING = 'removing';

    /**
     * Property of the GroupsContext is return to the data
     */
    const STRATEGY_ADDING = 'adding';

    /**
     * @var string
     */
    protected $groupName;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var array
     */
    private $properties;


    /**
     * @var string
     */
    private $strategy;

    /**
     * GroupsContext constructor.
     *
     * @param $data
     * @param $groupName
     */
    public function __construct($data, $groupName)
    {
        $this->properties = isset($data['properties']) ? array_flip($data['properties']) : [];
        $this->strategy   = isset($data['strategy']) ? $data['strategy'] : self::STRATEGY_ADDING;
        $this->priority   = isset($data['priority']) ? $data['priority'] : 0;
        $this->groupName  = $groupName;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @param string $groupName
     *
     * @return $this
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;

        return $this;
    }

    /**
     * @return string
     */
    public function getStrategy()
    {
        return $this->strategy;
    }

    /**
     * @param string $strategy
     *
     * @return $this
     */
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     *
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }
}