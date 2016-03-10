<?php
namespace Eliberty\ApiBundle\Transformer\Listener;

use Doctrine\ORM\Mapping\DefaultEntityListenerResolver;
use Eliberty\ApiBundle\Api\Resource;
use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use League\Fractal\TransformerAbstract;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TransformerResolver extends BaseResolver
{

    /**
     * @param TransformerAbstract $transformer
     * @param $serviceId
     */
    public function add(TransformerAbstract $transformer, $serviceId)
    {
        $transformer->setRequest($this->request);
        $this->mapping[$serviceId] = $transformer;
    }

    /**
     * @param $entityName
     * @return object
     * @throws \Exception
     */
    public function resolve($entityName)
    {
        if ($entityName instanceof Resource) {
            $entityName = $entityName->getShortName();
        }

        $serviceId = 'transformer.'.strtolower($entityName).'.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('transformer not found for '.$serviceId);
    }
}
