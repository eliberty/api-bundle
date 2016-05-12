<?php

namespace Eliberty\ApiBundle\Context;


/**
 * Interface GroupsContextInterface
 *
 * @package Eliberty\ApiBundle\Context
 */
interface GroupsContextInterface
{
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

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @param int $priority
     *
     * @return $this
     */
    public function setPriority($priority);
}