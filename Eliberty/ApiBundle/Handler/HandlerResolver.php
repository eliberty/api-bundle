<?php
namespace Eliberty\ApiBundle\Handler;

use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;

/**
 * Class HandlerResolver
 * @package Eliberty\ApiBundle\Handler
 */
class HandlerResolver
{
    /**
     * @param HandlerInterface $handler
     * @param $serviceId
     */
    public function add(HandlerInterface $handler, $serviceId)
    {
        $this->mapping[$serviceId] = $handler;
    }

    /**
     * @param $entityName
     * @param $version
     *
     * @return mixed
     * @throws \Exception
     */
    public function resolve($entityName, $version)
    {
        $serviceId = 'handler.'.strtolower($entityName).'.api.'.$version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('handler not found for '.$serviceId);
    }
}
