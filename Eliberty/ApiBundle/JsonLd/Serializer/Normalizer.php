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

use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Dunglas\JsonLdApiBundle\Api\ResourceCollectionInterface;
use Dunglas\JsonLdApiBundle\Api\ResourceInterface;
use Dunglas\JsonLdApiBundle\Api\ResourceResolver;
use Dunglas\JsonLdApiBundle\JsonLd\ContextBuilder;
use Dunglas\JsonLdApiBundle\Mapping\ClassMetadataFactory;
use Dunglas\JsonLdApiBundle\Mapping\AttributeMetadata;
use Dunglas\JsonLdApiBundle\Model\DataProviderInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\TransformerAbstract;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Dunglas\JsonLdApiBundle\Api\ResourceCollection;
use Dunglas\JsonLdApiBundle\Doctrine\Orm\Paginator;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Normalizer extends AbstractNormalizer implements NormalizerInterface
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
     * @var TransformerResolver
     */
    private $transformerResolver;

    /**
     * @var Manager
     */
    private $fractal;

    /**
     * @var TransformerAbstract
     */
    private $transformer;

    /**
     * @var ResourceCollection
     */
    private $resourceCollection;

    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        DataProviderInterface $dataProvider,
        RouterInterface $router,
        ClassMetadataFactory $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        PropertyAccessorInterface $propertyAccessor,
        TransformerResolver $transformerResolver,
        ResourceCollection $resourceCollection,
        NameConverterInterface $nameConverter = null
    ) {

        $this->resourceCollection = $resourceCollection;
        $this->dataProvider = $dataProvider;
        $this->router = $router;
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;
        $this->contextBuilder = $contextBuilder;
        $this->propertyAccessor = $propertyAccessor;
        $this->transformerResolver = $transformerResolver;
        $this->resourceCollection = $resourceCollection;
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

        $dunglasRessource = $this->guessResource($object, $context);

        if (empty($this->transformer)) {
            $this->transformer = $this->transformerResolver->resolve($dunglasRessource->getShortName());
        }

        if (empty($this->fractal)) {
            $this->fractal = new Manager();
            $this->fractal->setApiClassMetadataFactory($this->apiClassMetadataFactory);
            $this->fractal->setRouter($this->router);
            $this->fractal->setContextBuilder($this->contextBuilder);
            $this->fractal->setResourceCollection($this->resourceCollection);
        }

        $this->fractal->parseIncludes($this->transformer->getAvailableIncludes());

        if ($object instanceof Paginator) {
            $resource = new Collection($object, $this->transformer);
        } else {
            $resource = new Item($object, $this->transformer);
        }

        $rootScope = $this->fractal->createData($resource, $dunglasRessource->getShortName());

        $this->transformer
            ->setCurrentScope($rootScope)
            ->setInputEmbedParamFetcher('contact');

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
        var_dump('denormalize');exit;
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
        var_dump('normalizeRelation');exit;
    }
}