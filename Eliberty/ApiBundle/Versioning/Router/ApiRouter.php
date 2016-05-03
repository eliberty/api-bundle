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
     * @param ContainerInterface $container
     * @param mixed $resource
     * @param array $options
     * @param RequestContext $context
     */
    public function __construct(
        ContainerInterface $container,
        $resource,
        array $options = array(),
        RequestContext $context = null
    ) {
        parent::__construct($container, $resource, $options, $context);
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

        if (!is_null($this->getContext()->getApiVersion())) {
            return false;
        }

        $version = "v1";

        $request = $this->requestStack->getCurrentRequest();

        $version = $this->getVersionByAcceptHeaders($request, $version);
        $version = $this->getVersionByAccessControlRequestHeaders($request, $version);

        /*
         * @TODO check if always necessary
         */
        $this->getContext()->setMethod($request->getMethod());
        $this->getContext()->setApiVersion($version);
    }

    /**
     * @param Request $request
     * @param $version
     * @return mixed
     */
    public function getVersionByAcceptHeaders(Request $request, $version)
    {
        $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'))->all();
        foreach ($acceptHeader as $acceptHeaderItem) {
            if ($acceptHeaderItem->hasAttribute('version')) {
                $version = $acceptHeaderItem->getAttribute('version');
                break;
            }
        }

        return $version;
    }

    /**
     * @param Request $request
     * @param $version
     * @return mixed
     */
    public function getVersionByAccessControlRequestHeaders(Request $request, $version)
    {
        if ($request->headers->has('Access-Control-Request-Headers')) {
            $accessControlRequestHeaders = $request->headers->get('Access-Control-Request-Headers');

            if (
                preg_match('/e-api-v\d/', $accessControlRequestHeaders, $matches) &&
                preg_match('/v\d/', $matches[0], $matches2)
            ) {
                $version = array_shift($matches2);
            }
        }

        return $version;
    }
}
