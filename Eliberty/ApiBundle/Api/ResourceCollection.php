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

use Dunglas\ApiBundle\Api\ResourceCollection as BaseResourceCollection;

use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Util\ClassInfoTrait;
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
    public function getResourceForEntity($entityClass, $version = null)
    {
        if (is_object($entityClass)) {
            $entityClass = $this->getObjectClass($entityClass);
        }

        if (is_null($version)) {
            $version = $this->version;
        }

        if (isset($this->entityClassIndex[$version][$entityClass])) {
            return $this->entityClassIndex[$version][$entityClass];
        }

        return null;

    }

    /**
     * @param string $shortName
     * @param null $version
     * @return ResourceInterface|null
     */
    public function getResourceForShortName($shortName, $version = null)
    {
        if (is_null($version)) {
            $version = $this->version;
        }

        if (isset($this->shortNameIndex[$version][$shortName])) {
            return $this->shortNameIndex[$version][$shortName];
        }

        return null;
    }
}
