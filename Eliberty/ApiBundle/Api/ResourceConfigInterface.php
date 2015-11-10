<?php

namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\Filter\FilterInterface;
use Dunglas\ApiBundle\Api\Operation\OperationInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;

/**
 * Class ResourceConfigInterface
 * @package Eliberty\ApiBundle\Api
 */
interface ResourceConfigInterface
{
    /**
     * Gets the associated same (display name) for this resource.
     * Possible to have a class or shotname
     * @return []
     */
    public function getAlias();

    /**
     * Get resource parent.
     *
     * @return ResourceInterface
     */
    public function getResourceParent();

    /**
     * Gets the short name (display name) of the resource.
     *
     * @return string
     */
    public function getShortname();


    /**
     * Gets the short name (display name) of the parent resource if is not the same shortname of the parent resource.
     * @return string
     */
    public function getParentName();

    /**
     * Gets the identifier for this resource.
     * @return string
     */
    public function getIdentifier();
}
