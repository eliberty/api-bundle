<?php
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Util\ClassInfoTrait;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;

/**
 * Class ResourceCollection
 * @package Eliberty\ApiBundle\Api
 */
class ResourceCollection extends \ArrayObject implements ResourceCollectionInterface
{
    use ClassInfoTrait;

    /**
     * @var array
     */
    private $entityClassIndex = [];

    /**
     * @var array
     */
    private $shortNameIndex = [];

    /**
     * @param array $resources
     * @internal param ResourceInterface $resource
     */
    public function init(array $resources)
    {
        foreach ($resources as $resource) {
            if ($resource->hasOperations()) {
                $this->addResource($resource);
            }
        }
    }

    /**
     * @param Resource $resource
     */
    public function addResource($resource)
    {
        $version = $resource->getVersion();
        $entityClass = $resource->getEntityClass();
        if (isset($this->entityClassIndex[$version][$entityClass])) {
            throw new \InvalidArgumentException(sprintf('A Resource class already exists for "%s".', $entityClass));
        }

        $shortName = $resource->getShortName();
        if (isset($this->shortNameIndex[$version][$shortName])) {
            throw new \InvalidArgumentException(sprintf('A Resource class with the short name "%s" already exists.', $shortName));
        }

        $this->append($resource);

        $this->entityClassIndex[$version][$entityClass] = $resource;

        $this->shortNameIndex[$version][$shortName] = $resource;

        foreach ($resource->getAlias() as $alias) {
            if (!class_exists($alias)) {
                $this->shortNameIndex[$version][$alias] = $resource;
                continue;
            }
            $this->entityClassIndex[$version][$alias] = $resource;
        }
    }

    /**
     * @param object|string $entityClass
     * @param null $version
     * @return ResourceInterface|null
     */
    public function getResourceForEntityWithVersion($entityClass, $version)
    {
        if (is_object($entityClass)) {
            $entityClass = $this->getObjectClass($entityClass);
        }

        if (isset($this->entityClassIndex[$version][$entityClass])) {
            return $this->entityClassIndex[$version][$entityClass];
        }

        return null;

    }

    /**
     * @param object|string $entityClass
     *
     * @return null
     */
    public function getResourceForEntity($entityClass)
    {
        if (is_object($entityClass)) {
            $entityClass = $this->getObjectClass($entityClass);
        }

        if (isset($this->entityClassIndex['v2'][$entityClass])) {
            return $this->entityClassIndex['v2'][$entityClass];
        }

        return null;

    }

    /**
     * @param string $shortName
     * @param null $version
     * @return ResourceInterface|null
     */
    public function getResourceForShortNameWithVersion($shortName, $version)
    {
        if (isset($this->shortNameIndex[$version][$shortName])) {
            return $this->shortNameIndex[$version][$shortName];
        }

        return null;
    }

    /**
     * @param string $shortName
     *
     * @return null
     */
    public function getResourceForShortName($shortName)
    {
        if (isset($this->shortNameIndex['v2'][$shortName])) {
            return $this->shortNameIndex['v2'][$shortName];
        }

        return null;
    }
}
