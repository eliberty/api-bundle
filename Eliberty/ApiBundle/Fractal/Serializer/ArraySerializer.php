<?php

namespace Eliberty\ApiBundle\Fractal\Serializer;

use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Serializer\ArraySerializer as BaseArraySerializer;

/**
 * Class ArraySerializer
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class ArraySerializer extends BaseArraySerializer
{
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
        if (null !== $resourceKey) {
            return ['data' => $data];
        }

        return $data;
    }

    /**
     * Serialize the paginator.
     *
     * @param PaginatorInterface $paginator
     *
     * @return array
     */
    public function paginator(PaginatorInterface $paginator)
    {
        $data = parent::paginator($paginator);
        unset($data['pagination']['links']);

        return $data;
    }
}