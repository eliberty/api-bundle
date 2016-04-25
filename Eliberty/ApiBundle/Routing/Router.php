<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Routing;

use Doctrine\Common\Inflector\Inflector;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Eliberty\ApiBundle\Api\Resource;
use Eliberty\ApiBundle\Doctrine\Orm\MappingsFilter;
use Eliberty\ApiBundle\Fractal\Scope;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class Router
 * @package Eliberty\ApiBundle\Routing
 */
class Router implements RouterInterface
{

    /**
     * @var \SplObjectStorage
     */
    private $routeCache;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var Scope
     */
    protected $scope;

    public function __construct(
        RouterInterface $router,
        ResourceCollectionInterface $resourceCollection,
        PropertyAccessorInterface $propertyAccessor
    )
    {
        $this->router             = $router;
        $this->resourceCollection = $resourceCollection;
        $this->propertyAccessor   = $propertyAccessor;
        $this->routeCache         = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function setContext(RequestContext $context)
    {
        $this->router->setContext($context);
    }

    /**
     * {@inheritdoc}
     */
    public function getContext()
    {
        $this->router->getContext();
    }

    /**
     * {@inheritdoc}
     */
    public function getRouteCollection()
    {
        $this->router->getRouteCollection();
    }

    /**
     * @return Scope
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @param Scope $scope
     *
     * @return $this
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /*
     * {@inheritdoc}
     */
    public function match($pathInfo)
    {
        $baseContext = $this->router->getContext();

        $request = Request::create($pathInfo);
        $context = (new RequestContext())->fromRequest($request);
        $context->setPathInfo($pathInfo);

        try {
            $this->router->setContext($context);

            return $this->router->match($request->getPathInfo());
        } finally {
            $this->router->setContext($baseContext);
        }
    }

    /*
     * {@inheritdoc}
     */
    public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH)
    {

        if (is_object($name)) {
            if ($name instanceof ResourceInterface) {
                $embedRouteName = $this->getEmbedRouteName($name, $parameters);
                $name = ($embedRouteName === false) ? $this->getItemRouteName($name, 'collection') : $embedRouteName;
            } else {
                $parameters = $this->getParamsByResource($name);
            }
        }

        if (isset($parameters['id']) && $parameters['id'] === 0 ) {
            return null;
        }

        $baseContext = $this->router->getContext();

        try {
            $this->router->setContext(new RequestContext(
                '',
                'GET',
                $baseContext->getHost(),
                $baseContext->getScheme(),
                $baseContext->getHttpPort(),
                $baseContext->getHttpsPort()
            ));
            try {

                return $this->router->generate($name, $parameters, $referenceType);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }

        } finally {
            $this->router->setContext($baseContext);
        }
    }

    /**
     * return params route
     * @param $name
     * @param string $type
     * @return array
     */
    protected function getParamsByResource(&$name, $type = 'item')
    {
        $parentResource = null;
        $parameters = [];
        $version = $this->getScope()->getDunglasResource()->getVersion();
        if ($resource = $this->resourceCollection->getResourceForEntity($name, $version)) {
            $parameters = $resource->getRouteKeyParams($name);
            if (empty($parameters)) {
                $parameters['id'] = $this->propertyAccessor->getValue($name, 'id');
            }

            if (null !== $resource->getParent() && !isset($parameters[$resource->getParentName()])) {
                $parentResource = $resource->getParent();
                $parentObject   = $this->propertyAccessor->getValue(
                    $name,
                    Inflector::singularize($resource->getParentName())
                );
                $parentParams   = $parentResource->getRouteKeyParams($parentObject);
                $parameters     = array_merge($parentParams, $parameters);
            }


            $name = $this->getItemRouteName($resource, $type);
        }

        return $parameters;
    }

    /**
     * Gets the collection route name for a resource.
     *
     * @param ResourceInterface $resource
     *
     * @param $parameters
     * @return string
     */
    private function getEmbedRouteName(ResourceInterface $resource, &$parameters)
    {
        if ($this->getScope() instanceof Scope) {
            if ($this->getScope()->getParent() instanceof Scope) {
                $parentScope = $this->getScope()->getParent();
                if (!is_null($parentScope->getDunglasResource()->getEmbedOperation())) {
                    $parentDunglasResource = $parentScope->getDunglasResource();
                    $parameters = [];
                    $parameters[$parentDunglasResource->getIdentifier()] = $parentDunglasResource->getIdentifierValue($parentScope->getData());
                    $parameters['embed'] = $this->getScope()->getSingleIdentifier();

                    return $parentScope->getDunglasResource()->getEmbedOperation()->getRouteName();
                }
            }
        }

        return false;
    }


    /**
     * Gets the item route name for a resource.
     *
     * @param ResourceInterface $resource
     *
     * @return string
     */
    private function getItemRouteName(ResourceInterface $resource, $prefix)
    {
        if (!$this->routeCache->contains($resource)) {
            $this->routeCache[$resource] = [];
        }

        $key = $prefix.'RouteName';

        if (isset($this->routeCache[$resource][$key])) {
            return $this->routeCache[$resource][$key];
        }

        $operations = 'item' === $prefix ? $resource->getItemOperations() : $resource->getCollectionOperations();
        foreach ($operations as $operation) {
            $methods = $operation->getRoute()->getMethods();

            if (empty($methods) || in_array('GET', $methods)) {
                $data = $this->routeCache[$resource];
                $data[$key] = $operation->getRouteName();
                $this->routeCache[$resource] = $data;

                return $data[$key];
            }
        }
   }

    /**
     * Initializes the route cache structure for the given resource.
     *
     * @param ResourceInterface $resource
     */
    private function initRouteCache(ResourceInterface $resource)
    {
        if (!$this->routeCache->contains($resource)) {
            $this->routeCache[$resource] = [];
        }
    }

    /**
     * @return boolean
     */
    public function isIsCollectionEmbed()
    {
        return $this->isCollectionEmbed;
    }

    /**
     * @param boolean $isCollectionEmbed
     *
     * @return $this
     */
    public function setIsCollectionEmbed($isCollectionEmbed)
    {
        $this->isCollectionEmbed = $isCollectionEmbed;

        return $this;
    }
}
