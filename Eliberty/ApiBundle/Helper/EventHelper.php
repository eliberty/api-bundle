<?php

namespace Eliberty\ApiBundle\Helper;
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Eliberty\ApiBundle\Api\Resource;
/**
 * Class EventHelper
 */
class EventHelper {
    /**
     * @param $entity
     * @param array $event
     * @return object
     * @throws \Exception
     */
    public function createEventClass($entity, $event = []) {
        try {
            $eventClass = isset($event['eventClass']) ? $event['eventClass'] : 'Symfony\Component\EventDispatcher\GenericEvent';
            if (isset($event['arguments'])) {
                $args = [];
                $rc   = new \ReflectionClass($eventClass);
                foreach ($event['arguments'] as $arg) {
                    if ($arg === 'this') {
                        $args [] = $entity;
                        continue;
                    }
                    $args[] = $entity->$arg();
                };
                return $rc->newInstanceArgs($args);
            }

            return new $eventClass($entity);
        } catch (\Exception $e) {
            throw new \Exception('error configuration for api create event' , ['event', $event]);
        }
    }

    /**
     * read the configuration and dispatch event
     * @param $entity
     * @param $eventName
     * @param ResourceCollectionInterface $resourceResolver
     * @param EventDispatcherInterface $dispatcher
     * @return null
     * @throws \Exception
     */
    public function dispatchEvent(
        $entity,
        $eventName,
        ResourceCollectionInterface $resourceResolver,
        EventDispatcherInterface $dispatcher
    ) {
        /** @var \Eliberty\ApiBundle\Api\Resource $resource */
        $resource = $resourceResolver->getResourceForEntity(get_class($entity));

        if (null === $resource) {
            $entityClass = get_class($entity);
            $shortName = substr($entityClass, strrpos($entityClass, '\\') + 1);
            $resource = $resourceResolver->getResourceForShortName($shortName);
        }

        if (null === $resource) {
            return null;
        }

        if ($resource->hasEventListener($eventName)) {
            $events  = $resource->getListener($eventName);
            foreach($events as $eventData) {
                $event = $this->createEventClass($entity, $eventData);
                $eventName = isset($eventData['eventName']) ? $eventData['eventName']: $eventName;
                $dispatcher->dispatch($eventName, $event);
            }
        }
    }
}
