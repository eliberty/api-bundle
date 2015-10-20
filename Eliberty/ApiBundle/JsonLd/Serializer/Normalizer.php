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

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Util\Inflector;
use Dunglas\ApiBundle\JsonLd\Serializer\DateTimeNormalizer;
use Dunglas\ApiBundle\Mapping\Loader\ValidatorMetadataLoader;
use Eliberty\ApiBundle\Api\Resource;
use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Api\ResourceResolverTrait;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Dunglas\ApiBundle\Mapping\AttributeMetadata;
use Dunglas\ApiBundle\Model\DataProviderInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\TransformerAbstract;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Dunglas\ApiBundle\Api\ResourceCollection;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 */
class Normalizer extends AbstractNormalizer
{
    use ResourceResolverTrait;

    /**
     * @var string
     */
    const FORMAT = 'json-ld';

    /**
     * @var DataProviderInterface
     */
    private $dataProvider;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var ClassMetadataFactory
     */
    private $apiClassMetadataFactory;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;
    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

    /**
     * @var Manager
     */
    private $fractal;

    /**
     * @var TransformerAbstract
     */
    private $transformer;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var TransformerHelper
     */
    private $transformerHelper;

    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * List of denormalizing object.
     *
     * @var ArrayCollection
     */
    private $denormalizingObjects;


    /**
     * @param ResourceCollectionInterface $resourceCollection
     * @param DataProviderInterface       $dataProvider
     * @param RouterInterface             $router
     * @param ClassMetadataFactory        $apiClassMetadataFactory
     * @param ContextBuilder              $contextBuilder
     * @param PropertyAccessorInterface   $propertyAccessor
     * @param TransformerHelper           $transformerHelper
     * @param RequestStack                $requestStack
     * @param ObjectManager               $objectManager
     * @param ValidatorMetadataLoader     $validatorMetadataLoader
     * @param Logger                      $logger
     * @param NameConverterInterface      $nameConverter
     *
     * @internal param Request $request
     * @internal param ValidatorInterface $validator
     */
    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        DataProviderInterface $dataProvider,
        RouterInterface $router,
        ClassMetadataFactory $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        PropertyAccessorInterface $propertyAccessor,
        TransformerHelper $transformerHelper,
        RequestStack $requestStack,
        ObjectManager $objectManager,
        Logger $logger,
        NameConverterInterface $nameConverter = null
    ) {
        $this->resourceCollection      = $resourceCollection;
        $this->dataProvider            = $dataProvider;
        $this->router                  = $router;
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;
        $this->contextBuilder          = $contextBuilder;
        $this->propertyAccessor        = $propertyAccessor;
        $this->request                 = $requestStack->getCurrentRequest();
        $this->logger                  = $logger;
        $this->transformerHelper       = $transformerHelper;
        $this->objectManager           = $objectManager;
        $this->denormalizingObjects    = new ArrayCollection();
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return self::FORMAT === $format && (is_object($data) || is_array($data));
    }

    /**
     * {@inheritdoc}
     *
     * @throws CircularReferenceException
     * @throws InvalidArgumentException
     */
    public function normalize($object, $format = null, array $context = [])
    {
        $dunglasResource = $this->guessResource($object, $context);

        $this->transformer = $this->transformerHelper->getTransformer($dunglasResource->getShortName());

        if (!$this->fractal) {
            $this->fractal = new Manager();
            $this->fractal->setApiClassMetadataFactory($this->apiClassMetadataFactory);
            $this->fractal->setRouter($this->router);
            $this->fractal->setContextBuilder($this->contextBuilder);
            $this->fractal->setResourceCollection($this->resourceCollection);
        }

        $this->fractal->parseIncludes($this->getEmbedsWithoutOptions());

        if ($object instanceof Paginator) {
            $resource = new Collection($object, $this->transformer);
        } else {
            $resource = new Item($object, $this->transformer);
        }

        $rootScope = $this->fractal->createData($resource, $dunglasResource->getShortName());

        $this->transformer
            ->setCurrentScope($rootScope)
            ->setEmbed($dunglasResource->getShortName());

        return $rootScope->toArray();;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return self::FORMAT === $format;
    }

    /**
     * @param object|string $attributesMetadata
     * @param array $context
     * @param bool $attributesAsString
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
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $resource       = $this->guessResource($data, $context, true);
        $normalizedData = $this->prepareForDenormalization(json_decode($data));

        $attributesMetadata = $this->getMetadata($resource, $context)->getAttributes();

        $allowedAttributes = $this->getAllowedAttributes($attributesMetadata);

        $reflectionClass = new \ReflectionClass($class);

        if (isset($data['@id']) && !isset($context['object_to_populate'])) {
            $context['object_to_populate'] = $this->dataProvider->getItemFromIri($data['@id']);

            // Avoid issues with proxies if we populated the object
            $overrideClass = true;
        } else {
            $overrideClass = false;
        }

        $object = $this->instantiateObject(
            $normalizedData,
            $overrideClass ? get_class($context['object_to_populate']) : $class,
            $context,
            $reflectionClass,
            $allowedAttributes
        );

        if (isset($normalizedData[0])) {
            foreach ($normalizedData as $data) {
                $this->normalizedData($object, (array) $data, $allowedAttributes, $attributesMetadata, $resource);
            }
        } else {
            $this->normalizedData($object, $normalizedData, $allowedAttributes, $attributesMetadata, $resource);
        }

        return $object;
    }

    /**
     * @param $object
     * @param array             $normalizedData
     * @param array             $allowedAttributes
     * @param array             $attributesMetadata
     * @param ResourceInterface $resource
     *
     * @throws \Exception
     */
    protected function normalizedData($object, array $normalizedData, array $allowedAttributes, array $attributesMetadata, ResourceInterface $resource)
    {
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
                        throw new \InvalidArgumentException('Invalid json message received for : '.$attributeName);
                    }

                    if ($type->getClass() === 'Datetime') {
                        $this->setValue(
                            $object,
                            $attributeName,
                            $this->denormalizeRelation($resource, $attributeMetatdata, $class, $attributeValue, $attributeName)
                        );

                        continue;
                    }

                    $id                = $attributeValue;
                    $shortname         = ucfirst(Inflector::singularize($attributeMetatdata->getName()));
                    $attributeResource = $this->resourceCollection->getResourceForShortName($shortname);
                    if (!$attributeResource instanceof ResourceInterface) {
                        throw new \Exception('resource not found for shortname : '.$shortname);
                    }
                    $attributeValue = $this->objectManager->getRepository($attributeResource->getEntityClass())->find($id);
                }
            }

            $this->setValue($object, $attributeName, $attributeValue);
        }
    }

    /**
     * @deprecicate
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
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return array
     */
    private function getEmbedsWithoutOptions()
    {
        $embeds = $this->request->get('embed', null);

        if (null === $embeds) {
            $embeds = implode(',', $this->transformer->getDefaultIncludes());
        }

        if ($this->request->headers->get('e-embed-available', 0)) {
            if (is_null($embeds)) {
                $embeds = implode(',', $this->transformer->getAvailableIncludes());
            } else {
                $arrayEmbed = array_merge(explode(',', $embeds), $this->transformer->getAvailableIncludes());
                $embeds = implode(',', $arrayEmbed);
            }
        }

        $datas = [];
        preg_match_all('|{(.*)}|U', $embeds, $datas);
        $withoutEmbeds = $embeds;
        foreach ($datas[1] as $data) {
            $withoutEmbeds = str_replace('{'.$data.'}', "", $embeds);
        }

        return explode(',', $withoutEmbeds);
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
            isset($context['json_ld_normalization_groups']) ? $context['json_ld_normalization_groups'] : $resource->getNormalizationGroups(),
            isset($context['json_ld_denormalization_groups']) ? $context['json_ld_denormalization_groups'] : $resource->getDenormalizationGroups(),
            isset($context['json_ld_validation_groups']) ? $context['json_ld_validation_groups'] : $resource->getValidationGroups()
        );
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
     * @param string            $class
     * @param mixed             $value
     *
     * @return object|null
     */
    private function denormalizeRelation(ResourceInterface $currentResource, AttributeMetadata $attributeMetadata, $class, $value)
    {
        if ('Datetime' === $class) {
            $dateTimeNormalizer = new DateTimeNormalizer();

            return $dateTimeNormalizer->denormalize($value, $class ?: null, self::FORMAT);
        }

        $attributeName = $attributeMetadata->getName();

        // Always allow IRI to be compliant with the Hydra spec
        if (is_string($value)) {
            $item = $this->dataProvider->getItemFromIri($value);

            if (null === $item) {
                throw new InvalidArgumentException(sprintf(
                    'IRI  not supported (found "%s" in "%s" of "%s")',
                    $value,
                    $attributeName,
                    $currentResource->getEntityClass()
                ));
            }

            return $item;
        }

        if (!$this->resourceCollection->getResourceForEntity($class)) {
            $shortname = ucfirst(Inflector::singularize($attributeName));
            if (!$this->resourceCollection->getResourceForShortName($shortname)) {
                throw new InvalidArgumentException(sprintf(
                    'Type not supported (found "%s" in attribute "%s" of "%s")',
                    $class,
                    $attributeName,
                    $currentResource->getEntityClass()
                ));
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

        throw new InvalidArgumentException(sprintf(
            'Nested objects for attribute "%s" of "%s" are not enabled. Use serialization groups to change that behavior.',
            $attributeName,
            $currentResource->getEntityClass()
        ));
    }

    /**
     * @return null|ClassMetadataFactoryInterface
     */
    public function getClassMetadataFactory()
    {
        return $this->apiClassMetadataFactory;
    }
}
