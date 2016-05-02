<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Fractal\Serializer;

use Dunglas\ApiBundle\Routing\Router;
use League\Fractal\Manager;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Serializer\DataArraySerializer as baseDataArraySerializer;
use Doctrine\Common\Inflector\Inflector;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use League\Fractal\Pagination\PaginatorInterface as FractalPaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Scope as BaseFractalScope;
use League\Fractal\Resource\Collection;
use Dunglas\ApiBundle\Api\ResourceInterface as DunglasResource;
use League\Fractal\TransformerAbstract;

/**
 * Class DataArraySerializer
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class DataArraySerializer extends baseDataArraySerializer
{

    /**
     * @var string
     */
    const HYDRA_COLLECTION = 'hydra:Collection';
    /**
     * @var string
     */
    const HYDRA_PAGED_COLLECTION = 'hydra:PagedCollection';

    /**
     * @var \Eliberty\ApiBundle\Fractal\Scope;
     */
    protected $scope;

    /**
     * @param \Eliberty\ApiBundle\Fractal\Scope $scope
     *
     * @return $this
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function item($resourceKey, array $data)
    {
        $data = [];
        if ($this->getDunglasResource() instanceof DunglasResource) {
            $data['@context'] = $this->getContext($this->getDunglasResource());
        }

        if (!$this->getResource()->getTransformer()->isChild()) {
            $data['@embed'] = implode(',', $this->getResource()->getTransformer()->getAvailableIncludes());
        }

        return $data;
    }

    /**
     * Serialize a collection.
     *
     * @param string $resourceKey
     * @param array  $data
     *
     * @return array
     */
    public function collection($resourceKey, array $data)
    {

        if (empty($data)) {
            return [];
        }

        $data['@context'] = $this->getContext();

        if (!$this->getResource()->getTransformer()->isChild()) {
            $data['@embed'] = implode(',', $this->getResource()->getTransformer()->getAvailableIncludes());
        }

        if (!is_null($this->getResource())) {
            foreach ($this->getDunglasResource()->getFilters() as $filter) {
                if ($filter instanceof EmbedFilter) {
                    $route = $this->getGenerateRoute($filter->getRouteName(), $filter->getParameters());
                    break;
                }
            }
            if (empty($route)) {
                $parameters = [];
                if (
                    $this->getDunglasResource()->getParent() instanceof DunglasResource
                ) {
                    $dunglasParentResource = $this->getDunglasResource()->getParent();
                    $parameters = $dunglasParentResource->getRouteKeyParams($this->getParent()->getData());
                }
                try {
                    $route = $this->getGenerateRoute($this->getDunglasResource(), $parameters);
                } catch (\Exception $e) {
                    $route = null;
                }
            }
            $data['@id'] = $route;
        }

        $object = $this->getResource()->getData();

        if ($this->getResource()->hasPaginator()) {
            $object = $this->getResource()->getPaginator();
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

        return ['hydra:member' => $data];
    }


    /**
     * @return mixed
     */
    protected function getContext()
    {
        return $this->getManager()->getRouter()->generate(
            'api_json_ld_context',
            ['shortName' => $this->getDunglasResource()->getShortName()]
        );
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function getGenerateRoute($data, $params = [])
    {
        $this->getManager()->getRouter()->setScope($this);

        return $this->getManager()->getRouter()->generate($data, $params);
    }

    /**
     * @return DunglasResource
     */
    public function getDunglasResource()
    {
        return $this->scope->getDunglasResource();
    }

    /**
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->scope->getResource();
    }

    /**
     * @return Manager
     */
    public function getManager()
    {
        return $this->scope->getManager();
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
}
