<?php
namespace Eliberty\ApiBundle\WebHook\Doctrine\Listener;

use Doctrine\ORM\Mapping\DefaultEntityListenerResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;

class EntityListenerResolver extends DefaultEntityListenerResolver
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
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->mapping = array();
    }

    /**
     * @param $className
     * @param $service
     */
    public function addMapping($className, $service)
    {
        $this->mapping[$className] = $service;

    }

    /**
     * @param string $className
     * @return object
     */
    public function resolve($className)
    {

        if (isset($this->mapping[$className]) && $this->container->has($this->mapping[$className])) {
            return $this->container->get($this->mapping[$className]);
        }

        return parent::resolve($className);
    }
}