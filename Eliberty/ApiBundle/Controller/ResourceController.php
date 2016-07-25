<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Eliberty\ApiBundle\Resolver\ContextResolverTrait;
use Dunglas\ApiBundle\Event\Events;
use Eliberty\ApiBundle\Doctrine\Orm\ArrayPaginator;
use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Fractal\SerializerFactory;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Dunglas\ApiBundle\Event\DataEvent;
use Dunglas\ApiBundle\Exception\DeserializationException;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Model\PaginatorInterface;
use Dunglas\ApiBundle\JsonLd\Response;
use Pagerfanta\Adapter\ArrayAdapter;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Acl\Permission\MaskBuilder;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Dunglas\ApiBundle\Controller\ResourceController as BaseResourceController;
use Eliberty\ApiBundle\Xml\Response as XmlResponse;

/**
 * Class ResourceController.
 */
class ResourceController extends BaseResourceController
{

    use ContextResolverTrait;

    /**
     * enable send event because is delagate to the handler
     */
    const NONE = "None";

    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * Gets the Resource associated with the current Request.
     * Must be called before manipulating the resource.
     *
     * @param Request $request
     *
     * @return ResourceInterface
     *
     * @throws \InvalidArgumentException
     */
    protected function getResource(Request $request)
    {
        if ($this->resource) {
            return $this->resource;
        }

        if (!$request->attributes->has('_resource')) {
            throw new \InvalidArgumentException('The current request doesn\'t have an associated resource.');
        }

        $shortName = $request->attributes->get('_resource');
        if (!($this->resource = $this->get('api.resource_collection')->getResourceForShortNameWithVersion($shortName, $this->getApiVersion()))) {
            throw new \InvalidArgumentException(sprintf('The resource "%s" cannot be found.', $shortName));
        }

        return $this->resource;
    }

    /**
     * @param ConstraintViolationListInterface $violations
     *
     * @return Response
     */
    protected function getErrorResponse(ConstraintViolationListInterface $violations)
    {
        $request = $this->get('request_stack')->getCurrentRequest();
        $format  = $this->getContext($request);
        return $this->getResponse(
            $this->get('eliberty.api.serializer')->serialize($violations, $format),
            400,
            []
        );
    }

    /**
     * Finds an object of throws a 404 error.
     *
     * @param ResourceInterface $resource
     * @param string|int        $id
     *
     * @return object
     *
     * @throws NotFoundHttpException
     */
    protected function findOrThrowNotFound(ResourceInterface $resource, $id)
    {
        $resource->isGranted(['VIEW']);
        $item = $this->get('api.data_provider')->getItem($resource, $id, true);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        return $item;
    }

    /**
     * Gets the collection.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     401 = "Returned when the User is not authorized to use this method",
     *   },
     *   tags = {
     *          "collection" = "#0040FF"
     *      }
     * )
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \InvalidArgumentException
     */
    public function cgetAction(Request $request)
    {
        $resource = $this->getResource($request);

        $data = $this->getCollectionData($resource, $request);

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new DataEvent($resource, $data));

        return $this->getSuccessResponse($resource, $data, 200, [], ['request_uri' => $request->getRequestUri()]);
    }

    /**
     * Adds an element to the collection.
     *
     * @param Request $request
     *
     * @return Response|void
     * @throws \NotFoundResourceException
     * @ApiDoc(
     *                         resource = true,
     *                         statusCodes = {
     *                         200 = "Returned when successful",
     *                         401 = "Returned when the User is not authorized to use this method",
     *                         400 = "Returned when the form has errors"
     *                         }
     *                         )
     */
    public function cpostAction(Request $request)
    {
        throw new \NotFoundResourceException('this method is not allowed');
    }

    /**
     * Replaces an element of the collection.
     *
     * @param Request $request
     * @param string  $id
     *
     * @return Response
     * @throws DeserializationException
     * @throws \NotFoundResourceException
     * @ApiDoc(
     *    resource = true,
     *    statusCodes = {
     *                         200 = "Returned when successful",
     *                         401 = "Returned when the User is not authorized to use this method",
     *                         404 = "Returned when the element not found",
     *    }
     * )
     *
     */
    public function putAction(Request $request, $id)
    {
        throw new \NotFoundResourceException('this method is not allowed');
    }

    /**
     * Get an element.
     *
     * @param Request $request
     * @param int     $id
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     401 = "Returned when the User is not authorized to use this method",
     *     404 = "Returned when the element not found",
     *   }
     * )
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function getAction(Request $request, $id)
    {
        $resource = $this->getResource($request);

        $object = $this->findOrThrowNotFound($resource, $id);

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE, new DataEvent($resource, $object));

        return $this->getSuccessResponse($resource, $object);
    }

    /**
     * Deletes an element of the collection.
     *
     * @param Request $request
     * @param string  $id
     * @ApiDoc(
     *                         resource = true,
     *                         statusCodes = {
     *                         204 = "Returned when successful",
     *                         401 = "Returned when the User is not authorized to use this method",
     *                         404 = "Returned when the element not found",
     *                         }
     *                         )
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function deleteAction(Request $request, $id)
    {
        $resource = $this->getResource($request);
        $resource->isGranted(['DELETE']);

        $object    = $this->findOrThrowNotFound($resource, $id);
        $eventName = Events::PRE_DELETE;
        $event     = new DataEvent($resource, $object);
        if ($resource->hasEventListener($eventName)) {
            $eventName  = $resource->getListener($eventName);
            $eventClass = $resource->getListener('eventClass');
            $event      = new $eventClass($object);
        }

        $this->get('event_dispatcher')->dispatch($eventName, $event);

        return $this->getResponse(null, 204);
    }

    /**
     * Normalizes data using the Symfony Serializer.
     *
     * @param ResourceInterface $resource
     * @param array|object      $data
     * @param int               $status
     * @param array             $headers
     * @param array             $additionalContext
     *
     * @return Response
     */
    protected function getSuccessResponse(
        ResourceInterface $resource,
        $data,
        $status = 200,
        array $headers = [],
        array $additionalContext = []
    ) {
        $request = $this->get('request_stack')->getCurrentRequest();
        $dataResponse = $this->get('api.normalizer.item')
            ->normalize($data, $resource, $this->getFractalManager($request), $request);

        return $this->getResponse(
            $dataResponse,
            $status,
            $headers
        );
    }

    /**
     * @param $data
     * @param $status
     * @param $headers
     *
     * @return Response|XmlResponse
     */
    private function getResponse($data = [], $status = 200, $headers = [])
    {
        $request  = $this->container->get('request_stack')->getCurrentRequest();
        $context  = $this->getContext($request);
        if ('xml' === $context) {
            return new XmlResponse($data, $status, $headers);
        }

        return new Response($data, $status, $headers);
    }

    /**
     * Gets collection data.
     *
     * @param ResourceInterface $resource
     * @param Request           $request
     *
     * @return PaginatorInterface
     */
    protected function getCollectionData(ResourceInterface $resource, Request $request)
    {
        $this->resource->isGranted(['VIEW']);
        return $this->get('api.data_provider')->getCollection(
            $resource,
            $request
        );
    }

    /**
     * Gets an element of the collection.
     *
     * @ApiDoc(
     *   resource = true,
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     401 = "Returned when the User is not authorized to use this method",
     *     404 = "Returned when the element not found",
     *   },
     *   tags = {
     *          "embed" = "true"
     *      }
     * )
     *
     * @param Request $request
     * @param int     $id
     * @param string  $embed
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function cgetEmbedAction(Request $request, $id, $embed)
    {
        $resourceEmbed    = $this->get('api.init.filter.embed')->initFilterEmbed($request, $id, $embed);
        $em               = $this->get('doctrine.orm.entity_manager');
        $propertyAccessor = $this->get('property_accessor');
        $resource         = $this->getResource($request);
        $object           = $this->findOrThrowNotFound($resource, $id);
        $parentClassMeta  = $em->getClassMetadata($resource->getEntityClass());

        $resourceEmbed->isGranted(['VIEW'], true);

        $propertyName = $parentClassMeta->hasAssociation($embed) ? $embed : $resourceEmbed->shortName;
        $data         = call_user_func(
            [$object, 'get' . ucfirst($embed)],
            $resourceEmbed->getEmbedParams(strtolower($resource->getShortName()))
        );

        if (is_null($data)) {
            if (!is_null($resourceEmbed->getEmbedAlias($embed))) {
                $propertyName = $resource->getEmbedAlias($embed);
            }
            $data = $propertyAccessor->getValue($object, $propertyName);
        }

        $dataResponse = $this->get('api.helper.apply.criteria')->ApplyCriteria($request, $resourceEmbed, $data);

        if ($dataResponse instanceof ArrayCollection && $dataResponse->count() > 0) {
            $dataResponse = new ArrayPaginator(new ArrayAdapter($dataResponse->toArray()), $request);
        }

        if ($dataResponse instanceof ArrayCollection && $dataResponse->count() === 0) {
            return $this->getResponse();
        }

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new DataEvent($resourceEmbed, $dataResponse));

        return $this->getSuccessResponse($resourceEmbed, $dataResponse);
    }

    /**
     * @param                                  $object
     * @param ConstraintViolationListInterface $violations
     * @param ResourceInterface                $resource
     *
     * @return Response
     */
    protected function formResponse(
        $object,
        ConstraintViolationListInterface $violations,
        ResourceInterface $resource
    ) {
        if (0 === count($violations)) {
            $request = $this->get('request_stack')->getCurrentRequest();
            $codeResponse = in_array($request->getMethod(), ['PUT', 'PATCH']) ? 200 : 201;

            return $this->getSuccessResponse($resource, $object, $codeResponse);
        }

        return $this->getErrorResponse($violations);
    }


    /**
     * @param Request           $request
     * @param ResourceInterface $resource
     *
     * @return object
     *
     * @throws DeserializationException
     */
    protected function getEntity(Request $request, ResourceInterface $resource)
    {
        try {
            $object = $this->get('api.json_ld.normalizer.item')->denormalize(
                $request->getContent(),
                $resource->getEntityClass(),
                'json-ld',
                $resource->getDenormalizationContext()
            );
        } catch (\Exception $e) {
            throw new DeserializationException($e->getMessage(), $e->getCode(), $e);
        }

        return $object;
    }


    /**
     * return string
     */
    protected function getApiVersion() {
        return $this->get('router')->getContext()->getApiVersion();
    }


    /**
     * @param Request $request
     *
     * @return Manager
     */
    protected function getFractalManager(Request $request)
    {
        $serializer = new SerializerFactory();

        $manager = new Manager();
        $manager
            ->setApiClassMetadataFactory($this->get('api.mapping.class_metadata_factory'))
            ->setContextBuilder($this->get('api.json_ld.context_builder'))
            ->setRouter($this->get('api.router'))
            ->setResourceCollection($this->get('api.resource_collection'))
            ->setSerializer($serializer->getSerializer($request))
            ->setGroupsContextChainer($this->get('api.group.context.chainer'));

        return $manager;
    }
}
