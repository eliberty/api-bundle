<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrineOrmPaginator;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\FilterInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use Dunglas\ApiBundle\Model\DataProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Data provider for the Doctrine ORM.
 *
 * @author Vesin Philippe <pvesin@eliberty.fr>
 */
class DataProvider implements DataProviderInterface
{
    /**
     * @var array
     */
    protected $dataProvider= [];

    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;
    /**
     * @var string|null
     */
    protected $order;
    /**
     * @var string
     */
    protected $pageParameter;
    /**
     * @var int
     */
    protected $itemsPerPage;
    /**
     * @var bool
     */
    protected $enableClientRequestItemsPerPage;
    /**
     * @var string
     */
    protected $itemsPerPageParameter;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param string|null     $order
     * @param string          $pageParameter
     * @param int             $itemsPerPage
     * @param bool            $enableClientRequestItemsPerPage
     * @param string          $itemsPerPageParameter
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        $order,
        $pageParameter,
        $itemsPerPage,
        $enableClientRequestItemsPerPage,
        $itemsPerPageParameter
    ) {
        $this->managerRegistry = $managerRegistry;
        $this->order = $order;
        $this->pageParameter = $pageParameter;
        $this->itemsPerPage = $itemsPerPage;
        $this->enableClientRequestItemsPerPage = $enableClientRequestItemsPerPage;
        $this->itemsPerPageParameter = $itemsPerPageParameter;
    }

    /**
     * {@inheritdoc}
     */
    public function getItem(ResourceInterface $resource, $id, $fetchData = false)
    {
        $entityClass = $resource->getEntityClass();
        $manager = $this->managerRegistry->getManagerForClass($entityClass);

        if ($fetchData || !method_exists($manager, 'getReference')) {
            return $manager->find($entityClass, $id);
        }

        return $manager->getReference($entityClass, $id);
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(ResourceInterface $resource, Request $request)
    {
        $queryBuilder = $this->getQueryBuilderForCollection($resource, $request);

        return new Paginator(new DoctrineOrmPaginator($queryBuilder));
    }

    /**
     * created querybuilder
     *
     * @param ResourceInterface $resource
     * @param Request $request
     * @return mixed
     */
    public function getQueryBuilderForCollection(ResourceInterface $resource, Request $request)
    {
        $entityClass = $resource->getEntityClass();

        $manager = $this->managerRegistry->getManagerForClass($resource->getEntityClass());
        $repository = $manager->getRepository($entityClass);

        $page = (int) $request->get($this->pageParameter, 1);

        $itemsPerPage = $this->getItemPerPage($request);

        $queryBuilder = $this->getQB($request, $repository, $page, $itemsPerPage);

//        $queryBuilder->addOrderBy('o.id', $this->order);

        foreach ($resource->getFilters() as $filter) {
            if ($filter instanceof FilterInterface) {
                $filter->apply($resource, $queryBuilder, $request);
            }
        }

        return $queryBuilder;
    }

    /**
     * @param Request $request
     * @param EntityRepository $repository
     * @param $page
     * @param $itemsPerPage
     * @return \Doctrine\ORM\QueryBuilder
     */
    protected function getQB(Request $request, EntityRepository $repository, $page, $itemsPerPage) {
        return $repository
            ->createQueryBuilder('o')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);
    }

    /**
     * {@inheritdoc}
     */
    public function supports(ResourceInterface $resource)
    {
        foreach ($this->dataProvider as $dataProvider) {
            if ($dataProvider->supports($resource)) {
                return false;
            }
        }
        return null !== $this->managerRegistry->getManagerForClass($resource->getEntityClass());
    }

    /**
     * @param Request $request
     * @return int
     */
    protected function getItemPerPage(Request $request) {
        $itemsPerPage = $this->itemsPerPage;
        if ($this->enableClientRequestItemsPerPage && $requestedItemsPerPage = $request->get($this->itemsPerPageParameter)) {
            $itemsPerPage = (int) $requestedItemsPerPage;
            if ($itemsPerPage > 500) {
                $itemsPerPage = $this->itemsPerPage;
            }
        }

        return $itemsPerPage;
    }

    /**
     * @param $dataProvider
     */
    public function addDataProvider($dataProvider)
    {
        $this->dataProvider[] = $dataProvider;
    }
}
