<?php

namespace Eliberty\ApiBundle\Annotation;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\ConfigurationInterface;

/**
 * Class PropertyDescription
 * @Annotation
 * @package Eliberty\ApiBundle\Annotation
 */
class PropertyDescription implements ConfigurationInterface
{

    /**
     * @var array
     */
    protected $property = [];

    /**
     * AnnotationDescription constructor.
     *
     * @param $data
     */
    public function __construct($data)
    {
        $this->setProperty($data['value']);
    }

    /**
     * @return array
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param array $property
     *
     * @return $this
     */
    public function setProperty($property)
    {
        $this->property = $property;

        return $this;
    }

    public function getAliasName()
    {
        return 'proterty_desc';
    }

    public function allowArray()
    {
        return true;
    }
}
