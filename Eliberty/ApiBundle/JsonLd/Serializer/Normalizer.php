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
use Doctrine\ORM\PersistentCollection;
use Eliberty\ApiBundle\Context\GroupsContextChainer;
use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Fractal\Pagination\DunglasPaginatorAdapter;
use Eliberty\ApiBundle\Fractal\Pagination\PagerfantaPaginatorAdapter;
use Eliberty\ApiBundle\Fractal\Serializer\DataHydraSerializer;
use Eliberty\ApiBundle\Fractal\Serializer\DataXmlSerializer;
use Eliberty\ApiBundle\Fractal\SerializerFactory;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceResolverTrait;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Dunglas\ApiBundle\Model\DataProviderInterface;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Eliberty\ApiBundle\Fractal\Serializer\ArraySerializer;
use League\Fractal\TransformerAbstract;
use Symfony\Bridge\Monolog\Logger;
use Eliberty\ApiBundle\Helper\HeaderHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 */
class Normalizer
{
    use ResourceResolverTrait;

    /**
     * @var string
     */
    const FORMAT = 'json-ld';

    /**
     * @var DataProviderInterface
     */
    public $dataProvider;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var ClassMetadataFactory
     */
    private $apiClassMetadataFactory;

    /**
     * @var ContextBuilder
     */
    private $contextBuilder;

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
     * @var GroupsContextChainer
     */
    private $contextChainer;

    /**
     * @param ResourceCollectionInterface $resourceCollection
     * @param DataProviderInterface       $dataProvider
     * @param RouterInterface             $router
     * @param ClassMetadataFactory        $apiClassMetadataFactory
     * @param ContextBuilder              $contextBuilder
     * @param TransformerHelper           $transformerHelper
     * @param RequestStack                $requestStack
     * @param ObjectManager               $objectManager
     * @param GroupsContextChainer        $contextChainer
     * @param Logger                      $logger
     *
     * @internal param ValidatorMetadataLoader $validatorMetadataLoader
     * @internal param Request $request
     * @internal param ValidatorInterface $validator
     */
    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        DataProviderInterface $dataProvider,
        RouterInterface $router,
        ClassMetadataFactory $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        TransformerHelper $transformerHelper,
        RequestStack $requestStack,
        ObjectManager $objectManager,
        GroupsContextChainer $contextChainer,
        Logger $logger
    ) {
        $this->resourceCollection      = $resourceCollection;
        $this->dataProvider            = $dataProvider;
        $this->router                  = $router;
        $this->apiClassMetadataFactory = $apiClassMetadataFactory;
        $this->contextBuilder          = $contextBuilder;
        $this->request                 = $requestStack->getCurrentRequest();
        $this->logger                  = $logger;
        $this->transformerHelper       = $transformerHelper;
        $this->objectManager           = $objectManager;
        $this->contextChainer          = $contextChainer;
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
    public function normalize($object, $format = null, array $context = [], $defaultIncludes = true)
    {
        $dunglasResource = $this->guessResource($object, $context);
        $transformer = $this->transformerHelper->getTransformer($dunglasResource->getShortName());
        $fractal = $this->getManager();

        if ($this->request) {
            $fractal->parseIncludes($this->getEmbedsWithoutOptions($transformer));
        }

        if ($object instanceof Paginator || $object instanceof PersistentCollection) {
            $resource = new Collection($object, $transformer);
            if ($fractal->getSerializer() instanceof ArraySerializer) {
                $resource->setPaginator(
                    new DunglasPaginatorAdapter($object, $resource)
                );
            }
        } else {
            $resource = new Item($object, $transformer);
        }

        $rootScope = $fractal->createData($resource, $dunglasResource->getShortName());

        if ($defaultIncludes === false) {
            $transformer->setDefaultIncludes([]);
        }

        $transformer
            ->setCurrentScope($rootScope)
            ->setEmbed($dunglasResource->getShortName());

        return $rootScope->toArray();
    }

    /**
     * @return Manager
     */
    public function getManager()
    {
        $manager = new Manager();
        $manager
            ->setApiClassMetadataFactory($this->apiClassMetadataFactory)
            ->setContextBuilder($this->contextBuilder)
            ->setRouter($this->router)
            ->setResourceCollection($this->resourceCollection)
            ->setSerializer(SerializerFactory::getSerializer($this->request))
            ->setGroupsContextChainer($this->contextChainer);

        return $manager;
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
     * @param TransformerAbstract $transformer
     *
     * @return array
     */
    private function getEmbedsWithoutOptions(TransformerAbstract $transformer)
    {
        $embeds = $this->request->get('embed', null);

        if (null === $embeds) {
            $embeds = implode(',', $transformer->getDefaultIncludes());
        }

        $embedAvailable = $this->request->headers->get('e-embed-available', 0);
        if ($this->request->headers->has('e-embed-available') && !$embedAvailable) {
            $transformer->setDefaultIncludes([]);
        }

        if ($embedAvailable) {
            if (is_null($embeds)) {
                $embeds = implode(',', $transformer->getAvailableIncludes());
            } else {
                $arrayEmbed = array_merge(explode(',', $embeds), $transformer->getAvailableIncludes());
                $embeds     = implode(',', $arrayEmbed);
            }
        }

        $datas = [];
        preg_match_all('|{(.*)}|U', $embeds, $datas);
        $withoutEmbeds = $embeds;
        foreach ($datas[1] as $data) {
            $withoutEmbeds = str_replace('{' . $data . '}', "", $embeds);
        }

        return explode(',', $withoutEmbeds);
    }

    /**
     * @return null|ClassMetadataFactoryInterface
     */
    public function getClassMetadataFactory()
    {
        return $this->apiClassMetadataFactory;
    }

    /**
     * @return DataProviderInterface
     */
    public function getDataProvider()
    {
        return $this->dataProvider;
    }

    /**
     * Denormalizes data back into an object of the given class.
     *
     * @param mixed  $data    data to restore
     * @param string $class   the expected class to instantiate
     * @param string $format  format the given data was extracted from
     * @param array  $context options available to the denormalizer
     *
     * @return object
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        return null;
    }

    /**
     * Checks whether the given class is supported for denormalization by this normalizer.
     *
     * @param mixed  $data   Data to denormalize from.
     * @param string $type   The class to which the data should be denormalized.
     * @param string $format The format being deserialized from.
     *
     * @return bool
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return null;
    }
}
