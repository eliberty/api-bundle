<?php
namespace Eliberty\ApiBundle\Resolver;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class BaseResolver
 * @package Eliberty\ApiBundle\Handler
 */
abstract class BaseResolver
{
    /**
     * @var array
     */
    protected $mapping;

    /**
     * @var string
     */
    protected $version = 'v2';
    /**
     * @var Request
     */
    protected $request;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->mapping   = [];
        $this->request = $requestStack->getCurrentRequest();
        if ($this->request instanceof Request) {
            $acceptHeader = AcceptHeader::fromString($this->request->headers->get('Accept'))->all();
            foreach ($acceptHeader as $acceptHeaderItem) {
                if ($acceptHeaderItem->hasAttribute('version')) {
                    $this->version = $acceptHeaderItem->getAttribute('version');
                    break;
                }
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
}
