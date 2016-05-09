<?php

namespace Eliberty\ApiBundle\Context;

use Dunglas\ApiBundle\Api\ResourceInterface;

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
     * @var array
     */
    private $properties;

    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * @var string
     */
    private $strategy;

    /**
     * GroupsContext constructor.
     *
     * @param                   $groupName
     * @param ResourceInterface $resource
     * @param array|null        $properties
     * @param string            $strategy
     */
    public function __construct(
        $groupName,
        ResourceInterface $resource,
        array $properties = null,
        $strategy = self::STRATEGY_REMOVING
    ) {
        $this->properties = $properties;
        $this->resource   = $resource;
        $this->groupName  = $groupName;
        $this->strategy   = $strategy;
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param ResourceInterface $resource
     *
     * @return $this
     */
    public function setResouce($resource)
    {
        $this->resource = $resource;

        return $this;
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
}