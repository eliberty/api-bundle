<?php

namespace Eliberty\ApiBundle\WebHook\Listener;

use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Eliberty\ApiBundle\JsonLd\ContextBuilder;
use Eliberty\ApiBundle\Transformer\BaseTransformer;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Eliberty\ApiBundle\WebHook\Event\EntityListenerEvent;
use Eliberty\ApiBundle\Fractal\Manager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Events;
use Guzzle\Http\Client;
use League\Fractal\Resource\Item;
use League\Fractal\Scope;
use League\Fractal\TransformerAbstract;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class WebHookListener.
 */
class WebHookListener implements EventSubscriberInterface
{
    /**
     * @var array
     */
    protected $configuration = [
//        'Acme\DemoBundle\Entity\Contact' => [
//            'Listener' => 'Acme\DemoBundle\Doctrine\Listener\Entity\ContactListener',
//            'Transformer' => 'Acme\DemoBundle\Transformer\Api\V1\ContactTransformer',
//            'Embed' => ['addresses', 'rights'],
//            'Action'   => [
//                Events::postPersist => 'postPersist',
//                Events::postUpdate  => 'postUpdate',
//                Events::postRemove  => 'postRemove'
//            ]
//        ],
//        'Acme\DemoBundle\Entity\Address' => [
//            'Listener' => 'Acme\DemoBundle\Doctrine\Listener\Entity\AddressListener',
//            'Transformer' => 'Acme\DemoBundle\Transformer\Api\V1\ContactTransformer',
//            'Embed' => ['contact', 'rights'],
//            'CallBackMethod' => 'getContact',
//            'Action'   => [
//                Events::postPersist => 'postPersist',
//                Events::postUpdate  => 'postUpdate',
//                Events::postRemove  => 'postRemove'
//            ]
//        ]
    ];

    /**
     * @var Client
     */
    protected $client = null;
    /**
     * @var array
     */
    protected $updateData = [];

    /**
     * @var array
     */
    protected $persistData = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    private $entityManager;
    /**
     * @var ResourceCollection
     */
    protected $resourceCollection;
    /**
     * @var RequestStack
     */
    protected $requestStack;
    /**
     * @var TransformerResolver
     */
    private $transformerResolver;

    /**
     * @param EntityManager               $em
     * @param RouterInterface             $router
     * @param ClassMetadataFactory        $apiClassMetadataFactory
     * @param ContextBuilder              $contextBuilder
     * @param ResourceCollectionInterface $resourceCollection
     * @param TransformerResolver         $transformerResolver
     * @param RequestStack                $requestStack
     * @param LoggerInterface             $logger
     */
    public function __construct(
        EntityManager $em,
        RouterInterface $router,
        ClassMetadataFactory $apiClassMetadataFactory,
        ContextBuilder $contextBuilder,
        ResourceCollectionInterface $resourceCollection,
        TransformerResolver $transformerResolver,
        RequestStack $requestStack,
        LoggerInterface $logger
    ) {

        $this->entityManager = $em;
        $this->logger        = $logger;

        $this->fractal = new Manager();
        $this->fractal
            ->setApiClassMetadataFactory($apiClassMetadataFactory)
            ->setRouter($router)
            ->setContextBuilder($contextBuilder)
            ->setResourceCollection($resourceCollection);

        $this->resourceCollection  = $resourceCollection;
        $this->requestStack        = $requestStack;
        $this->transformerResolver = $transformerResolver;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'webhook.update.entity'  => [
                ['processUpdateEntity', 245],
            ],
            'webhook.persist.entity' => [
                ['processPersistEntity', 245],
            ],
            'kernel.controller'      => [
                ['onKernelController', 0],
            ],
            'kernel.terminate'       => [
                ['onKernelTerminate', 0],
            ],
        ];
    }

    /**
     * Do job if necessary.
     */
    public function onKernelTerminate()
    {
        $this->buildDataForMessage($this->persistData, 'create');
        $this->buildDataForMessage($this->updateData, 'update');
    }

    /**
     * send a update message.
     *
     * @param $objects
     * @param $action
     */
    protected function buildDataForMessage($objects, $action)
    {
        foreach ($objects as $object) {
            try {
                $config = $this->getConfiguration($object, $action);
                foreach ($config as $version => $data) {
                    $classTransformer = $this->getConfigByKey($data, 'Transformer');
                    if (null !== $classTransformer && class_exists($classTransformer)) {
                        $dataResponse          = [];
                        $transformer           = new $classTransformer($this->entityManager);
                        $transformer           = $this->initTransformer($transformer);
                        $currentObjectResponse = $this->getTransformerData($transformer, $object, $version);

                        foreach ($data["Target"] as $embed => $target) {
                            $parentObject = $this->isEmbed($transformer, $object, $embed);
                            $dataResponse[$transformer->getCurrentResourceKey()] = $currentObjectResponse;

                            if (null !== $parentObject) {
                                $dataResponse = array_merge(
                                    $this->getEmbedData($embed, $parentObject, $version),
                                    $dataResponse
                                );
                            }

                            if (isset($dataResponse[$embed])) {
                                $this->sendMessage($dataResponse, $target, $action);
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                $this->logger->info($ex->getMessage());
            }
        }
    }


    /**
     * @param BaseTransformer $transformer
     *
     * @return BaseTransformer
     */
    protected function initTransformer(BaseTransformer $transformer)
    {
        $defaultIncludes = [];
        if (in_array('user', $transformer->getAvailableIncludes())) {
            $defaultIncludes[] = 'user';
        }
        $transformer->setDefaultIncludes($defaultIncludes);

        return $transformer;
    }

    /**
     * get the current object data transforme
     *
     * @param $transformer
     * @param $object
     * @param $version
     *
     * @return array
     * @internal param $classTransformer
     */
    public function getTransformerData(TransformerAbstract $transformer, $object, $version)
    {
        $scope = $this->initScope($transformer, $object, $version);

        return $scope->toArray();
    }

    /**
     * @param TransformerAbstract $transformer
     * @param                     $object
     * @param string              $apiVersion
     *
     * @return Scope
     */
    protected function initScope(TransformerAbstract $transformer, $object, $apiVersion = "v2")
    {
        $resource  = new Item($object, $transformer);
        $scope     = $this->fractal->createData($resource);
        $shortName = $transformer->getCurrentResourceKey();
        if (!($dunglasResource = $this->resourceCollection->getResourceForShortNameWithVersion(ucfirst($shortName), $apiVersion))) {
            throw new \InvalidArgumentException(sprintf('The resource "%s" cannot be found.', $shortName));
        }
        $scope->setDunglasResource($dunglasResource);

        return $scope;
    }

    /**
     * @param $embed
     * @param $parentObject
     * @param $version
     *
     * @return array
     * @throws \Exception
     */
    protected function getEmbedData($embed, $parentObject, $version = "v2")
    {
        $transformer = $this->transformerResolver->resolve($embed);
        $transformer = $this->initTransformer($transformer);

        return [
            $transformer->getCurrentResourceKey() => $this->getTransformerData($transformer, $parentObject, $version)
        ];
    }

    /**
     * @param TransformerAbstract $transformer
     * @param                     $object
     * @param                     $embed
     * @param string              $version
     *
     * @return bool
     */
    protected function isEmbed(TransformerAbstract $transformer, $object, $embed, $version = 'v2')
    {
        if (in_array($embed, $transformer->getAvailableIncludes())) {
            return call_user_func([$object, 'get' . ucfirst($embed)]);
        }

        return null;
    }

    /**
     * @param FilterControllerEvent $event
     *
     * @return bool
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        if ($this->requestStack->getCurrentRequest()->getMethod() === 'GET') {
            return false;
        }

        try {
            $em  = $this->entityManager;
            $cmf = $em->getMetadataFactory();

            foreach ($this->configuration as $entityClass => $listenerElement) {
                $classMetadata = $cmf->getMetadataFor($entityClass);
                foreach ($listenerElement as $actionName => $apiData) {
                    foreach ($apiData as $apiVersion => $data) {
                        foreach ($data['Action'] as $eventName => $methodName) {
                            $classMetadata->addEntityListener($eventName, $data['Listener'], $methodName);
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            $this->logger->info($ex->getMessage());
        }
    }

    /**
     * @param EntityListenerEvent $event
     */
    public function processPersistEntity(EntityListenerEvent $event)
    {
        $this->logger->info('processPersistEntity');
        $entity                              = $event->getLifecycleEventArgs()->getEntity();
        $this->persistData[$entity->getId()] = $entity;
    }

    /**
     * @param EntityListenerEvent $event
     */
    public function processUpdateEntity(EntityListenerEvent $event)
    {
        $this->logger->info('processUpdateEntity');
        $entity                             = $event->getLifecycleEventArgs()->getEntity();
        $this->updateData[$entity->getId()] = $entity;
    }

    /**
     * @param $object
     *
     * @param $action
     *
     * @return array
     */
    protected function getConfiguration($object, $action)
    {
        $className = get_class($object);
        return isset($this->configuration[$className][$action]) ? $this->configuration[$className][$action] : [];
    }

    /**
     * @param      $config
     * @param      $key
     * @param null $default
     *
     * @return null
     */
    protected function getConfigByKey($config, $key, $default = null)
    {
        return isset($config[$key]) ? $config[$key] : $default;
    }

    /**
     * get the webhook configuration.
     *
     * @param array $config
     */
    protected function setConfiguration($config = [])
    {
        $this->configuration = $config;
    }

    /**
     * create guzzle client for send message.
     */
    public function initClient()
    {
        if (is_null($this->client)) {
            $config['request.options']['proxy'] = 'http://localhost:8080';
            $this->client                       = new Client('', $config);
        }
    }

    /**
     * send a message to the uri.
     */
    protected function sendMessage($dataResponse, $target, $eventType)
    {
//        $this->initClient();
//        $uris       = $target['uri'];
//        $secrets    = $target['secret'];
//        $randoms    = $target['random'];
//
//        foreach ($uris as $clientId => $url) {
//            $clientRequest = $this->client->createRequest('POST', $url);
//            $clientRequest->setHeader('Content-Type', 'application/json');
//            $dataResponse['x-redpill-delivery']  = $clientId.'_'.$randoms[$clientId];
//            $dataResponse['x-redpill-secret'] = $secrets[$clientId];
//            ksort($dataResponse);
//            $message = new JsonResponse($dataResponse);
//            $this->logger->info('send for uri:'.$url.'  with mesage : '.$message);
//            $clientRequest->setBody($message);
//            try {
//                $this->client->send($clientRequest);
//            } catch (\Exception $ex) {
//                $this->logger->info($ex->getMessage());
//            }
//        }
    }
}
