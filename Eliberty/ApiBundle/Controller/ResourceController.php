<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Controller;

use Doctrine\Common\Inflector\Inflector;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;
use Dunglas\ApiBundle\Doctrine\Orm\SearchFilter as Filter;
use Dunglas\ApiBundle\Event\Events;
use Eliberty\ApiBundle\Doctrine\Orm\ArrayPaginator;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Dunglas\ApiBundle\Event\DataEvent;
use Dunglas\ApiBundle\Exception\DeserializationException;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Model\PaginatorInterface;
use Dunglas\ApiBundle\JsonLd\Response;
use Pagerfanta\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Serializer\Exception\Exception;
use Eliberty\ApiBundle\Doctrine\Orm\EmbedFilter;
use Dunglas\ApiBundle\Controller\ResourceController as BaseResourceController;

/**
 * Class ResourceController.
 */
class ResourceController extends BaseResourceController
{
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
        if (!($this->resource = $this->get('api.resource_collection')->getResourceForShortName($shortName))) {
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
        return new Response(
            $this->get('eliberty.api.hydra.normalizer.violation.list.error')->normalize($violations, 'hydra-error'),
            400
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
        $data     = $this->getCollectionData($resource, $request);
        if ($request->get($this->container->getParameter('api.collection.pagination.page_parameter_name')) &&
            0 === count($data)
        ) {
            throw $this->createNotFoundException();
        }
        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new DataEvent($resource, $data));

        return $this->getSuccessResponse($resource, $data, 200, [], ['request_uri' => $request->getRequestUri()]);
    }

    /**
     * Adds an element to the collection.
     *
     * @param Request $request
     * @ApiDoc(
     *                         resource = true,
     *                         statusCodes = {
     *                         200 = "Returned when successful",
     *                         401 = "Returned when the User is not authorized to use this method",
     *                         400 = "Returned when the form has errors"
     *                         }
     *                         )
     *
     * @return Response
     *
     * @throws DeserializationException
     */
    public function cpostAction(Request $request)
    {
        $resource = $this->getResource($request);

        $object = $this->getEntity($request, $resource);

        $this->get('event_dispatcher')->dispatch(Events::PRE_CREATE_VALIDATION, new DataEvent($resource, $object));

//        $objects = $this->get('api.json_ld.normalizer.item')->getObjectNormalizers();

        $violations = $this->get('validator')->validate($object, null, $resource->getValidationGroups());
//        foreach ($objects as $objToValidation) {
//            $violations->addAll(
//                $this->get('validator')->validate(
//                    $objToValidation->getObject(),
//                    null,
//                    $objToValidation->getValidationGroups()
//                )
//            );
//        }

        return $this->FormResponse($object, $violations, $resource, Events::PRE_CREATE);
    }

    /**
     * Replaces an element of the collection.
     *
     * @param Request $request
     * @param string  $id
     * @ApiDoc(
     *                         resource = true,
     *                         statusCodes = {
     *                         200 = "Returned when successful",
     *                         401 = "Returned when the User is not authorized to use this method",
     *                         404 = "Returned when the element not found",
     *                         }
     *                         )
     *
     * @return Response
     *
     * @throws DeserializationException
     */
    public function putAction(Request $request, $id)
    {
        $resource = $this->getResource($request);
        $object   = $this->findOrThrowNotFound($resource, $id);

        $context                       = $resource->getDenormalizationContext();
        $context['object_to_populate'] = $object;

        try {
            $object = $this->get('api.json_ld.normalizer.item')->denormalize(
                $request->getContent(),
                $resource->getEntityClass(),
                'json-ld',
                $context
            );
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $e->getCode(), $e);
        }

        $this->get('event_dispatcher')->dispatch(Events::PRE_UPDATE_VALIDATION, new DataEvent($resource, $object));

        $violations = $this->get('validator')->validate($object, null, $resource->getValidationGroups());

        return $this->FormResponse($object, $violations, $resource, Events::PRE_UPDATE);
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
        $object   = $this->findOrThrowNotFound($resource, $id);

        $this->get('event_dispatcher')->dispatch(Events::PRE_DELETE, new DataEvent($resource, $object));

        return new Response(null, 204);
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
        $dataResponse = $this->get('api.json_ld.normalizer.item')
            ->normalize($data, 'json-ld', $resource->getNormalizationContext() + $additionalContext);

        return new Response(
            $dataResponse,
            $status,
            $headers
        );
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
        $embedShortname = ucwords(Inflector::singularize($embed));
        $resourceEmbed  = $this->get('api.resource_collection')->getResourceForShortName($embedShortname);

        $iriConverter = $this->get('api.iri_converter');

        $managerRegister = $this->get('doctrine');

        $propertyAccessor = $this->get('property_accessor');

        $filter = new EmbedFilter($managerRegister, $iriConverter, $propertyAccessor);

        $params = !$request->request->has('embedParams')?[
            'embed' => $embed,
            'id'    => $id,
        ] : $request->request->get('embedParams');

        $filter->setParameters($params);

        $filter->setRouteName($request->get('_route'));

        $resourceEmbed->addFilter($filter);

        $resource = $this->getResource($request);

        $object = $this->findOrThrowNotFound($resource, $id);

        $data = $propertyAccessor->getValue($object, $embed);

        $dataPaginator = new ArrayPaginator(new ArrayAdapter($data->toArray()), $request);

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new DataEvent($resourceEmbed, $dataPaginator));

        return $this->getSuccessResponse($resourceEmbed, $dataPaginator);
    }

    /**
     * @param $object
     * @param ConstraintViolationListInterface $violations
     * @param ResourceInterface                $resource
     * @param string                           $event
     *
     * @return Response
     */
    protected function formResponse(
        $object,
        ConstraintViolationListInterface $violations,
        ResourceInterface $resource,
        $event
    ) {
        if (0 === count($violations)) {
            // Validation succeed
            $this->get('event_dispatcher')->dispatch($event, new DataEvent($resource, $object));

            return $this->getSuccessResponse($resource, $object, 201);
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
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $e->getCode(), $e);
        }

        return $object;
    }
}
