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
//        $container->get('router')->matchRequest($container->get('request'));
        //$this->version   = $container->get('router')->getContext()->getApiVersion();
    }

    /**
     * @param $service
     * @param array $tags
     */
    public function addMapping($service, $tags = [])
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
//        var_dump($serviceId);
        if (isset($this->mapping[$serviceId])) {
            return $this->container->get($this->mapping[$serviceId]);
        }

        throw new \Exception('transformer not found for '.$serviceId);
    }
}