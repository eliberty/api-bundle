<?php

namespace Eliberty\ApiBundle\Fractal\Serializer;

use Eliberty\ApiBundle\Fractal\Scope;
use League\Fractal\Resource\ResourceAbstract;

/**
 * Interface SerializerInterface
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
interface SerializerInterface
{
    /**
     * @param Scope $scope
     *
     * @return $this
     */
    public function setScope(Scope $scope);
}