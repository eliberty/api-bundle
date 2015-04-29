<?php

namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\ResourceCollection as BaseResourceCollection;

use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Util\ClassInfo;

/**
 * Class ResourceCollection
 * @package Eliberty\ApiBundle\Api
 */
class ResourceCollection extends \ArrayObject implements ResourceCollectionInterface
{
    use ClassInfo;

    /**
     * @var array
     */
    private $entityClassIndex = [];
    /**
     * @var array
     */
    private $shortNameIndex = [];

    /**
     * @param ResourceInterface $resource
     */
    public function add(ResourceInterface $resource)
    {
        $entityClass = $resource->getEntityClass();
        if (isset($this->entityClassIndex[$entityClass])) {
            throw new \InvalidArgumentException(sprintf('A Resource class already exists for "%s".', $entityClass));
        }

        $shortName = $resource->getShortName();
        if (isset($this->shortNameIndex[$shortName])) {
            throw new \InvalidArgumentException(sprintf('A Resource class with the short name "%s" already exists.', $shortName));
        }

        $this->append($resource);

        $this->entityClassIndex[$entityClass] = $resource;

        $this->shortNameIndex[$shortName] = $resource;

        foreach ($resource->getAlias() as $alias) {
            if (!class_exists($alias)) {
                $this->shortNameIndex[$alias] = $resource;
                continue;
            }
            $this->entityClassIndex[$alias] = $resource;
        }
    }

    /**
     * @param object|string $entityClass
     * @return ResourceInterface|null
     */
    public function getResourceForEntity($entityClass)
    {
        if (is_object($entityClass)) {
            $entityClass = $this->getObjectClass($entityClass);
        }

        if (isset($this->entityClassIndex[$entityClass])) {
            return $this->entityClassIndex[$entityClass];
        }

        return null;

    }

    /**
     * @param string $shortName
     * @return ResourceInterface|null
     */
    public function getResourceForShortName($shortName)
    {
        if (isset($this->shortNameIndex[$shortName])) {
            return $this->shortNameIndex[$shortName];
        }

        return null;
    }
}
