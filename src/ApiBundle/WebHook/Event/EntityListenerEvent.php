<?php

namespace Eliberty\ApiBundle\WebHook\Event;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\EventDispatcher\Event;

/**
 * Asset Event
 */
class EntityListenerEvent extends Event
{
    /**
     * @var LifecycleEventArgs
     */
    private $args;

    /**
     * @param LifecycleEventArgs $args
     */
    public function __construct(LifecycleEventArgs $args)
    {
        $this->args = $args;
    }

    /**
     * @return LifecycleEventArgs
     */
    public function getLifecycleEventArgs()
    {
        return $this->args;
    }
}
