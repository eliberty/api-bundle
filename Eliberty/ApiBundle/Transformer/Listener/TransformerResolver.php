<?php
namespace Eliberty\ApiBundle\Transformer\Listener;

use Doctrine\ORM\Mapping\DefaultEntityListenerResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use League\Fractal\TransformerAbstract;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TransformerResolver
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @var string
     */
    private $version = 'v2';
    /**
     * @var Request
     */
    private $request;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->mapping   = [];
        $this->request = $requestStack->getCurrentRequest();

        $acceptHeader = AcceptHeader::fromString($this->request->headers->get('Accept'))->all();
        foreach ($acceptHeader as $acceptHeaderItem) {
            if ($acceptHeaderItem->hasAttribute('version')) {
                $this->version = $acceptHeaderItem->getAttribute('version');
                break;
            }
        }
    }

    /**
     * @param string $version
     *
     * @return $this
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

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
        $serviceId = 'transformer.'.strtolower($entityName).'.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('transformer not found for '.$serviceId);
    }
}