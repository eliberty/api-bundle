<?php

namespace Eliberty\ApiBundle\Versioning\Router;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;

/**
 * Class ApiRouter.
 */
class ApiRouter extends Router implements RequestMatcherInterface
{
    /**
     * @var RequestStack
     */
    protected $requestStack;
    /**
     * @var string
     */
    private $acceptHeader;

    /**
     * @param ContainerInterface $container
     * @param mixed              $resource
     * @param array              $options
     * @param RequestContext $context
     */
    public function __construct(ContainerInterface $container, $resource, array $options = array(), RequestContext $context = null)
    {
        parent::__construct($container, $resource, $options, $context);
        $this->acceptHeader = "/application\/vnd.eliberty.api.+json/";
        $this->requestStack = $container->get('request_stack');

    }

    /**
     * {@inheritdoc}
     */
    public function match($pathinfo)
    {
        $this->setApiVersion();
        return parent::match($pathinfo);
    }

    /**
     * @param Request $request
     * @return array
     */
    public function matchRequest(Request $request)
    {
        $this->setApiVersion();

        return parent::matchRequest($this->requestStack->getCurrentRequest());
    }

    /**
     * set apiVersion into context
     */
    public function setApiVersion()
    {

        $version = "v1";

        $request = $this->requestStack->getCurrentRequest();
        $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'))->all();
        foreach ($acceptHeader as $acceptHeaderItem) {
            if ($acceptHeaderItem->hasAttribute('version')) {
                $version = $acceptHeaderItem->getAttribute('version');
                break;
            }
        }

        /*
         * @TODO check if always necessary
         */
        $context = $this->getContext();
        $context->setMethod($request->getMethod());
        $context->setApiVersion($version);
        $this->setContext($context);
    }
}
