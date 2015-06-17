<?php

/*
 * This file is part of the ElibertyBundle package.
 *
 * (c) philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm;


use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;

use Doctrine\Common\Persistence\ManagerRegistry;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Class EmbedFilter
 * @package Eliberty\ApiBundle\Doctrine\Orm
 */
class EmbedFilter extends SearchFilter
{
    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var []
     */
    protected $parameters;

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $paramters
     * @return $this
     */
    public function setParameters($paramters = [])
    {
        $this->parameters = $paramters;

        return $this;
    }

    /**
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * @param string $routeName
     *
     * @return $this
     */
    public function setRouteName($routeName)
    {
        $this->routeName = $routeName;

        return $this;
    }

    /**
     * @return string
     */
    public function getEmbedName()
    {
        return isset($this->parameters['embed'])?$this->parameters['embed']:'';
    }

    /**
     * @return array|null
     */
    public function getProperties()
    {
        $property = parent::getProperties();

        return !is_null($property) ? $this->properties : [];
    }
}
