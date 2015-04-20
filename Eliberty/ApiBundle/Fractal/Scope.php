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
use Doctrine\ORM\PersistentCollection;
use Dunglas\JsonLdApiBundle\Model\PaginatorInterface;
use League\Fractal\Scope as BaseFractalScope;
use InvalidArgumentException;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Serializer\SerializerAbstract;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Dunglas\JsonLdApiBundle\Api\Resource as DunglasResource;

/**
 * Scope
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
     * @var string
     */
    const HYDRA_COLLECTION = 'hydra:Collection';
    /**
     * @var string
     */
    const HYDRA_PAGED_COLLECTION = 'hydra:PagedCollection';

    /**
     * Convert the current data for this scope to an array.
     * @return array
     * @throws \Exception
     */
    public function toArray()
    {
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

        $this->dunglasResource = $this->getDunglasRessource();

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();
        if (count($rawData) === 0) {
            return [];
        }

        $data['@context'] = $this->getContext($this->dunglasResource);

        if(!is_null($this->resource)) {
            $data['@id'] = $this->manager->getRouter()->generate($this->dunglasResource);
        }


        $object = $this->resource->getData();
        if ($object instanceof PaginatorInterface) {
            $data['@type'] = self::HYDRA_PAGED_COLLECTION;

            $currentPage = $object->getCurrentPage();
            $lastPage = $object->getLastPage();

            $baseUrl = $data['@id'];
            $paginatedUrl = $baseUrl.'?page=';

            if (1. !== $currentPage) {
                $previousPage = $currentPage - 1.;
                $data['@id'] .= $paginatedUrl.$currentPage;
                $data['hydra:previousPage'] = 1. === $previousPage ? $baseUrl : $paginatedUrl.$previousPage;
            }

            if ($currentPage !== $lastPage) {
                $data['hydra:nextPage'] = $paginatedUrl.($currentPage + 1.);
            }

            $data['hydra:totalItems'] = $object->getTotalItems();
            $data['hydra:itemsPerPage'] = $object->getItemsPerPage();
            $data['hydra:firstPage'] = $baseUrl;
            $data['hydra:lastPage'] = 1. === $lastPage ? $baseUrl : $paginatedUrl.$lastPage;
        } else {
            $data['@type'] = self::HYDRA_COLLECTION;
        }

        $data = array_merge($data ,$this->serializeResource($serializer, $rawData));

        return $data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function itemNormalizer()
    {
        $serializer = $this->manager->getSerializer();

        // Don't use hydra:Collection in sub levels
        $context['json_ld_sub_level'] = true;

        $this->dunglasResource = $this->getDunglasRessource();

        $data['@context'] = $this->getContext($this->dunglasResource);

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();
        $data = array_merge($data ,$this->serializeResource($serializer, $rawData));

        // If the serializer wants the includes to be side-loaded then we'll
        // serialize the included data and merge it with the data.
        if ($serializer->sideloadIncludes()) {
            $includedData = $serializer->includedData($this->resource, $rawIncludedData);

            $data = array_merge($data, $includedData);
        }

        // Pull out all of OUR metadata and any custom meta data to merge with the main level data
        $meta = $serializer->meta($this->resource->getMeta());

        return array_merge($data, $meta);
    }

    /**
     * @return string
     */
    protected function getEntityName(){
        return ucwords(Inflector::singularize($this->scopeIdentifer));
    }

    /**
     * @throws \Exception
     */
    protected function getDunglasRessource()
    {
        $resource = $this->manager->getResourceCollection()->getResourceForShortName($this->getEntityName());
        if (null === $resource) {
            throw new \Exception('resource not found for entityname : '.$this->getEntityName());
        }

        return $resource;
    }

    /**
     * @param DunglasResource $resource
     * @return mixed
     */
    protected function getContext(DunglasResource $resource)
    {
        return $this->manager->getRouter()->generate(
            'api_json_ld_context',
            ['shortName' => $resource->getShortName()]
        );
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
        $includedData = [];
        $transformedData = [];

        if(!is_null($this->dunglasResource)) {
            $transformedData['@id'] = $this->manager->getRouter()->generate($data);
        }

        $classMetadata =  $this->manager->getApiClassMetadataFactory()->getMetadataFor(
            $this->dunglasResource->getEntityClass()
        );

        $transformedData['@type'] =  ($iri = $classMetadata->getIri()) ? $iri : $this->getEntityName();

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
}
