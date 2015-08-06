<?php
namespace Eliberty\ApiBundle\WebHook\Doctrine\Listener;

use Doctrine\ORM\Mapping\EntityListenerResolver as baseEntityListenerResolver;

/**
 * Class EntityListenerResolver
 * @package Eliberty\ApiBundle\WebHook\Doctrine\Listener
 */
class EntityListenerResolver implements baseEntityListenerResolver
{

    /**
     * @var
     */
    private $instances = [];


    /**
     * {@inheritdoc}
     */
    public function clear($className = null)
    {
        if ($className === null) {
            $this->instances = array();

            return;
        }

        if (isset($this->instances[$className = trim($className, '\\')])) {
            unset($this->instances[$className]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function register($object)
    {
        if ( ! is_object($object)) {
            throw new \InvalidArgumentException(sprintf('An object was expected, but got "%s".', gettype($object)));
        }

        $this->instances[get_class($object)] = $object;
    }

    /**
     * @param $service
     * @param $class
     */
    public function addMapping($service, $class)
    {
        $this->instances[$class] = $service;

    }

    /**
     * {@inheritdoc}
     */
    public function resolve($className)
    {
        if (isset($this->instances[$className = trim($className, '\\')])) {
            return $this->instances[$className];
        }

        return $this->instances[$className] = new $className();
    }
}