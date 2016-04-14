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
use Dunglas\ApiBundle\Model\PaginatorInterface;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use League\Fractal\Pagination\PaginatorInterface as FractalPaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Scope as BaseFractalScope;
use League\Fractal\Resource\Collection;
use Dunglas\ApiBundle\Api\ResourceInterface as DunglasResource;
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

        $this->dunglasResource = $this->getDunglasResource();

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();

        if (count($rawData) === 0) {
            return [];
        }

        $data['@context'] = $this->getContext($this->dunglasResource);

        if (!$this->resource->getTransformer()->isChild()) {
            $data['@embed'] = implode(',', $this->resource->getTransformer()->getAvailableIncludes());
        }

        if (!is_null($this->resource)) {
            foreach ($this->dunglasResource->getFilters() as $filter) {
                if ($filter instanceof EmbedFilter) {
                    $route = $this->getGenerateRoute($filter->getRouteName(), $filter->getParameters());
                    break;
                }
            }
            if (empty($route)) {
                $parameters = [];
                if (
                    $this->dunglasResource->getParent() instanceof DunglasResource
                ) {
                    $dunglasParentResource = $this->dunglasResource->getParent();
                    $parameters = $dunglasParentResource->getRouteKeyParams($this->getParent()->getData());
                }
                try {
                    $route = $this->getGenerateRoute($this->dunglasResource, $parameters);
                } catch (\Exception $e) {
                   // var_dump($e);exit;
                    $route = $e->getMessage();
                }
            }
            $data['@id'] = $route;
        }

        $object = $this->resource->getData();

        if ($this->resource->hasPaginator()) {
            $object = $this->resource->getPaginator();
        }

        if ($object instanceof PaginatorInterface || $object instanceof FractalPaginatorInterface) {
            $data['@type'] = self::HYDRA_PAGED_COLLECTION;

            $currentPage = (int) $object->getCurrentPage();
            $lastPage    = (int) $object->getLastPage();

            $baseUrl = $data['@id'];
            $paginatedUrl = $baseUrl.'?perpage='.$object->getItemsPerPage().'&page=';

            $this->getPreviewPage($data, $object, $currentPage, $paginatedUrl, $baseUrl);
            $this->getNextPage($data, $object, $currentPage, $lastPage, $paginatedUrl);

            $data['hydra:totalItems'] =  $object->getTotalItems();
            $data['hydra:itemsPerPage'] = $object->getItemsPerPage();
            $this->getFirstPage($data, $object, $currentPage, $lastPage, $baseUrl);
            $this->getLastPage($data, $object, $currentPage, $lastPage, $paginatedUrl);
        } else {
            $data['@type'] = self::HYDRA_COLLECTION;
        }

        $data = array_merge($data, $this->serializeResource($serializer, $rawData));

        return $data;
    }

    /**
     * check if scope has parent
     */
    protected function hasParent() {
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

        $data = [];
        if ($this->dunglasResource instanceof DunglasResource) {
            $data['@context'] = $this->getContext($this->dunglasResource);
        }

        if (!$this->resource->getTransformer()->isChild()) {
            $data['@embed'] = implode(',', $this->resource->getTransformer()->getAvailableIncludes());
        }

        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();

        $data = array_merge($data, $this->serializeResource($serializer, $rawData));

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
     * @return mixed
     * @throws \Exception
     */
    protected function findDunglasResource($entityName) {
        $resource = $this->manager->getResourceCollection()->getResourceForShortName(
            $entityName,
            $this->getApiVersion()
        );
        if (null === $resource) {
            throw new \Exception('resource not found for entityname : '.$entityName);
        }

        return $resource;
    }

    /**
     * @throws \Exception
     */
    protected function getApiVersion() {
        $version = 'v2';
        if ($this->parent instanceof Scope && $this->parent->getDunglasResource() instanceof DunglasResource) {
            $version = $this->parent->getDunglasResource()->getVersion();
        }

        return $version;
    }

    /**
     * @param DunglasResource $resource
     *
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
        $this->transformer = $transformer;
        $includedData = [];
        $transformedData = [];

        if (!empty($data)) {
            $transformedData['@id'] = $this->getGenerateRoute($data);
        }

        if (!empty($this->getEntityName())) {
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
     * @param $data
     * @param $object
     * @param $currentPage
     * @param $paginatedUrl
     * @param $baseUrl
     * @return bool
     */
    protected function getPreviewPage(&$data, $object, $currentPage, $paginatedUrl, $baseUrl)
    {
        if (0 !== ($currentPage - 1)) {
            $previousPage = $currentPage - 1.;
            if ($object instanceof FractalPaginatorInterface) {
                $data['hydra:previousPage'] = $object->getUrl($currentPage - 1);

                return true;
            }

            $data['hydra:previousPage'] = 1 === $previousPage ? $baseUrl : $paginatedUrl.$previousPage;
        }
    }

    /**
     * @param $data
     * @param $object
     * @param $currentPage
     * @param $lastPage
     * @param $paginatedUrl
     * @return bool
     */
    protected function getNextPage(&$data, $object, $currentPage, $lastPage, $paginatedUrl)
    {
        if ($currentPage !== $lastPage) {
            if ($object instanceof FractalPaginatorInterface) {
                $data['hydra:nextPage'] = $object->getUrl($currentPage + 1);

                return true;
            }

            $data['hydra:nextPage'] = $paginatedUrl.($currentPage + 1.);
        }
    }

    /**
     * @param $data
     * @param $object
     * @param $currentPage
     * @param $lastPage
     * @param $paginatedUrl
     *
     * @return bool
     */
    protected function getLastPage(&$data, $object, $currentPage, $lastPage, $paginatedUrl)
    {
        if ($object instanceof FractalPaginatorInterface) {
            $data['hydra:lastPage'] = $object->getUrl($lastPage);

            return true;
        }

        $data['hydra:lastPage'] = $paginatedUrl.$lastPage;
    }

    /**
     * @param $data
     * @param $object
     * @param $currentPage
     * @param $lastPage
     * @param $baseUrl
     *
     * @return bool
     */
    protected function getFirstPage(&$data, $object, $currentPage, $lastPage, $baseUrl)
    {
        if ($object instanceof FractalPaginatorInterface) {
            $data['hydra:firstPage'] = $object->getUrl(1);

            return true;
        }

        $data['hydra:firstPage'] = $baseUrl;
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function getGenerateRoute($data, $params = [])
    {
        $this->manager->getRouter()->setScope($this);

        return $this->manager->getRouter()->generate($data, $params);
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
     * @return mixed
     */
    public function getSingleIdentifier($position = 'desc') {
        $identifiers = explode('.', $this->getIdentifier());
        return ('desc' === $position) ? array_pop($identifiers) : array_shift($identifiers);
    }

    /**
     * @return string
     */
    public function getIdentifierWithoutSourceIdentifier() {
        return str_replace($this->getSingleIdentifier('asc').'.', '', $this->getIdentifier());
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


}
