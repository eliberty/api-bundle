<?php

namespace Eliberty\ApiBundle\Fractal;

use Eliberty\ApiBundle\Fractal\Serializer\DataArraySerializer;
use Dunglas\ApiBundle\Api\ResourceCollection;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use League\Fractal\Manager as BaseFractalManager;
use League\Fractal\Resource\ResourceInterface;
use Eliberty\ApiBundle\Fractal\Scope as ApiScope;
use League\Fractal\Scope;

/**
 * Manager
 *
 * Not a wildly creative name, but the manager is what a Fractal user will interact
 * with the most. The manager has various configurable options, and allows users
 * to create the "root scope" easily.
 */
class Manager extends BaseFractalManager
{

    /**
     * @var ClassMetadataFactory
     */
    private $apiClassMetadataFactory;

    /**
     * @var ContextBuilder
     */
    private $contextBuilder;


    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var ResourceCollection
     */
    private $resourceCollection;

    /**
     * Get Serializer.
     *
     * @return DataArraySerializer
     */
    public function getSerializer()
    {
        if (! $this->serializer) {
            $this->setSerializer(new DataArraySerializer());
        }

        return $this->serializer;
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getApiClassMetadataFactory()
    {
        return $this->apiClassMetadataFactory;
    }

    /**
     * @param ClassMetadataFactory $apiClassMetadataFactory
     *
     * @return $this
     */
    public function setApiClassMetadataFactory($apiClassMetadataFactory)
    {
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;

        return $this;
    }

    /**
     * @return ContextBuilder
     */
    public function getContextBuilder()
    {
        return $this->contextBuilder;
    }

    /**
     * @param ContextBuilder $contextBuilder
     *
     * @return $this
     */
    public function setContextBuilder($contextBuilder)
    {
        $this->contextBuilder = $contextBuilder;

        return $this;
    }

    /**
     * Create Data.
     *
     * Main method to kick this all off. Make a resource then pass it over, and use toArray()
     *
     * @param ResourceInterface $resource
     * @param string            $scopeIdentifier
     * @param Scope             $parentScopeInstance
     *
     * @return Scope
     */
    public function createData(ResourceInterface $resource, $scopeIdentifier = null, Scope $parentScopeInstance = null)
    {
        $scopeInstance = new ApiScope($this, $resource, $scopeIdentifier);

        // Update scope history
        if ($parentScopeInstance !== null) {
            // This will be the new children list of parents (parents parents, plus the parent)
            $scopeArray = $parentScopeInstance->getParentScopes();
            $scopeArray[] = $parentScopeInstance->getScopeIdentifier();

            $scopeInstance->setParentScopes($scopeArray);
            $scopeInstance->setParent($parentScopeInstance);
        }

        return $scopeInstance;
    }

    /**
     * @return RouterInterface
     */
    public function getRouter()
    {
        return $this->router;
    }

    /**
     * @param RouterInterface $router
     *
     * @return $this
     */
    public function setRouter($router)
    {
        $this->router = $router;

        return $this;
    }

    /**
     * @return ResourceCollection
     */
    public function getResourceCollection()
    {
        return $this->resourceCollection;
    }

    /**
     * @param ResourceCollection $resourceCollection
     *
     * @return $this
     */
    public function setResourceCollection($resourceCollection)
    {
        $this->resourceCollection = $resourceCollection;

        return $this;
    }

}
