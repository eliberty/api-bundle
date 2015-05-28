<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\JsonLd\Serializer;

/**
 * Converts between objects
 *
 * @package Eliberty\ApiBundle\JsonLd\Serializer
 */
class ObjectDenormalizer
{
    /**
     * @var object
     */
    protected $object;

    /**
     * @var array
     */
    protected $validationGroups = [];

    /**
     * @param $object
     * @param $validationGroups
     */
    public function __construct($object, $validationGroups)
    {
        $this->validationGroups = $validationGroups;
        $this->object = $object;
    }

    /**
     * @return mixed
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param mixed $object
     *
     * @return $this
     */
    public function setObject($object)
    {
        $this->object = $object;

        return $this;
    }

    /**
     * @return array
     */
    public function getValidationGroups()
    {
        return $this->validationGroups;
    }

    /**
     * @param array $validationGroups
     *
     * @return $this
     */
    public function setValidationGroups($validationGroups)
    {
        $this->validationGroups = $validationGroups;

        return $this;
    }


}