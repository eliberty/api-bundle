<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\DateFilter as BaseDateFilter;
/**
 * Filters the collection by date intervals.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class DateFilter extends BaseDateFilter
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
