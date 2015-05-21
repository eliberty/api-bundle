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
use Eliberty\ApiBundle\Doctrine\Orm\EmbedFilter;
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

        $this->dunglasResource = $this->getDunglasRessource();

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
                if (!is_null($this->dunglasResource->getParent())) {
                    if ($filter instanceof EmbedFilter) {
                        $route = $this->getGenerateRoute($filter->getRouteName(), $filter->getParameters());
                        break;
                    }
                } else if ($filter instanceof EmbedFilter) {
                    $route = $this->getGenerateRoute($filter->getRouteName(), $filter->getParameters());
                    break;
                }
            }
            if (empty($route)) {
                if (null === $this->dunglasResource->getParent()) {
                    $route = $this->getGenerateRoute($this->dunglasResource);
                } else {
                    $parameterName = strtolower($this->getParent()->dunglasResource->getShortname()).'id';
                    $value = $this->getParent()->getData()->getId();
                    $parameters[$parameterName] = $value;
                    $route = $this->getGenerateRoute($this->dunglasResource, $parameters);
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
     * @return array
     *
     * @throws \Exception
     */
    protected function itemNormalizer()
    {
        $this->logger->info('itemNormalizer1 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $serializer = $this->manager->getSerializer();
        $this->logger->info('itemNormalizer2 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        // Don't use hydra:Collection in sub levels
        $context['json_ld_sub_level'] = true;
        $this->logger->info('itemNormalizer3 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $this->dunglasResource = $this->getDunglasRessource();
        $this->logger->info('itemNormalizer4 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $data = [];
        $data['@context'] = $this->getContext($this->dunglasResource);
        $this->logger->info('itemNormalizer5 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        if (!$this->resource->getTransformer()->isChild()) {
            $data['@embed'] = implode(',', $this->resource->getTransformer()->getAvailableIncludes());
        }
        $this->logger->info('itemNormalizer6 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        list($rawData, $rawIncludedData) = $this->executeResourceTransformers();
        $this->logger->info('itemNormalizer7 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $data = array_merge($data, $this->serializeResource($serializer, $rawData));
        $this->logger->info('itemNormalizer8 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        // If the serializer wants the includes to be side-loaded then we'll
        // serialize the included data and merge it with the data.
        if ($serializer->sideloadIncludes()) {
            $includedData = $serializer->includedData($this->resource, $rawIncludedData);

            $data = array_merge($data, $includedData);
        }
        $this->logger->info('itemNormalizer9 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        // Pull out all of OUR metadata and any custom meta data to merge with the main level data
        $meta = $serializer->meta($this->resource->getMeta());
        $this->logger->info('itemNormalizer10 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        return array_merge($data, $meta);
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        if (substr($this->scopeIdentifer, -1) === 's') {
            return ucwords(Inflector::singularize($this->scopeIdentifer));
        }

        return ucwords($this->scopeIdentifer);
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
        $this->logger->info('fireTransformer1 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $includedData = [];
        $transformedData = [];

        if (!empty($data)) {
            $transformedData['@id'] = $this->getGenerateRoute($data);
        }
        $this->logger->info('fireTransformer2 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $classMetadata =  $this->manager->getApiClassMetadataFactory()->getMetadataFor(
            $this->dunglasResource->getEntityClass()
        );
        $this->logger->info('fireTransformer3 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        $transformedData['@type'] =  ($iri = $classMetadata->getIri()) ? $iri : $this->getEntityName();
        $this->logger->info('fireTransformer4 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        if (is_callable($transformer)) {
            $transformedData = array_merge($transformedData, call_user_func($transformer, $data));
        } else {
            $transformedData = array_merge($transformedData, $transformer->transform($data));
        }
        $this->logger->info('fireTransformer5 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        if ($this->transformerHasIncludes($transformer)) {
            $includedData = $this->fireIncludedTransformers($transformer, $data);
            // If the serializer does not want the includes to be side-loaded then
            // the included data must be merged with the transformed data.
            if (! $this->manager->getSerializer()->sideloadIncludes()) {
                $transformedData = array_merge($transformedData, $includedData);
            }
        }
        $this->logger->info('fireTransformer6 '.(new \DateTime())->format('Y-m-d H:i:s.u'));
        return array($transformedData, $includedData);
    }

    /**
     * @param $data
     * @param $object
     * @param $currentPage
     * @param $paginatedUrl
     * @param $baseUrl
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
     * @return mixed
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
     * @param Logger $logger
     *
     * @return $this
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

}
