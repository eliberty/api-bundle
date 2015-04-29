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
     * @param string $entityClass
     * @param array $alias
     */
    public function __construct(
        $entityClass,
        $alias = array()
    ) {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException(sprintf('The class %s does not exist.', $entityClass));
        }

        $this->entityClass = $entityClass;
        $shortName = substr($this->entityClass, strrpos($this->entityClass, '\\') + 1);
        $this->shortName = ucwords(strtolower($shortName));

        $this->alias = $alias;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClass()
    {
        return $this->entityClass;
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
    public function addMappingOperation(OperationInterface $operation)
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
}
