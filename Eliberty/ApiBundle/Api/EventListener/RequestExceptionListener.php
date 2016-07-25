<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Api\EventListener;


use Dunglas\ApiBundle\Exception\DeserializationException;
use Dunglas\ApiBundle\JsonLd\Response;
use Eliberty\ApiBundle\Api\ApiSerializer;
use Eliberty\ApiBundle\Resolver\ContextResolverTrait;
use Eliberty\ApiBundle\Api\NormalizeSerializer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Dunglas\ApiBundle\Hydra\EventListener\RequestExceptionListener as BaseRequestExceptionListener;
use Eliberty\ApiBundle\Xml\Response as XmlResponse;
/**
 * Handle requests errors.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 * @author Samuel ROZE <samuel.roze@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class RequestExceptionListener extends BaseRequestExceptionListener
{
    use ContextResolverTrait;
    /**
     * @var mixed|string
     */
    protected $format;

    /**
     * @var ApiSerializer
     */
    protected $serializer;

    /**
     * RequestExceptionListener constructor.
     *
     * @param RequestStack  $requestStack
     * @param ApiSerializer $serializer
     */
    public function __construct(RequestStack $requestStack, ApiSerializer $serializer)
    {
        $this->serializer = $serializer;
        $this->format     = $this->getContext($requestStack->getCurrentRequest());
    }

    /**
     * @param GetResponseForExceptionEvent $event
     */
    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        $request   = $event->getRequest();
        $exception = $event->getException();

        if ($exception instanceof HttpException) {
            $status  = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        } elseif ($exception instanceof DeserializationException) {
            $status  = Response::HTTP_BAD_REQUEST;
            $headers = [];
        } else {
            $code    = method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : $exception->getCode();
            $status  = $code === 0 ? Response::HTTP_INTERNAL_SERVER_ERROR : $code;
            $headers = [];
        }

        // Normalize exceptions with hydra errors only for resources
        if ($request->attributes->has('_resource')) {
            $dataResponse = $this->serializer->serialize($exception, $this->format);
            $response = new Response($dataResponse, $status, $headers);
            if ($this->format === 'xml') {
                $response = new XmlResponse($dataResponse, $status, $headers);
            }
            $event->setResponse($response);
        }
    }
}
