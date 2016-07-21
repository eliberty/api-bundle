<?php
namespace Eliberty\ApiBundle\Transformer\Listener;

use Eliberty\ApiBundle\Resolver\VersionResolverTrait;
use Eliberty\ApiBundle\Api\Resource;
use Eliberty\ApiBundle\Helper\HeaderHelper;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use League\Fractal\TransformerAbstract;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class TransformerResolver
 *
 * @package Eliberty\ApiBundle\Transformer\Listener
 */
class TransformerResolver
{

    use VersionResolverTrait;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var string
     */
    protected $version;

    /**
     * TransformerResolver constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->version = $this->getVersion($this->request);
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
        if ($entityName instanceof Resource) {
            $entityName = $entityName->getShortName();
        }

        $serviceId = 'transformer.'.strtolower($entityName).'.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('transformer not found for '.$serviceId);
    }

    /**
     * @param $version
     */
    public function setVersion($version) {
        $this->version = $version;
    }
}
