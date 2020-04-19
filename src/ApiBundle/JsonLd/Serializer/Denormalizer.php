<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\JsonLd\Serializer;

use Doctrine\Common\Persistence\ObjectManager;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Eliberty\ApiBundle\Api\ResourceCollectionInterface;
use Eliberty\ApiBundle\Fractal\Pagination\PagerfantaPaginatorAdapter;
use Eliberty\ApiBundle\JsonLd\ContextBuilder;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 */
class Denormalizer extends AbstractNormalizer
{
    /**
     * @var string
     */
    const FORMAT = 'json-ld';
    /**
     * @var ClassMetadataFactoryInterface
     */
    protected $apiClassMetadataFactory;
    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var ContextBuilder
     */
    private $contextBuilder;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @param object $object
     * @param null   $format
     * @param array  $context
     *
     * @return null
     */
    public function normalize($object, $format = null, array $context = array())
    {
        //not use
        return null;
    }

    /**
     * Sets the {@link ClassMetadataFactoryInterface} to use.
     *
     * @param ContextBuilder                $contextBuilder
     * @param ResourceCollectionInterface   $resourceCollection
     * @param PropertyAccessorInterface     $propertyAccessor
     * @param ObjectManager                 $objectManager
     * @param ClassMetadataFactoryInterface $classMetadataFactory
     * @param NameConverterInterface        $nameConverter
     *
     * @internal param ClassMetadataFactoryInterface|null $classMetadataFactory
     * @internal param NameConverterInterface|null $nameConverter
     */
    public function __construct(
        ContextBuilder $contextBuilder,
        ResourceCollectionInterface $resourceCollection,
        PropertyAccessorInterface $propertyAccessor,
        ObjectManager $objectManager,
        ClassMetadataFactoryInterface $classMetadataFactory = null,
        NameConverterInterface $nameConverter = null
    ) {
        parent::__construct($classMetadataFactory, $nameConverter);
        $this->resourceCollection      = $resourceCollection;
        $this->contextBuilder          = $contextBuilder;
        $this->propertyAccessor        = $propertyAccessor;
        $this->objectManager           = $objectManager;
        $this->apiClassMetadataFactory = $classMetadataFactory;
    }

    /**
     * -     * {@inheritdoc}
     * -     *
     * -     * @throws InvalidArgumentException
     * -     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $normalizedData = $this->prepareForDenormalization(json_decode($data));

        $resource = $this->resourceCollection->getResourceForEntity($class);

        $attributesMetadata = $this->getMetadata($resource, $context)->getAttributes();

        $allowedAttributes = $this->getAllowedAttributes($attributesMetadata);

        $reflectionClass = new \ReflectionClass($class);

        $object = $this->instantiateObject(
            $normalizedData,
            $class,
            $context,
            $reflectionClass,
            $allowedAttributes
        );

        if (isset($normalizedData[0])) {
            foreach ($normalizedData as $data) {
                $this->normalizedData($object, (array)$data, $allowedAttributes, $attributesMetadata, $resource);
            }
        } else {
            $this->normalizedData($object, $normalizedData, $allowedAttributes, $attributesMetadata, $resource);
        }

        return $object;
    }


    /**
     * Gets metadata for the given resource with the current context.
     *
     * Fallback to the resource own groups if no context is provided.
     *
     * @param ResourceInterface $resource
     * @param array             $context
     *
     * @return ClassMetadata
     */
    public function getMetadata(ResourceInterface $resource, array $context)
    {
        return $this->apiClassMetadataFactory->getMetadataFor(
            $resource->getEntityClass(),
            isset($context['json_ld_normalization_groups']) ? $context['json_ld_normalization_groups'] : $resource->getNormalizationGroups(
            ),
            isset($context['json_ld_denormalization_groups']) ? $context['json_ld_denormalization_groups'] : $resource->getDenormalizationGroups(
            ),
            isset($context['json_ld_validation_groups']) ? $context['json_ld_validation_groups'] : $resource->getValidationGroups(
            )
        );
    }


    /**
     * @param object|string $attributesMetadata
     * @param array         $context
     * @param bool          $attributesAsString
     *
     * @return array|bool|\string[]|\Symfony\Component\Serializer\Mapping\AttributeMetadataInterface[]
     */
    public function getAllowedAttributes($attributesMetadata, array $context = null, $attributesAsString = false)
    {
        $allowedAttributes = [];
        foreach ($attributesMetadata as $attributeName => $attributeMetatdata) {
            if ($attributeMetatdata->isReadable() && $attributeMetatdata->isRequired()) {
                $allowedAttributes[] = $attributeName;
            }
        }

        return $allowedAttributes;
    }

    /**
     * @param                   $object
     * @param array             $normalizedData
     * @param array             $allowedAttributes
     * @param array             $attributesMetadata
     * @param ResourceInterface $resource
     *
     * @throws \Exception
     */
    protected function normalizedData(
        $object,
        array $normalizedData,
        array $allowedAttributes,
        array $attributesMetadata,
        ResourceInterface $resource
    ) {
        foreach ($normalizedData as $attributeName => $attributeValue) {
            // Ignore JSON-LD special attributes
            if ('@' === $attributeName[0]) {
                continue;
            }
            if ($this->nameConverter) {
                $attributeName = $this->nameConverter->denormalize($attributeName);
            }
            if (!in_array($attributeName, $allowedAttributes) || in_array($attributeName, $this->ignoredAttributes)) {
                continue;
            }
            $attributeMetatdata = $attributesMetadata[$attributeName];
            $types              = $attributeMetatdata->getTypes();
            if (isset($types[0])) {
                $type = $types[0];
                if ($class = $type->getClass()) {
                    if (is_array($attributeValue) || is_object($attributeValue)) {
                        //normalizeDataArrayCollection
                        throw new \InvalidArgumentException('Invalid json message received for : ' . $attributeName);
                    }
                    if ($type->getClass() === 'Datetime') {
                        $this->setValue(
                            $object,
                            $attributeName,
                            $this->denormalizeRelation(
                                $resource,
                                $attributeMetatdata,
                                $class,
                                $attributeValue,
                                $attributeName
                            )
                        );
                        continue;
                    }
                    $id                = $attributeValue;
                    $shortname         = ucfirst(Inflector::singularize($attributeMetatdata->getName()));
                    $attributeResource = $this->resourceCollection->getResourceForShortName($shortname);
                    if (!$attributeResource instanceof ResourceInterface) {
                        throw new \Exception('resource not found for shortname : ' . $shortname);
                    }
                    $attributeValue = $this->objectManager->getRepository($attributeResource->getEntityClass())->find(
                        $id
                    );
                }
            }
            $this->setValue($object, $attributeName, $attributeValue);
        }
    }

    /**
     * @deprecicate
     *
     * @param $attributeValue
     * @param $attributeName
     * @param $object
     * @param $resource
     * @param $attributeMetatdata
     * @param $type
     */
    public function normalizeDataArrayCollection(
        $attributeValue,
        $attributeName,
        $object,
        $resource,
        $attributeMetatdata,
        $type
    ) {
        if (is_array($attributeValue)) {
            $values = new ArrayCollection();
            foreach ($attributeValue as $obj) {
                $values->add($this->denormalizeRelation($resource, $attributeMetatdata, $type->getClass(), $obj));
            }
            $this->setValue($object, $attributeName, $values);
            throw new \InvalidArgumentException('Invalid json message received');
        }
    }

    /**
     * Sets a value of the object using the PropertyAccess component.
     *
     * @param object $object
     * @param string $attributeName
     * @param mixed  $value
     */
    private function setValue($object, $attributeName, $value)
    {
        try {
            $this->propertyAccessor->setValue($object, $attributeName, $value);
            if (is_object($value) && !$value instanceof \DateTime) {
                if (!$value instanceof ArrayCollection) {
                    $resource       = $this->resourceCollection->getResourceForEntity(get_class($value));
                    $validationGrps = $resource->getValidationGroups(Inflector::singularize($attributeName));
                    $this->denormalizingObjects->add(new ObjectDenormalizer($value, $validationGrps));
                }
                foreach ($value as $obj) {
                    $resource       = $this->resourceCollection->getResourceForEntity(get_class($obj));
                    $validationGrps = $resource->getValidationGroups(Inflector::singularize($attributeName));
                    $this->denormalizingObjects->add(new ObjectDenormalizer($obj, $validationGrps));
                }
            }
        } catch (NoSuchPropertyException $exception) {
            // Properties not found are ignored
        }
    }


    /**
     * Denormalizes a relation.
     *
     * @param ResourceInterface $currentResource
     * @param AttributeMetadata $attributeMetadata
     * @param                   $class
     * @param                   $value
     *
     * @return object
     * @throws InvalidArgumentException
     */
    private function denormalizeRelation(
        ResourceInterface $currentResource,
        AttributeMetadata $attributeMetadata,
        $class,
        $value
    ) {
        if ('Datetime' === $class) {
            $dateTimeNormalizer = new DateTimeNormalizer();

            return $dateTimeNormalizer->denormalize($value, $class ?: null, self::FORMAT);
        }
        $attributeName = $attributeMetadata->getName();
//        // Always allow IRI to be compliant with the Hydra spec
//        if (is_string($value)) {
//            $item = $this->dataProvider->getItemFromIri($value);
//            if (null === $item) {
//                throw new InvalidArgumentException(sprintf(
//                    'IRI  not supported (found "%s" in "%s" of "%s")',
//                    $value,
//                    $attributeName,
//                    $currentResource->getEntityClass()
//                ));
//            }
//            return $item;
//        }
        if (!$this->resourceCollection->getResourceForEntity($class)) {
            $shortname = ucfirst(Inflector::singularize($attributeName));
            if (!$this->resourceCollection->getResourceForShortName($shortname)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Type not supported (found "%s" in attribute "%s" of "%s")',
                        $class,
                        $attributeName,
                        $currentResource->getEntityClass()
                    )
                );
            } else {
                $resource = $this->resourceCollection->getResourceForShortName($shortname);
                $context  = $this->contextBuilder->bootstrapRelation($resource, $resource->getEntityClass());
            }
        } else {
            $context = $this->contextBuilder->bootstrapRelation($currentResource, $class);
        }
        if (!$attributeMetadata->isDenormalizationLink()) {
            $object = $this->denormalize(json_encode($value), $resource->getEntityClass(), self::FORMAT, $context);

            //$this->deserializeObjects[] = $object;
            return $object;
        }
        throw new InvalidArgumentException(
            sprintf(
                'Nested objects for attribute "%s" of "%s" are not enabled. Use serialization groups to change that behavior.',
                $attributeName,
                $currentResource->getEntityClass()
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return self::FORMAT === $format;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return self::FORMAT === $format && (is_object($data) || is_array($data));
    }

}