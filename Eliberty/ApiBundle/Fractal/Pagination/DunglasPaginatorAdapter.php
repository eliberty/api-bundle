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
use Doctrine\ORM\Tools\Pagination\Paginator;
/**
 * A paginator adapter for Doctrine.
 *
 * @author Vesin Philippe <pvesin@eliberty.fr>
 */
class DoctrinePaginatorAdapter implements PaginatorInterface
{
    /**
     * The paginator instance.
     *
     * @var Paginator
     */
    protected $paginator;

    /**
     * The route generator.
     *
     * @var callable
     */
    protected $routeGenerator;

    /**
     * Create a new doctrine pagination adapter.
     *
     * DoctrinePaginatorAdapter constructor.
     *
     * @param Paginator $paginator
     * @param           $routeGenerator
     */
    public function __construct(Paginator $paginator, $routeGenerator = null)
    {
        $this->paginator      = $paginator;
        $this->routeGenerator = $routeGenerator;
    }

    /**
     * Get the current page.
     *
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->paginator->getCurrentPage();
    }

    /**
     * Get the last page.
     *
     * @return int
     */
    public function getLastPage()
    {
        return $this->paginator->getNbPages();
    }

    /**
     * Get the total.
     *
     * @return int
     */
    public function getTotal()
    {
        return count($this->paginator);
    }

    /**
     * Get the count.
     *
     * @return int
     */
    public function getCount()
    {
        return count($this->paginator->getCurrentPageResults());
    }

    /**
     * Get the number per page.
     *
     * @return int
     */
    public function getPerPage()
    {
        return $this->paginator->getMaxPerPage();
    }

    /**
     * Get the url for the given page.
     *
     * @param int $page
     *
     * @return string
     */
    public function getUrl($page)
    {
        return null; //call_user_func($this->routeGenerator, $page);
    }

    /**
     * Get the paginator instance.
     *
     * @return \Pagerfanta\Pagerfanta
     */
    public function getPaginator()
    {
        return $this->paginator;
    }

    /**
     * Get the the route generator.
     *
     * @return callable
     */
    public function getRouteGenerator()
    {
        return $this->routeGenerator;
    }

    /**
     * Get the total items.
     *
     * @return int
     */
    public function getTotalItems()
    {
        return $this->getTotal();
    }

    /**
     * Get the total items.
     *
     * @return int
     */
    public function getItemsPerPage()
    {
        return $this->getPerPage();
    }


}
