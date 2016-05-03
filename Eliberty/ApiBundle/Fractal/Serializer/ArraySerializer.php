<?php

namespace Eliberty\ApiBundle\Fractal\Serializer;

use Eliberty\ApiBundle\Fractal\Scope;
use League\Fractal\Pagination\PaginatorInterface;
use League\Fractal\Serializer\ArraySerializer as BaseArraySerializer;
use League\Fractal\Resource\ResourceAbstract;
/**
 * Class ArraySerializer
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class ArraySerializer extends BaseArraySerializer implements SerializerInterface
{

    /**
     * @var Scope;
     */
    protected $scope;

    /**
     * @param Scope $scope
     *
     * @return $this
     */
    public function setScope(Scope $scope)
    {
        $this->scope = $scope;

        return $this;
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
        if (!$this->scope->hasParent()) {
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