<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm;

use Dunglas\ApiBundle\Doctrine\Orm\Filter;

/**
 * Class EmbedFilter
 * @package Eliberty\ApiBundle\Doctrine\Orm
 */
class EmbedFilter extends Filter
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


}
