<?php
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Api\Filter\FilterInterface;
use Dunglas\ApiBundle\Api\Operation\OperationInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;

/**
 * Class Resource
 * @package Eliberty\ApiBundle\Api
 */
class ResourceConfig implements ResourceConfigInterface
{
    /**
     * @var array
     */
    protected $alias = [];

    /**
     * @var ResourceInterface
     */
    protected $resourceParent;

    /**
     * @var string
     */
    protected $shortname;

    /**
     * @var string
     */
    protected $parentName;

    /**
     * @var []
     */
    private $routeKeyParams;

    /**
     * @var []
     * key (shortname => string, parentName => string, routerKey => [], 'embeds' => []')
     */
    private $options;

    /**
     * @var []
     */
    private $embeds;


    /**
     * @var array
     */
    protected $listener = [];

    /**
     * @param array $alias
     * @param ResourceInterface $resourceParent
     * @param null $options
     */
    public function __construct(
        $alias = [],
        ResourceInterface $resourceParent = null,
        $options = null
    ) {
        $this->alias = $alias;
        $this->setOptions($options);
        $this->setResourceParent($resourceParent);
    }

    /**
     * @param mixed $options
     *
     * @return $this
     */
    public function setOptions($options)
    {
        $this->options = $options;

        if (isset($this->options['shortname'])) {
            $this->shortname = $this->options['shortname'];
        }

        if (isset($this->options['parentName'])) {
            $this->parentName = $this->options['parentName'];
        }

        if (isset($this->options['routerKey'])) {
            $this->routeKeyParams = $this->options['routerKey'];
        }

        if (isset($this->options['listener'])) {
            $this->listener = $this->options['listener'];
        }

        if (isset($this->options['embeds'])) {
            $this->embeds = $this->options['embeds'];
        }

        if (isset($this->options['identifier'])) {
            $this->identifier = $this->options['identifier'];
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getListener()
    {
        return $this->listener;
    }

    /**
     * @param ResourceInterface $resourceParent
     *
     * @return $this
     */
    public function setResourceParent($resourceParent)
    {

        $this->resourceParent = $resourceParent;

        if (null !== $this->resourceParent && null === $this->parentName) {
            $this->parentName = $this->resourceParent->getShortName();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * {@inheritdoc}
     */
    public function getResourceParent()
    {
        return $this->resourceParent;
    }

    /**
     * {@inheritdoc}
     */
    public function getShortname()
    {
        return $this->shortname;
    }

    /**
     * @return mixed
     */
    public function getRouteKeyParams()
    {
        return $this->routeKeyParams;
    }

    /**
     * @param $alias
     * @return null
     */
    public function getEmbedAlias($alias) {
        if (isset($this->embeds[$alias])) {
            return $this->embeds[$alias];
        }
        return null;
    }

    /**
     * @return string
     */
    public function getIdentifier() {
        if (isset($this->options['identifier'])) {
            return $this->options['identifier'];
        }
        return 'id';
    }
}
