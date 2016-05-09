<?php

namespace Eliberty\ApiBundle\Context;

use Dunglas\ApiBundle\Api\Resource;

/**
 * Interface GroupsContextInterface
 *
 * @package Eliberty\ApiBundle\Context
 */
interface GroupsContextInterface
{
    /**
     * @return ResourceInterface
     */
    public function getResource();

    /**
     * @param ResourceInterface $resource
     *
     * @return $this
     */
    public function setResouce($resource);

    /**
     * @return array
     */
    public function getProperties();

    /**
     * @param array $properties
     *
     * @return $this
     */
    public function setProperties($properties);

    /**
     * @return string
     */
    public function getGroupName();

    /**
     * @param string $groupName
     *
     * @return $this
     */
    public function setGroupName($groupName);

    /**
     * @return string
     */
    public function getStrategy();

    /**
     * @param string $strategy
     *
     * @return $this
     */
    public function setStrategy($strategy);
}