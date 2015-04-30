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

use Dunglas\ApiBundle\JsonLd\Serializer\DateTimeNormalizer;
use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Api\ResourceResolver;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Dunglas\ApiBundle\Mapping\AttributeMetadata;
use Dunglas\ApiBundle\Model\DataProviderInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\TransformerAbstract;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Dunglas\ApiBundle\Api\ResourceCollection;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 *
 * @package Eliberty\ApiBundle\JsonLd\Serializer
 */
class Normalizer extends AbstractNormalizer
{
    use ResourceResolver;

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
     * @param ResourceCollectionInterface $resourceCollection
     * @param DataProviderInterface $dataProvider
     * @param RouterInterface $router
     * @param ClassMetadataFactory $apiClassMetadataFactory
     * @param ContextBuilder $contextBuilder
     * @param PropertyAccessorInterface $propertyAccessor
     * @param TransformerHelper $transformerHelper
     * @param Request $request
     * @param NameConverterInterface $nameConverter
     */
    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        DataProviderInterface $dataProvider,
        RouterInterface $router,
        ClassMetadataFactory $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        PropertyAccessorInterface $propertyAccessor,
        TransformerHelper $transformerHelper,
        Request $request,
        NameConverterInterface $nameConverter = null
    ) {

        $this->resourceCollection = $resourceCollection;
        $this->dataProvider = $dataProvider;
        $this->router = $router;
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;
        $this->contextBuilder = $contextBuilder;
        $this->propertyAccessor = $propertyAccessor;
        $this->request =$request;
        $this->transformerHelper = $transformerHelper;
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

        if (!$this->transformer) {
            $this->transformer = $this->transformerHelper->getTransformer($dunglasResource->getShortName());
            $this->transformer->setRequest($this->request);
        }

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

        return $rootScope->toArray();
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
     *
     * @throws InvalidArgumentException
     */
    public function denormalize($data, $class, $format = null, array $context = [])
    {

        $resource = $this->guessResource($data, $context, true);
        $normalizedData = $this->prepareForDenormalization(json_decode($data));

        $attributesMetadata = $this->getMetadata($resource, $context)->getAttributes();

        $allowedAttributes = [];
        foreach ($attributesMetadata as $attributeName => $attributeMetatdata) {
            if ($attributeMetatdata->isReadable()) {
                $allowedAttributes[] = $attributeName;
            }
        }

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

            $types = $attributesMetadata[$attributeName]->getTypes();
            if (isset($types[0])) {
                $type = $types[0];

                if (
                    $attributeValue &&
                    $type->isCollection() &&
                    ($collectionType = $type->getCollectionType()) &&
                    ($class = $collectionType->getClass())
                ) {
                    $values = [];
                    foreach ($attributeValue as $obj) {
                        $values[] = $this->denormalizeRelation($resource, $attributesMetadata[$attributeName], $class, $obj);
                    }

                    $this->setValue($object, $attributeName, $values);

                    continue;
                }

                if ($attributeValue && ($class = $type->getClass())) {
                    $this->setValue(
                        $object,
                        $attributeName,
                        $this->denormalizeRelation($resource, $attributesMetadata[$attributeName], $class, $attributeValue)
                    );

                    continue;
                }
            }

            $this->setValue($object, $attributeName, $attributeValue);
        }

        return $object;
    }



    /**
     * Normalizes a relation as an URI if is a Link or as a JSON-LD object.
     *
     * @param ResourceInterface $currentResource
     * @param AttributeMetadata $attribute
     * @param mixed             $relatedObject
     * @param string            $class
     *
     * @return string|array
     */
    private function normalizeRelation(ResourceInterface $currentResource, AttributeMetadata $attribute, $relatedObject, $class)
    {
        if ($attribute->isNormalizationLink()) {
            return $this->router->generate($relatedObject);
        } else {
            $context = $this->contextBuilder->bootstrapRelation($currentResource, $class);

            return $this->serializer->normalize($relatedObject, 'json-ld', $context);
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
            $embeds = implode(',', $this->transformer->getAvailableIncludes());
        }

        $datas        = [];
        preg_match_all('|{(.*)}|U', $embeds, $datas);
        $withoutEmbeds = $embeds;
        foreach ($datas[1] as $data) {
            $withoutEmbeds = str_replace('{' . $data . '}', "", $embeds);
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
    private function getMetadata(ResourceInterface $resource, array $context)
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
        if ('DateTime' === $class) {
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
            throw new InvalidArgumentException(sprintf(
                'Type not supported (found "%s" in attribute "%s" of "%s")',
                $class,
                $attributeName,
                $currentResource->getEntityClass()
            ));
        }

        $context = $this->contextBuilder->bootstrapRelation($currentResource, $class);
        if (!$attributeMetadata->isDenormalizationLink()) {
            return $this->denormalize($value, $class, self::FORMAT, $context);
        }

        throw new InvalidArgumentException(sprintf(
            'Nested objects for attribute "%s" of "%s" are not enabled. Use serialization groups to change that behavior.',
            $attributeName,
            $currentResource->getEntityClass()
        ));
    }
}
