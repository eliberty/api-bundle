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
     * @var ResourceConfigInterface
     */
    private $config;

    /**
     * versionning api
     * @var string
     */
    protected $version;

    /**
     * @param $entityClass
     * @param ResourceConfigInterface $config
     */
    public function __construct(
        $entityClass,
        ResourceConfigInterface $config = null
    ) {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException(sprintf('The class %s does not exist.', $entityClass));
        }

        $this->config = $config;
        $this->entityClass = $entityClass;

        if (null === $this->shortName) {
            $shortName = substr($this->entityClass, strrpos($this->entityClass, '\\') + 1);
            $this->shortName = ucwords(strtolower($shortName));
        }
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
     * Initializes filters.
     *
     * @param array $filters
     */
    public function initFilters(array $filters)
    {
        $this->filters = $filters;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return $this->filters;
    }

    public function addFilter (FilterInterface $filter)
    {
        $this->filters[] = $filter;
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
    public function getValidationGroups($alias = null)
    {
        if (empty($this->validationGroups)) {
            return [];
        }

        $validationGroups = [];
        foreach ($this->validationGroups as $valGrp) {
            if (!is_null($alias) && isset($valGrp[$alias])) {
                return $valGrp[$alias];
            }

            if (!is_array($valGrp)) {
                $validationGroups[] = $valGrp;
            }
        }

        return $validationGroups;
    }

    /**
     * Initializes short name.
     * @param $shortName
     * @return $this
     */
    public function initShortName($shortName)
    {
        if ($this->config instanceof ResourceConfigInterface &&
            null !== $this->config->getShortname()
        ) {
            $this->shortName = $this->config->getShortname();

            return $this;
        }

        $this->shortName = $shortName;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortName()
    {
        if ($this->config instanceof ResourceConfigInterface &&
            null !== $this->config->getShortname()
        ) {
            return $this->config->getShortname();
        }

        return $this->shortName;
    }

    /**
     * @return array
     */
    public function getAlias()
    {
        if ($this->config instanceof ResourceConfigInterface &&
            !empty($this->config->getAlias())
        ) {
            return $this->config->getAlias();
        }

        return [];
    }

    /**
     * @return ResourceInterface
     */
    public function getParent()
    {
        if ($this->config instanceof ResourceConfigInterface &&
            $this->config->getResourceParent() instanceof ResourceInterface
        ) {
            return $this->config->getResourceParent();
        }

        return null;
    }

    /**
     * @return string
     */
    public function getParentName()
    {
        if ($this->config instanceof ResourceConfigInterface &&
            null !== $this->config->getParentName()
        ) {
            return $this->config->getParentName();
        }

        return $this->shortName;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function getRouteKeyParams($data)
    {
        $routeKeyParams = [];
        if ($this->config instanceof ResourceConfigInterface) {
            $routeKeyParams = $this->config->getRouteKeyParams();
        }

        if (empty($routeKeyParams)) {
            $parameterName = 'id'; //strtolower($this->getShortname()).
            $routeKeyParams[$parameterName] = 'getId';
        }

        foreach ($routeKeyParams as $key => $value) {
            $dataValue = $this->getPropertyValue($data, $value);
            if (is_object($dataValue)) {
                $dataValue = $dataValue->getId();
            }
            $routeKeyParams[$key] = $dataValue;
        }

        return $routeKeyParams;
    }

    /**
     * @param $object
     * @param $methode
     * @throws \Exception
     */
    public function getPropertyValue($object, $methode)
    {
        try {
            if (method_exists($object, $methode)) {
                return $object->$methode();
            }

            $parrentGetter = 'get' . $this->getShortName();
            if (!is_null($this->getParent())) {
                $parrentGetter = 'get' . $this->getParent()->getShortName();
            }

            $parentData = $object->$parrentGetter();

            return $parentData->$methode();

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @param string $version
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Initializes collection operations.
     *
     * @param OperationInterface[] $collectionOperations
     */
    public function initCollectionOperations(array $collectionOperations)
    {
        $this->collectionOperations = $collectionOperations;
    }

    /**
     * Initializes item operations.
     *
     * @param OperationInterface[] $itemOperations
     */
    public function initItemOperations(array $itemOperations)
    {
        $this->itemOperations = $itemOperations;
    }

    /**
     * if resource has operation
     */
    public function hasOperations()
    {
        $dataResponse = false;

        if (!empty($this->itemOperations) || !empty($this->collectionOperations)) {
            $dataResponse = true;
        }

        return $dataResponse;
    }
}
