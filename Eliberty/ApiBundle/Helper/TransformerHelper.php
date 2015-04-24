<?php


namespace Eliberty\ApiBundle\Helper;

use Dunglas\ApiBundle\Api\Resource;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Mapping\AttributeMetadata;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Eliberty\ApiBundle\Transformer\BaseTransformer;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;

/**
 * Class TransformerHelper
 * @package Eliberty\ApiBundle\Helper
 */
class TransformerHelper
{
    /**
     * @var TransformerResolver
     */
    private $transformerResolver;

    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * @var BaseTransformer
     */
    protected $transformer;

    /**
     * @param TransformerResolver $transformerResolver
     * @param ClassMetadataFactory $classMetadataFactory
     */
    public function __construct(TransformerResolver $transformerResolver, ClassMetadataFactory $classMetadataFactory)
    {
        $this->transformerResolver  = $transformerResolver;
        $this->classMetadataFactory = $classMetadataFactory;
    }

    /**
     * @param $entityname
     * @return BaseTransformer
     * @throws \Exception
     */
    public function getTransformer($entityname)
    {
        if (!$this->transformer) {
            $this->transformer = $this->transformerResolver->resolve($entityname);
        }

        return $this->transformer;
    }

    /**
     * @param null $shortname
     * @throws \Exception
     * @return string
     */
    public function getEntityClass($shortname = null)
    {
        if (!$this->transformer) {
            if (null === $shortname) {
                throw new \Exception('transformer is empty, specify the shortname into the parameter');
            }
            $this->getTransformer($shortname);
        }

        return $this->transformer->getEntityClass();
    }

    /**
     * @param null $shortname
     * @throws \Exception
     * @return string
     */
    public function getTransformerClass($shortname = null)
    {
        if (!$this->transformer) {
            if (null === $shortname) {
                throw new \Exception('transformer is empty, specify the shortname into the parameter');
            }
            $this->getTransformer($shortname);
        }

        return get_class($this->transformer);
    }

    /**
     * @param null $shortname
     * @throws \Exception
     * @return
     */
    public function getAttribute($shortname = null)
    {
        if (!$this->transformer) {
            if (null === $shortname) {
                throw new \Exception('transformer is empty, specify the shortname into the parameter');
            }
            $this->getTransformer($shortname);
        }
        $class = $this->getEntityClass();

        $entity = new $class();

        return $this->transformer->transform($entity);

    }

    /**
     * @param ResourceInterface $resource
     * @return \Dunglas\ApiBundle\Mapping\AttributeMetadata[]
     * @throws \Exception
     */
    public function getTransformerAttributes(ResourceInterface $resource = null)
    {
        if (!$this->transformer) {
            if (null === $resource) {
                throw new \Exception('transformer is empty, specify the resource into the parameter');
            }
            $this->getTransformer($resource->getShortName());
        }

        $resource = new Resource($this->getEntityClass());

        $attributes = $this->classMetadataFactory->getMetadataFor(
            $this->getTransformerClass(),
            $resource->getNormalizationGroups(),
            $resource->getDenormalizationGroups(),
            $resource->getValidationGroups()
        )->getAttributes();

        return $attributes;
    }

    /**
     * @param $shortname
     * @param $data
     * @param string $type
     * @return array
     */
    public function getOutputAttr($shortname, &$data, $type = 'context')
    {

        $attributes             = $this->getAttribute($shortname);
        $transformerAttributes = $this->getTransformerAttributes();

        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $attributes) && !in_array($key, ['@vocab', 'hydra'])) {
                unset($data[$key]);
            }
        }

        foreach ($attributes as $attributeName => $value) {
            if (array_key_exists($attributeName, $transformerAttributes)) {
                $attribute = $transformerAttributes[$attributeName];
                $data[$attributeName] = $this->addAttribute($attribute, $shortname, $type);
            }
        }

        $this->transformer = null;

        return $data;
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getClassMetadataFactory()
    {
        return $this->classMetadataFactory;
    }

    /**
     * @param AttributeMetadata $attribute
     * @param $entityName
     * @param $type
     * @return array|null|string
     */
    protected function addAttribute(AttributeMetadata $attribute, $entityName, $type)
    {
        if ($type === 'context') {
            if (!$id = $attribute->getIri()) {
                $prefixedShortName = sprintf('#%s', $entityName);
                $id                = sprintf('%s/%s', $prefixedShortName, $attribute->getName());
            }

            return $id;
        }

        $propertyInfo = array_shift($attribute->getTypes());
        $type = $propertyInfo->getType();

        return [
            'dataType'     => $type,
            'actualType'   => $type,
            'subType'      => null,
            'required'     => $attribute->isRequired(),
            'default'      => null,
            'description'  => $attribute->getDescription(),
            'readonly'     => !$attribute->isWritable(),
            'sinceVersion' => null,
            'untilVersion' => null
        ];
    }
}