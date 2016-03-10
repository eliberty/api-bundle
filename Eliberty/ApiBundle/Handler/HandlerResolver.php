<?php
namespace Eliberty\ApiBundle\Handler;

use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class HandlerResolver
 * @package Eliberty\ApiBundle\Handler
 */
class HandlerResolver extends BaseResolver
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
     * @return object
     * @throws \Exception
     */
    public function resolve($entityName)
    {
        $serviceId = 'handler.'.strtolower($entityName).'.api.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('handler not found for '.$serviceId);
    }
}
