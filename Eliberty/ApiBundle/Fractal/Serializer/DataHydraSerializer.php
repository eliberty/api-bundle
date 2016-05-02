<?php

namespace Eliberty\ApiBundle\Fractal\Serializer;

use Doctrine\ORM\PersistentCollection;
use League\Fractal\Manager;
use Dunglas\ApiBundle\Model\PaginatorInterface;;
use League\Fractal\Serializer\DataArraySerializer as BaseDataArraySerializer;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use League\Fractal\Pagination\PaginatorInterface as FractalPaginatorInterface;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Resource\Collection;
use Dunglas\ApiBundle\Api\ResourceInterface as DunglasResource;

/**
 * Class DataArraySerializer
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class DataHydraSerializer extends BaseDataArraySerializer
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

        return ['hydra:member' => $data];
    }

    /**
     * Serialize the meta.
     *
     * @param array $meta
     *
     * @return array
     */
    public function meta(array $meta)
    {
        $hydra['@context'] = $this->getContext();

        if (!$this->getResource()->getTransformer()->isChild()) {
            $hydra['@embed'] = implode(',', $this->getResource()->getTransformer()->getAvailableIncludes());
        }

        $object = $this->getResource()->getData();

        if($object instanceof Collection) {
            $hydra['@id'] = $this->getResourceRoute();
        }

        if ($object instanceof PaginatorInterface || $object instanceof FractalPaginatorInterface) {
            $hydra['@type'] = self::HYDRA_PAGED_COLLECTION;

            $currentPage = (int) $object->getCurrentPage();
            $lastPage    = (int) $object->getLastPage();

            $baseUrl = $hydra['@id'];
            $paginatedUrl = $baseUrl.'?perpage='.$object->getItemsPerPage().'&page=';

            $this->getPreviewPage($hydra, $currentPage, $paginatedUrl, $baseUrl);
            $this->getNextPage($hydra, $currentPage, $lastPage, $paginatedUrl);

            $hydra['hydra:totalItems'] =  $object->getTotalItems();
            $hydra['hydra:itemsPerPage'] = $object->getItemsPerPage();
            $this->getFirstPage($hydra, $object, $currentPage, $lastPage, $baseUrl);
            $this->getLastPage($hydra, $object, $currentPage, $lastPage, $paginatedUrl);
        } else if ($object instanceof PersistentCollection) {
            $hydra['@type'] = self::HYDRA_COLLECTION;
        }

        return $hydra;
    }

    /**
     * @return mixed|null
     */
    protected function getResourceRoute() {
        if (!is_null($this->getResource())) {
            foreach ($this->getDunglasResource()->getFilters() as $filter) {
                if ($filter instanceof EmbedFilter) {
                    $route = $this->getGenerateRoute($filter->getRouteName(), $filter->getParameters());
                    break;
                }
            }
            if (empty($route)) {
                $parameters = [];
                if ($this->getDunglasResource()->getParent() instanceof DunglasResource) {
                    $dunglasParentResource = $this->getDunglasResource()->getParent();
                    $parameters = $dunglasParentResource->getRouteKeyParams($this->getParent()->getData());
                }
                try {
                    $route = $this->getGenerateRoute($this->getDunglasResource(), $parameters);
                } catch (\Exception $e) {
                    $route = null;
                }
            }

            return $route;
        }

        return null;
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
        return $this->scope->getGenerateRoute($data, $params);
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
     * @param $currentPage
     * @param $paginatedUrl
     * @param $baseUrl
     */
    protected function getPreviewPage(&$data, $currentPage, $paginatedUrl, $baseUrl)
    {
        if (0 !== ($currentPage - 1)) {
            $previousPage = $currentPage - 1.;
            $data['hydra:previousPage'] = 1 === $previousPage ? $baseUrl : $paginatedUrl.$previousPage;
        }
    }

    /**
     * @param $data
     * @param $currentPage
     * @param $lastPage
     * @param $paginatedUrl
     */
    protected function getNextPage(&$data, $currentPage, $lastPage, $paginatedUrl)
    {
        if ($currentPage !== $lastPage) {
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
