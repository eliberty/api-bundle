<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 **/

namespace Eliberty\ApiBundle\Fractal;

use Doctrine\Common\Inflector\Inflector;
use Eliberty\ApiBundle\Fractal\Serializer\DataHydraSerializer;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Scope as BaseFractalScope;
use League\Fractal\Resource\Collection;
use Dunglas\ApiBundle\Api\ResourceInterface as DunglasResource;
use League\Fractal\Serializer\SerializerAbstract;
use League\Fractal\TransformerAbstract;

/**
 * Scope.
 *
 * The scope class acts as a tracker, relating a specific resource in a specific
 * context. For example, the same resource could be attached to multiple scopes.
 * There are root scopes, parent scopes and child scopes.
 */
class Scope extends BaseFractalScope
{
    /**
     * @var DunglasResource
     */
    protected $dunglasResource;

    /**
     * @var TransformerAbstract
     */
    protected $transformer;
    /**
     * @var array
     */
    protected $data;

    /**
     * @var Scope
     */
    protected $parent;

    /**
     * @var string
     */
    const HYDRA_COLLECTION = 'hydra:Collection';
    /**
     * @var string
     */
    const HYDRA_PAGED_COLLECTION = 'hydra:PagedCollection';

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Convert the current data for this scope to an array.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function toArray()
    {
        $this->resource->setResourceKey($this->scopeIdentifier);

        if ($this->resource instanceof Collection) {
            return $this->collectionNormalizer();
        }

        return $this->itemNormalizer();
    }

    /**
     *
     */
    protected function collectionNormalizer()
    {
        $serializer = $this->manager->getSerializer();

        // Don't use hydra:Collection in sub levels
        $context['json_ld_sub_level'] = true;

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();

        if (count($rawData) === 0) {
            return [];
        }

        $data = $this->serializeResource($serializer, $rawData);

        if ($this->resource instanceof Collection) {
            if ($this->resource->hasCursor()) {
                $pagination = $serializer->cursor($this->resource->getCursor());
            } elseif ($this->resource->hasPaginator()) {
                $pagination = $serializer->paginator($this->resource->getPaginator());
            }

            if (! empty($pagination)) {
                $this->resource->setMetaValue(key($pagination), current($pagination));
            }
        }

        // Pull out all of OUR metadata and any custom meta data to merge with the main level data
        $meta = $serializer->meta($this->resource->getMeta());

        return array_merge($meta, $data);
    }

    /**
     * check if scope has parent
     */
    public function hasParent()
    {
        return ($this->parent instanceof Scope);
    }

    /**
     * @return array
     *
     * @throws \Exception
     */
    protected function itemNormalizer()
    {
        $serializer = $this->manager->getSerializer();

        // Don't use hydra:Collection in sub levels
        $context['json_ld_sub_level'] = true;

        $this->dunglasResource = $this->getDunglasResource();

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();

        $data = $this->serializeResource($serializer, $rawData);

        // If the serializer wants the includes to be side-loaded then we'll
        // serialize the included data and merge it with the data.
        if ($serializer->sideloadIncludes()) {
            $includedData = $serializer->includedData($this->resource, $rawIncludedData);

            $data = array_merge($data, $includedData);
        }

        // Pull out all of OUR metadata and any custom meta data to merge with the main level data
        $meta = $serializer->meta($this->resource->getMeta());

        return array_merge($meta, $data);
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        if (substr($this->scopeIdentifier, -1) === 's') {
            return ucwords(Inflector::singularize($this->scopeIdentifier));
        }

        return ucwords($this->scopeIdentifier);
    }

    /**
     * @throws \Exception
     */
    public function getDunglasResource()
    {
        if (!is_null($this->dunglasResource)) {
            return $this->dunglasResource;
        }

        return $this->findDunglasResource($this->getEntityName());
    }

    /**
     * @param $entityName
     *
     * @return mixed
     * @throws \Exception
     */
    protected function findDunglasResource($entityName)
    {
        $resource = $this->manager->getResourceCollection()->getResourceForShortName(
            $entityName,
            $this->getApiVersion()
        );
        if (null === $resource) {
            throw new \Exception('resource not found for entityname : ' . $entityName);
        }

        return $resource;
    }

    /**
     * @throws \Exception
     */
    protected function getApiVersion()
    {
        $version = 'v2';
        if ($this->parent instanceof Scope && $this->parent->getDunglasResource() instanceof DunglasResource) {
            $version = $this->parent->getDunglasResource()->getVersion();
        }

        return $version;
    }


    /**
     * Fire the main transformer.
     *
     * @internal
     *
     * @param TransformerAbstract|callable $transformer
     * @param mixed                        $data
     *
     * @return array
     */
    protected function fireTransformer($transformer, $data)
    {
        $this->transformer = $transformer;
        $includedData = [];
        $transformedData = [];

        if ($this->getManager()->getSerializer() instanceof DataHydraSerializer && !empty($data)) {
            $transformedData['@id'] = $this->getGenerateRoute($data);
        }

        if ($this->getManager()->getSerializer() instanceof DataHydraSerializer && !empty($this->getEntityName())) {
            $transformedData['@type'] = $this->getEntityName();
        }

        if (is_callable($transformer)) {
            $transformedData = array_merge($transformedData, call_user_func($transformer, $data));
        } else {
            $transformedData = array_merge($transformedData, $transformer->transform($data));
        }

        if ($this->transformerHasIncludes($transformer)) {
            $includedData = $this->fireIncludedTransformers($transformer, $data);
            // If the serializer does not want the includes to be side-loaded then
            // the included data must be merged with the transformed data.
            if (! $this->manager->getSerializer()->sideloadIncludes()) {
                $transformedData = array_merge($transformedData, $includedData);
            }
        }
        return array($transformedData, $includedData);
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param array
     *
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return scope
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param mixed $parent
     *
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Determine if a transformer has any available includes.
     *
     * @internal
     *
     * @param TransformerAbstract|callable $transformer
     *
     * @return bool
     */
    protected function transformerHasIncludes($transformer)
    {
        $parentScope = $this->getParent();

        if (!$parentScope instanceof Scope) {
            return parent::transformerHasIncludes($transformer);
        }

        if ($parentScope->getDunglasResource()->getShortName() === $this->getDunglasResource()->getShortName()) {
            return true;
        }

        if ($parentScope->getParent() instanceof Scope) {
            $embedsRequest = array_keys($transformer->getRequestEmbeds());
            $transformer->setDefaultIncludes([]);

            return in_array(strtolower($this->getIdentifierWithoutSourceIdentifier()), $embedsRequest);
        }

        return true;
    }

    /**
     * @param string $position
     *
     * @return mixed
     */
    public function getSingleIdentifier($position = 'desc')
    {
        $identifiers = explode('.', $this->getIdentifier());

        return ('desc' === $position) ? array_pop($identifiers) : array_shift($identifiers);
    }

    /**
     * @return string
     */
    public function getIdentifierWithoutSourceIdentifier()
    {
        return str_replace($this->getSingleIdentifier('asc') . '.', '', $this->getIdentifier());
    }


    /**
     * @param DunglasResource $dunglasResource
     *
     * @return $this
     */
    public function setDunglasResource($dunglasResource)
    {
        $this->dunglasResource = $dunglasResource;

        return $this;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function getGenerateRoute($data, $params = [])
    {
        $this->getManager()->getRouter()->setScope($this);

        return $this->getManager()->getRouter()->generate($data, $params);
    }

    /**
     * Serialize a resource
     *
     * @internal
     *
     * @param SerializerAbstract $serializer
     * @param mixed              $data
     *
     * @return array
     */
    protected function serializeResource(SerializerAbstract $serializer, $data)
    {
        $serializer->setScope($this);
        return parent::serializeResource($serializer, $data);
    }

}
