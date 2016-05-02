<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm;

use Doctrine\ORM\Tools\Pagination\Paginator as DoctrineOrmPaginator;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator as BasePaginator;
/**
 * Decorates the Doctrine ORM paginator.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class Paginator extends BasePaginator
{
    /**
     * @var DoctrineOrmPaginator
     */
    private $paginator;

    public function __construct(DoctrineOrmPaginator $paginator)
    {
        $this->paginator = $paginator;
        parent::__construct($paginator);
    }

    /**
     * @return DoctrineOrmPaginator
     */
    public function getPaginator()
    {
        return $this->paginator;
    }
}