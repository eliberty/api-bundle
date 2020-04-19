<?php

namespace Eliberty\ApiBundle\Fractal\Serializer;

use Doctrine\Common\Util\Inflector;
use Eliberty\ApiBundle\Fractal\Scope;

/**
 * Class DataXmlSerializer
 *
 * @package Eliberty\ApiBundle\Fractal\Serializer
 */
class DataXmlSerializer extends ArraySerializer implements SerializerInterface
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
        $dataResponse             = [];
        $resourceKey              = strtolower($resourceKey);
        $pluralize                = Inflector::pluralize($resourceKey);
        $singularize              = Inflector::singularize($resourceKey);
        if (!$this->scope->hasParent()) {
            $dataResponse[$pluralize] = [$singularize => $data];
        } else {
            $dataResponse = [$singularize => $data];
        }

        return $dataResponse;
    }

}