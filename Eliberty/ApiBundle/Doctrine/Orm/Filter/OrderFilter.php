<?php

/*
 * This file is part of the ElibertyBundle package.
 *
 * (c) philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;


use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\OrderFilter as BaseOrderFilter;
use Doctrine\Common\Persistence\ManagerRegistry;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Order by the collection by given properties.
 *
 * @author philippe Vesin <pvesin@eliberty.fr>
 */
class OrderFilter extends BaseOrderFilter
{
    /**
     * @param Request $request
     * @return array|mixed
     */
    public function getRequestProperties(Request $request)
    {
        return $this->extractProperties($request);
    }
}
