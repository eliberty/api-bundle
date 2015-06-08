<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm;

use Doctrine\ORM\Tools\Pagination\Paginator as DoctrineOrmPaginator;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use Dunglas\ApiBundle\Model\PaginatorInterface;
use Pagerfanta\Adapter\ArrayAdapter;

/**
 * Decorates the Array Pagerfanta paginator.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ArrayPaginator extends Paginator
{
    /**
     * @var ArrayPaginator
     */
    private $paginator;

    /**
     * @var int
     */
    private $firstResult;
    /**
     * @var int
     */
    private $maxResults;
    /**
     * @var int
     */
    private $totalItems;

    public function __construct(ArrayAdapter $paginator)
    {
        $this->paginator = $paginator->getArray();

        $this->firstResult = 0 ;
        $this->maxResults = count( $this->paginator);
        $this->totalItems = count( $this->paginator);
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentPage()
    {
        return floor($this->firstResult / $this->maxResults) + 1.;
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPage()
    {
        return ceil($this->totalItems / $this->maxResults) ?: 1.;
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsPerPage()
    {
        return (float) $this->maxResults;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalItems()
    {
        return (float) $this->totalItems;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return (new \ArrayObject($this->paginator))->getIterator();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->getIterator());
    }
}
