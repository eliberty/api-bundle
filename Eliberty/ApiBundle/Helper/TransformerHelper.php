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
     */
    public function __construct(TransformerResolver $transformerResolver)
    {
        $this->transformerResolver  = $transformerResolver;
    }

    /**
     * @param ClassMetadataFactory $classMetadataFactory
     *
     * @return $this
     */
    public function setClassMetadataFactory(ClassMetadataFactory $classMetadataFactory)
    {
        $this->classMetadataFactory = $classMetadataFactory;

        return $this;
    }

    /**
     * @param $entityname
     * @return BaseTransformer
     * @throws \Exception
     */
    public function getTransformer($entityname = null)
    {
        if (!$this->transformer || ($entityname !== null && $this->transformer->getCurrentResourceKey() !== $entityname)) {
            if (null === $entityname) {
                throw new \Exception('transformer is empty, specify the entity name into the parameter');
            }
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
        $transformer = $this->getTransformer($shortname);

        return $transformer->getEntityClass();
    }

    /**
     * @param null $shortname
     * @throws \Exception
     * @return string
     */
    public function getTransformerClass($shortname = null)
    {
        $transformer = $this->getTransformer($shortname);

        return get_class($transformer);
    }


    /**
     * @param null $shortname
     * @throws \Exception
     * @return
     */
    public function getAttribute($shortname = null)
    {
        $class = $this->getEntityClass($shortname);

        $entity = new $class();

        $transformer = $this->getTransformer($shortname);

        return $transformer->transform($entity);

    }

    /**
     * @param ResourceInterface $resource
     * @return \Dunglas\ApiBundle\Mapping\AttributeMetadata[]
     * @throws \Exception
     */
    protected function getTransformerAttributes(ResourceInterface $resource = null)
    {

        $shortname = (null !== $resource) ? $resource->getShortName(): null;

        $resource = new Resource($this->getEntityClass($shortname));

        $attributes = $this->classMetadataFactory->getMetadataFor(
            $this->getTransformerClass($shortname),
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
     * List of resources possible to embed via this processor.
     * @param null $shortname
     * @return array
     * @throws \Exception
     */
    public function getAvailableIncludes($shortname = null)
    {
        $transformer = $this->getTransformer($shortname);

        return $transformer->getAvailableIncludes();
    }

    /**
     * List of resources to automatically include.
     * @param null $shortname
     * @return array
     * @throws \Exception
     */
    public function getDefaultIncludes($shortname = null)
    {
        $transformer = $this->getTransformer($shortname);

        return $transformer->getDefaultIncludes();
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