<?php
namespace Eliberty\ApiBundle\Transformer\Listener;

use Doctrine\ORM\Mapping\DefaultEntityListenerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TransformerResolver
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var
     */
    private $mapping;

    /**
     * @var string
     */
    private $version = 'v1';

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->mapping   = array();
        $router = $container->get('router');
//
//        if (empty($router->getContext()->getApiVersion())) {
//            $request = $container->get('request');
//            $router->matchRequest($request);
//        }

        $versioning = $router->getContext()->getApiVersion();
        if (!empty($versioning)) {
            $this->version = $versioning;
        }
    }

    /**
     * @param string $version
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @param $service
     * @param array $tags
     */
    public function addMapping($service)
    {
        $this->mapping[$service] = $service;
    }

    /**
     * @param $entityName
     * @return object
     * @throws \Exception
     */
    public function resolve($entityName)
    {
        $serviceId = 'transformer.'.strtolower($entityName).'.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->container->get($this->mapping[$serviceId]);
        }

        throw new \Exception('transformer not found for '.$serviceId);
    }
}