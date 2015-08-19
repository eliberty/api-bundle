<?php
namespace Eliberty\ApiBundle\WebHook\Doctrine\Listener;

use Eliberty\ApiBundle\WebHook\Event\EntityListenerEvent;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class WebHookBaseListener
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param LoggerInterface $logger
     */
    public function __construct($eventDispatcher, $logger)
    {
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args)
    {
        $this->logger->alert('postPersist');
        $this->eventDispatcher->dispatch('weebhook.persist.entity', new EntityListenerEvent($args));
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args)
    {
        $this->logger->alert('postUpdate');
        $this->eventDispatcher->dispatch('weebhook.update.entity', new EntityListenerEvent($args));
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postRemove(LifecycleEventArgs $args)
    {
        $this->logger->alert('postRemove');
        $this->eventDispatcher->dispatch('weebhook.remove.entity', new EntityListenerEvent($args));
    }
}