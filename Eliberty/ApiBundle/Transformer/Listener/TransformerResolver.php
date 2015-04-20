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
    private $version;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->mapping   = array();
        $this->version   = $container->get('router')->getContext()->getApiVersion();
    }

    /**
     * @param $service
     * @param array $tags
     */
    public function addMapping($service, $tags = [])
    {
        if(array_key_exists('api_'.$this->version, $tags)){
            $this->mapping[$service] = $service;
        }
    }

    /**
     * @param $entityName
     * @return object
     * @throws \Exception
     */
    public function resolve($entityName)
    {
        $serviceId = 'transformer.'.strtolower($entityName);
        if (isset($this->mapping[$serviceId])) {
            return $this->container->get($this->mapping[$serviceId]);
        }

        throw new \Exception('transformer not found');
    }
}