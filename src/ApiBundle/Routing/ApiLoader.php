<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Routing;

use Dunglas\ApiBundle\Api\Operation\Operation;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\XmlFileLoader;
use Symfony\Component\Routing\RouteCollection;
//use Dunglas\ApiBundle\Routing\ApiLoader as BaseApiLoader;

/**
 * Loads Resources.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ApiLoader extends Loader
{
    const ROUTE_NAME_PREFIX = 'api_';

    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var XmlFileLoader
     */
    private $fileLoader;

    public function __construct(ResourceCollectionInterface $resourceCollection, KernelInterface $kernel)
    {
        $this->resourceCollection = $resourceCollection;
        $this->fileLoader = new XmlFileLoader(new FileLocator($kernel->locateResource('@DunglasApiBundle/Resources/config/routing')));
    }

    /**
     * {@inheritdoc}
     */
    public function load($data, $type = null)
    {
        $routeCollection = new RouteCollection();

        $routeCollection->addCollection($this->fileLoader->load('json_ld.xml'));
        $routeCollection->addCollection($this->fileLoader->load('hydra.xml'));

        foreach ($this->resourceCollection as $resource) {
            foreach ($resource->getCollectionOperations() as $operation) {
                $routeCollection->add($operation->getRouteName(), $operation->getRoute());
            }

            $items = $resource->getItemOperations();
            if ($resource->getEmbedOperation() instanceof Operation) {
                $items[] = $resource->getEmbedOperation();
            }

            foreach ($items as $operation) {
                $routeCollection->add($operation->getRouteName(), $operation->getRoute());
            }
        }

        return $routeCollection;
    }

    /**
     * {@inheritdoc}
     */
    public function supports($resource, $type = null)
    {
        return 'api' === $type;
    }
}
