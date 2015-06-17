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

use Doctrine\Common\Util\Inflector;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Eliberty\ApiBundle\Doctrine\Orm\MappingsFilter;
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

    public function __construct(
        RouterInterface $router,
        ResourceCollectionInterface $resourceCollection,
        PropertyAccessorInterface $propertyAccessor
    ) {
        $this->router = $router;
        $this->resourceCollection = $resourceCollection;
        $this->propertyAccessor = $propertyAccessor;
        $this->routeCache = new \SplObjectStorage();
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
                $name = $this->getCollectionRouteName($name);
            } else {
                if ($resource = $this->resourceCollection->getResourceForEntity($name)) {
                    $parameters = $resource->getRouteKeyParams($name);
                    if (empty($parameters)) {
                        $parameters['id'] = $this->propertyAccessor->getValue($name, 'id');
                    }

                    if (null !== $resource->getParent() && !isset($parameters[$resource->getParentName()])) {
                        $parentResource = $resource->getParent();
                        $parentObject = $this->propertyAccessor->getValue(
                            $name,
                            $resource->getParentName()
                        );
                        $parentParams = $parentResource->getRouteKeyParams($parentObject);
                        $parameters = array_merge($parentParams, $parameters);
                    }
                    $name = $this->getItemRouteName($resource);
                }
            }
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
                var_dump($parameters);exit;
            }

        } finally {
            $this->router->setContext($baseContext);
        }
    }

    /**
     * Gets the collection route name for a resource.
     *
     * @param ResourceInterface $resource
     *
     * @return string
     */
    private function getCollectionRouteName(ResourceInterface $resource)
    {
        $this->initRouteCache($resource);

        if (isset($this->routeCache[$resource]['collectionRouteName'])) {
            return $this->routeCache[$resource]['collectionRouteName'];
        }

        $operations = $resource->getCollectionOperations();
        foreach ($operations as $operation) {
            if (in_array('GET', $operation->getRoute()->getMethods())) {
                $data = $this->routeCache[$resource];
                $data['collectionRouteName'] = $operation->getRouteName();
                $this->routeCache[$resource] = $data;

                return $data['collectionRouteName'];
            }
        }
    }

    /**
     * Gets the item route name for a resource.
     *
     * @param ResourceInterface $resource
     *
     * @return string
     */
    private function getItemRouteName(ResourceInterface $resource)
    {
        $this->initRouteCache($resource);

        if (isset($this->routeCache[$resource]['itemRouteName'])) {
            return $this->routeCache[$resource]['itemRouteName'];
        }

        $operations = $resource->getitemOperations();
        foreach ($operations as $operation) {
            if (in_array('GET', $operation->getRoute()->getMethods())) {
                //check if have not the embed routing
                if (!$resource->getParent() instanceof ResourceInterface &&
                    false !== strpos($this->router->getRouteCollection()->get($operation->getRouteName())->getPath(), 'embed')) {
                    continue;
                }

                $data = $this->routeCache[$resource];
                $data['itemRouteName'] = $operation->getRouteName();
                $this->routeCache[$resource] = $data;

                return $data['itemRouteName'];
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
}
