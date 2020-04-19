<?php

/*
 * This file is part of the League\Fractal package.
 *
 * (c) Phil Sturgeon <me@philsturgeon.uk>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Fractal\Pagination;

use League\Fractal\Pagination\PaginatorInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use League\Fractal\Resource\Collection;
/**
 * A paginator adapter for Doctrine.
 *
 * @author Vesin Philippe <pvesin@eliberty.fr>
 */
class DunglasPaginatorAdapter implements PaginatorInterface
{
    /**
     * @var Paginator
     */
    protected $paginator;
    /**
     * @var Collection
     */
    private $resource;

    /**
     * DunglasPaginatorAdapter constructor.
     *
     * @param Paginator  $paginator
     * @param Collection $resource
     */
    public function __construct(Paginator $paginator, Collection $resource)
    {
        $this->paginator = $paginator;
        $this->resource  = $resource;
    }

    /**
     * Get the current page.
     *
     * @return int
     */
    public function getCurrentPage(){
        return $this->paginator->getCurrentPage();
    }

    /**
     * Get the last page.
     *
     * @return int
     */
    public function getLastPage(){
        return $this->paginator->getLastPage();
    }

    /**
     * Get the total.
     *
     * @return int
     */
    public function getTotal(){
        return $this->paginator->getTotalItems();
    }

    /**
     * Get the count.
     *
     * @return int
     */
    public function getCount(){
        return count($this->paginator->getIterator());
    }

    /**
     * Get the number per page.
     *
     * @return int
     */
    public function getPerPage(){
        return $this->paginator->getItemsPerPage();
    }

    /**
     * Get the url for the given page.
     *
     * @param int $page
     *
     * @return string
     */
    public function getUrl($page){
        return null;
    }
}
