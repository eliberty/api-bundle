<?php

namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\ResourceCollection as BaseResourceCollection;

use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Util\ClassInfo;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;


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
     * @var string
     */
    protected $version = 'v1';

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $request = $requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'))->all();

            foreach ($acceptHeader as $acceptHeaderItem) {
                if ($acceptHeaderItem->hasAttribute('version')) {
                    $this->version = $acceptHeaderItem->getAttribute('version');
                    break;
                }
            }
        }
    }

    /**
     * @param ResourceInterface $resource
     */
    public function add(ResourceInterface $resource)
    {
        $entityClass = $resource->getEntityClass();
        if (isset($this->entityClassIndex[$this->version][$entityClass])) {
            throw new \InvalidArgumentException(sprintf('A Resource class already exists for "%s".', $entityClass));
        }

        $shortName = $resource->getShortName();
        if (isset($this->shortNameIndex[$this->version][$shortName])) {
            throw new \InvalidArgumentException(sprintf('A Resource class with the short name "%s" already exists.', $shortName));
        }

        $this->append($resource);

        $this->entityClassIndex[$this->version][$entityClass] = $resource;

        $this->shortNameIndex[$this->version][$shortName] = $resource;

        foreach ($resource->getAlias() as $alias) {
            if (!class_exists($alias)) {
                $this->shortNameIndex[$this->version][$alias] = $resource;
                continue;
            }
            $this->entityClassIndex[$this->version][$alias] = $resource;
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

        if (isset($this->entityClassIndex[$this->version][$entityClass])) {
            return $this->entityClassIndex[$this->version][$entityClass];
        }

        return null;

    }

    /**
     * @param string $shortName
     * @return ResourceInterface|null
     */
    public function getResourceForShortName($shortName)
    {
        if (isset($this->shortNameIndex[$this->version][$shortName])) {
            return $this->shortNameIndex[$this->version][$shortName];
        }

        return null;
    }
}
