<?php

namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\Filter\FilterInterface;
use Dunglas\ApiBundle\Api\Operation\OperationInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;

/**
 * Class Resource
 * @package Eliberty\ApiBundle\Api
 */
class Resource implements ResourceInterface
{
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var OperationInterface[]
     */
    protected $itemOperations = [];
    /**
     * @var OperationInterface[]
     */
    protected $collectionOperations = [];
    /**
     * @var FilterInterface[]
     */
    protected $filters = [];
    /**
     * @var array
     */
    protected $normalizationContext = [];
    /**
     * @var array
     */
    protected $denormalizationContext = [];
    /**
     * @var array|null
     */
    protected $validationGroups;
    /**
     * @var string|null
     */
    protected $shortName;

    /**
     * @var array
     */
    protected $alias = [];

    /**
     * @var null| ResourceInterface
     */
    protected $parent = null;
    /**
     * @var null
     */
    private $parentName;

    /**
     * @param $entityClass
     * @param array $alias
     * @param ResourceInterface $parent
     * @param null $shortname
     * @param null $parentName
     */
    public function __construct(
        $entityClass,
        $alias = [],
        ResourceInterface $parent = null,
        $shortname = null,
        $parentName = null
    ) {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException(sprintf('The class %s does not exist.', $entityClass));
        }

        $this->entityClass = $entityClass;
        $this->shortName   = $shortname;
        $this->parentName = $parentName;
        $this->setParent($parent);

        if (null === $shortname) {
            $shortName = substr($this->entityClass, strrpos($this->entityClass, '\\') + 1);
            $this->shortName = ucwords(strtolower($shortName));
        }

        $this->alias = $alias;

        $this->parentName = $parentName;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * @param ResourceInterface|null $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {

        $this->parent = $parent;

        if (null !== $this->parent && null === $this->parentName) {
            $this->parentName = $this->parent->getShortName();
        }

        return $this;
    }

    /**
     * @return null
     */
    public function getParentName()
    {
        return $this->parentName;
    }



    /**
     * {@inheritdoc}
     */
    public function addCollectionOperation(OperationInterface $operation)
    {
        $this->collectionOperations[] = $operation;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectionOperations()
    {
        return $this->collectionOperations;
    }

    /**
     * {@inheritdoc}
     */
    public function addItemOperation(OperationInterface $operation)
    {
        $this->itemOperations[] = $operation;
    }

    /**
     * {@inheritdoc}
     */
    public function addEmbedOperation(OperationInterface $operation)
    {
        $this->itemOperations[] = $operation;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemOperations()
    {
        return $this->itemOperations;
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter(FilterInterface $filter)
    {
        $this->filters[] = $filter;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Initializes normalization context.
     *
     * @param array $normalizationContext
     */
    public function initNormalizationContext(array $normalizationContext)
    {
        $this->normalizationContext = $normalizationContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizationContext()
    {
        if (!isset($this->normalizationContext['resource'])) {
            $this->normalizationContext['resource'] = $this;
        }

        return $this->normalizationContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getNormalizationGroups()
    {
        return isset($this->normalizationContext['groups']) ? $this->normalizationContext['groups'] : null;
    }

    /**
     * Initializes denormalization context.
     *
     * @param array $denormalizationContext
     */
    public function initDenormalizationContext(array $denormalizationContext)
    {
        $this->denormalizationContext = $denormalizationContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getDenormalizationContext()
    {
        if (!isset($this->denormalizationContext['resource'])) {
            $this->denormalizationContext['resource'] = $this;
        }

        return $this->denormalizationContext;
    }

    /**
     * {@inheritdoc}
     */
    public function getDenormalizationGroups()
    {
        return isset($this->denormalizationContext['groups']) ? $this->denormalizationContext['groups'] : null;
    }

    /**
     * Initializes validation groups.
     *
     * @param array $validationGroups
     */
    public function initValidationGroups(array $validationGroups)
    {
        $this->validationGroups = $validationGroups;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidationGroups()
    {
        return $this->validationGroups;
    }

    /**
     * Initializes short name.
     *
     * @param string $shortName
     */
    public function initShortName($shortName)
    {
        $this->shortName = $shortName;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        return $this->shortName;
    }

    /**
     * @return array
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * @return array
     */
    public function getParent()
    {
        return $this->parent;
    }
}
