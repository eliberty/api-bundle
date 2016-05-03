<?php

namespace Eliberty\ApiBundle\Handler;

use Doctrine\Common\Persistence\ObjectManager;
use Eliberty\ApiBundle\Helper\EventHelper;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Event\Events;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
     * @param FormInterface               $form
     * @param RequestStack                $requestStack
     * @param ObjectManager               $manager
     * @param ResourceCollectionInterface $resourceResolver
     * @param EventDispatcherInterface    $dispatcher
     */
    public function __construct(
        FormInterface $form,
        RequestStack $requestStack,
        ObjectManager $manager,
        ResourceCollectionInterface $resourceResolver,
        EventDispatcherInterface $dispatcher
    ) {
        $this->form             = $form;
        $this->request          = $requestStack->getCurrentRequest();
        $this->manager          = $manager;
        $this->resourceResolver = $resourceResolver;
        $this->dispatcher       = $dispatcher;
        $this->eventHelper      = new EventHelper();
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
        $this->sendEvent();
    }

    /**
     * send created event.
     */
    public function sendEvent()
    {
        $entityInsertions = $this->manager->getUnitOfWork()->getScheduledEntityInsertions();
        foreach ($entityInsertions as $entityInsertion) {
            $this->dispatchEvent($entityInsertion, Events::PRE_CREATE);
        }

        $this->manager->getUnitOfWork()->computeChangeSets();
        $entityUpdateds = $this->manager->getUnitOfWork()->getScheduledEntityUpdates();
        foreach ($entityUpdateds as $entityUpdated) {
            $this->dispatchEvent($entityUpdated, Events::PRE_UPDATE);
        }
    }

    /**
     * send updated event.
     */
    public function dispatchEvent($entity, $eventName)
    {
        $this->eventHelper->dispatchEvent($entity, $eventName, $this->resourceResolver, $this->dispatcher);
    }

    /**
     * @param $entity
     * @return \Dunglas\ApiBundle\Api\ResourceInterface|null
     */
    public function getResource($entity) {
        return $this->resourceResolver->getResourceForEntity(get_class($entity));
    }


    /**
     * @return FormInterface
     */
    public function getForm()
    {
        return $this->form;
    }
}
