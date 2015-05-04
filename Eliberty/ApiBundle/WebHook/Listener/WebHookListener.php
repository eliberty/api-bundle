<?php

namespace Eliberty\ApiBundle\WebHook\Listener;

use Eliberty\ApiBundle\WebHook\Event\EntityListenerEvent;
use Eliberty\ApiBundle\Fractal\Manager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\Builder\EntityListenerBuilder;
use Guzzle\Http\Client;
use League\Fractal\Resource\Item;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;

/**
 * Class WebHookListener.
 */
class WebHookListener implements EventSubscriberInterface
{
    /**
     * @var array
     */
    private $configuration = [
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
     * @var array
     */
    private $updateData = [];

    /**
     * @var array
     */
    private $persistData = [];

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EntityManager
     */
    private $entityManager;


    /**
     * @param EntityManager $em
     * @param LoggerInterface $logger
     */
    public function __construct(EntityManager $em, LoggerInterface $logger)
    {
        $this->entityManager = $em;
        $this->logger        = $logger;

        $config['request.options']['proxy'] = 'http://localhost:8080';
        $this->fractal = new Manager();
        $this->client  = new Client('', $config);
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'weebhook.update.entity' => [
                ['processUpdateEntity', 245],
            ],
            'weebhook.persist.entity' => [
                ['processPersistEntity', 245],
            ],
            'kernel.controller' =>[
                ['onKernelController', 0]
            ],
            'kernel.terminate'  => [
                ['onKernelTerminate', 0],
            ],
        ];
    }

    /**
     * Do job if necessary.
     */
    public function onKernelTerminate()
    {

        $dataResponse = [];
        foreach ($this->persistData as $object) {
            $dataResponse['action'] = 'persist';
            $this->logger->alert('persist :'.$object->getId() );
        }

        foreach ($this->updateData as $object) {
            try {
                $config = $this->getConfiguration($object);
                $classTransformer = $this->getConfigByKey($config, 'Transformer');

                if (null !== $classTransformer) {
                    $transformer = new $classTransformer($this->entityManager);
                    $this->fractal->parseIncludes($this->getConfigByKey($config, 'Embed', []));
                    $resource     = new Item($object, $transformer);
                    $rootScope = $this->fractal->createData($resource);
                    $data[$transformer->getCurrentResourceKey()] = $rootScope->toArray();
                    $this->logger->debug(new JsonResponse($data));
                    $dataResponse = array_merge($dataResponse, $data);
                }
            } catch (\Exception $ex) {
                $this->logger->alert($ex->getMessage());
            }
        }

        if (!empty($dataResponse)) {
            $clientRequest = $this->client->createRequest('POST', 'http://tignes.redpill.e-liberty.fr/');
            $clientRequest->setHeader('Content-Type', 'application/json');

            $clientRequest->setBody(new JsonResponse($dataResponse));
            $response = $this->client->send($clientRequest);
        }
    }

    /**
     * @param FilterControllerEvent $event
     */
    public function onKernelController(FilterControllerEvent $event)
    {
        try {
            $em  = $this->entityManager;
            $cmf = $em->getMetadataFactory();

            foreach ($this->configuration as $entityClass => $listenerElement) {
                $classMetadata = $cmf->getMetadataFor($entityClass);
//                EntityListenerBuilder::bindEntityListener($classMetadata, $entityClass);
                foreach($listenerElement['Action'] as $eventName => $methodName) {
                    $classMetadata->addEntityListener($eventName, $listenerElement['Listener'], $methodName);
                }
            }
        } catch (\Exception $ex) {
            $this->logger->alert($ex->getMessage());
        }
    }

    /**
     * @param EntityListenerEvent $event
     */
    public function processPersistEntity(EntityListenerEvent $event)
    {
        $this->logger->alert('processPersistEntity');
        $entity = $this->applyCallBackMethod($event);

        $this->persistData[$entity->getId()] = $entity;
    }

    /**
     * @param EntityListenerEvent $event
     */
    public function processUpdateEntity(EntityListenerEvent $event)
    {
        $this->logger->alert('processUpdateEntity');
        $entity = $this->applyCallBackMethod($event);
        $this->updateData[$entity->getId()] = $entity;
    }

    /**
     * @param $object
     * @return array
     */
    private function getConfiguration($object)
    {
        $className = get_class($object);

        return isset($this->configuration[$className]) ? $this->configuration[$className] : [];
    }

    /**
     * @param $config
     * @param $key
     * @param null $default
     * @return null
     */
    private function getConfigByKey($config, $key, $default = null)
    {
        return isset($config[$key]) ? $config[$key] : $default;
    }

    /**
     * @param EntityListenerEvent $event
     * @return object|string
     */
    private function applyCallBackMethod(EntityListenerEvent $event)
    {
        $config = $this->getConfiguration($event->getLifecycleEventArgs()->getEntity());
        $callBackMethod = $this->getConfigByKey($config, 'CallBackMethod');
        if (null !== $callBackMethod) {
            return call_user_func([$event->getLifecycleEventArgs()->getEntity(), $callBackMethod]);
        }

        return $event->getLifecycleEventArgs()->getEntity();
    }
}
