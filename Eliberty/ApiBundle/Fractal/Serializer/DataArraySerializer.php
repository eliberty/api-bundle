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
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Serializer\DataArraySerializer as baseDataArraySerializer;

/**
 * Class DataArraySerializer
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class DataArraySerializer extends baseDataArraySerializer
{
    /**
     * Serialize an item.
     *
     * @param string $resourceKey
     * @param array $data
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
     * @param array $data
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
}
