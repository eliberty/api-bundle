<?php

namespace Eliberty\ApiBundle\Handler;

use Doctrine\Common\Persistence\ObjectManager;
use Eliberty\ApiBundle\Helper\EventHelper;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Event\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class BaseHandler.
 */
abstract class BaseHandler implements HandlerInterface
{
    /**
     * @var FormInterface
     */
    public $form;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var ObjectManager
     */
    protected $manager;

    /**
     * @var ResourceResolver
     */
    protected $resourceResolver;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var EventHelper
     */
    protected $eventHelper;
    /**
     * @var Router
     */
    private $router;


    /**
     * @param RequestStack                $requestStack
     * @param RouterInterface             $router
     * @param ObjectManager               $manager
     * @param ResourceCollectionInterface $resourceResolver
     * @param EventDispatcherInterface    $dispatcher
     */
    public function __construct(
        RequestStack $requestStack,
        RouterInterface $router,
        ObjectManager $manager,
        ResourceCollectionInterface $resourceResolver,
        EventDispatcherInterface $dispatcher
    ) {
        $this->manager          = $manager;
        $this->resourceResolver = $resourceResolver;
        $this->dispatcher       = $dispatcher;
        $this->eventHelper      = new EventHelper();
        $this->router           = $router;
        $this->request          = $requestStack->getCurrentRequest();
    }

    /**
     * @param FormInterface $form
     */
    public function setForm(FormInterface $form) {
        $this->form = $form;
    }

    /**
     * "Success" form handler.
     *
     * @param  $entity
     *
     * @return mixed|void
     */
    public function onSuccess($entity)
    {
        $this->manager->persist($entity);
        $this->sendEvent($this->getApiVersion());
    }

    /**
     * send created event.
     *
     * @param $version
     */
    public function sendEvent($version)
    {
        $entityInsertions = $this->manager->getUnitOfWork()->getScheduledEntityInsertions();
        foreach ($entityInsertions as $entityInsertion) {
            $this->dispatchEvent($entityInsertion, Events::PRE_CREATE, $version);
        }

        $this->manager->getUnitOfWork()->computeChangeSets();
        $entityUpdateds = $this->manager->getUnitOfWork()->getScheduledEntityUpdates();
        foreach ($entityUpdateds as $entityUpdated) {
            $this->dispatchEvent($entityUpdated, Events::PRE_UPDATE, $version);
        }
    }

    /**
     * send updated event.
     *
     * @param $entity
     * @param $eventName
     * @param $version
     */
    public function dispatchEvent($entity, $eventName, $version)
    {
        $this->eventHelper->dispatchEvent($version, $entity, $eventName, $this->resourceResolver, $this->dispatcher);
    }

    /**
     * @param $entity
     * @param $version
     *
     * @return \Dunglas\ApiBundle\Api\ResourceInterface|null
     */
    public function getResource($entity, $version) {
        return $this->resourceResolver->getResourceForEntityWithVersion(get_class($entity), $version);
    }


    /**
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     * return string
     */
    protected function getApiVersion() {
        $this->router->getContext()->getApiVersion();
    }
}
