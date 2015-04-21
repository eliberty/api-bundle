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

use Dunglas\JsonLdApiBundle\Controller\ResourceController as BaseResourceController;

use Doctrine\Common\Inflector\Inflector;
use Dunglas\JsonLdApiBundle\Doctrine\Orm\Filter;
use Dunglas\JsonLdApiBundle\Event\Events;
use Dunglas\JsonLdApiBundle\Event\ObjectEvent;
use Dunglas\JsonLdApiBundle\Exception\DeserializationException;
use Dunglas\JsonLdApiBundle\Api\ResourceInterface;
use Dunglas\JsonLdApiBundle\Model\PaginatorInterface;
use Dunglas\JsonLdApiBundle\JsonLd\Response;
use Eliberty\ApiBundle\Doctrine\Orm\MappingsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Serializer\Exception\Exception;

/**
 * Class ResourceController
 * @package Eliberty\ApiBundle\Controller
 */
class ResourceController extends Controller
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
        return new Response($this->get('serializer')->normalize($violations, 'hydra-error'), 400);
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

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new ObjectEvent($resource, $data));

        return $this->getSuccessResponse($resource, $data);
    }

    /**
     * Adds an element to the collection.
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws DeserializationException
     */
    public function cpostAction(Request $request)
    {
        $resource = $this->getResource($request);
        try {
            $object = $this->get('serializer')->deserialize(
                $request->getContent(),
                $resource->getEntityClass(),
                'json-ld',
                $resource->getDenormalizationContext()
            );
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $e->getCode(), $e);
        }

        $this->get('event_dispatcher')->dispatch(Events::PRE_CREATE_VALIDATION, new ObjectEvent($resource, $object));

        $violations = $this->get('validator')->validate($object, null, $resource->getValidationGroups());
        if (0 === count($violations)) {
            // Validation succeed
            $this->get('event_dispatcher')->dispatch(Events::PRE_CREATE, new ObjectEvent($resource, $object));

            return $this->getSuccessResponse($resource, $object, 201);
        }

        return $this->getErrorResponse($violations);
    }

    /**
     * Gets an element of the collection.
     *
     * @param Request $request
     * @param int     $id
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

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE, new ObjectEvent($resource, $object));

        return $this->getSuccessResponse($resource, $object);
    }

    /**
     * Replaces an element of the collection.
     *
     * @param Request $request
     * @param string  $id
     *
     * @return Response
     *
     * @throws DeserializationException
     */
    public function putAction(Request $request, $id)
    {
        $resource = $this->getResource($request);
        $object = $this->findOrThrowNotFound($resource, $id);

        $context = $resource->getDenormalizationContext();
        $context['object_to_populate'] = $object;

        try {
            $object = $this->get('serializer')->deserialize(
                $request->getContent(),
                $resource->getEntityClass(),
                'json-ld',
                $context
            );
        } catch (Exception $e) {
            throw new DeserializationException($e->getMessage(), $e->getCode(), $e);
        }

        $this->get('event_dispatcher')->dispatch(Events::PRE_UPDATE_VALIDATION, new ObjectEvent($resource, $object));

        $violations = $this->get('validator')->validate($object, null, $resource->getValidationGroups());
        if (0 === count($violations)) {
            // Validation succeed
            $this->get('event_dispatcher')->dispatch(Events::PRE_UPDATE, new ObjectEvent($resource, $object));

            return $this->getSuccessResponse($resource, $object, 202);
        }

        return $this->getErrorResponse($violations);
    }

    /**
     * Deletes an element of the collection.
     *
     * @param Request $request
     * @param string  $id
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function deleteAction(Request $request, $id)
    {
        $resource = $this->getResource($request);
        $object = $this->findOrThrowNotFound($resource, $id);

        $this->get('event_dispatcher')->dispatch(Events::PRE_DELETE, new ObjectEvent($resource, $object));

        return new Response(null, 204);
    }


    /**
     * Normalizes data using the Symfony Serializer.
     *
     * @param ResourceInterface $resource
     * @param array|object      $data
     * @param int               $status
     * @param array             $headers
     *
     * @return Response
     */
    protected function getSuccessResponse(ResourceInterface $resource, $data, $status = 200, array $headers = [])
    {
        return new Response(
            $this->get('api.json_ld.normalizer.item')->normalize($data, 'json-ld', $resource->getNormalizationContext()),
            $status,
            $headers
        );
    }

    /**
     * Gets collection data.
     *
     * @param ResourceInterface $resource
     * @param Request $request
     *
     * @return PaginatorInterface
     */
    protected function getCollectionData(ResourceInterface $resource, Request $request)
    {
        $page = (int) $request->get('page', 1);

        $itemsPerPage = $this->container->getParameter('api.default.items_per_page');
        $defaultOrder = $this->container->getParameter('api.default.order');
        $order = $defaultOrder ? ['id' => $defaultOrder] : [];

        return $this->get('api.data_provider')->getCollection(
            $resource,
            $request->query->getIterator()->getArrayCopy(),
            $order,
            $page,
            $itemsPerPage
        );
    }


    /**
     * Gets an element of the collection.
     *
     * @param Request $request
     * @param int     $id
     * @param string  $mappings
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function cgetMappingsAction(Request $request, $id, $mappings = null)
    {
        $resource = $this->getResource($request);
        $mappingShortname = ucwords(Inflector::singularize($mappings));

        $resourceMapping = $this->get('api.resource_collection')->getResourceForShortName($mappingShortname);

        $page = (int) $request->get('page', 1);

        $itemsPerPage = $this->container->getParameter('api.default.items_per_page');
        $defaultOrder = $this->container->getParameter('api.default.order');
        $order = $defaultOrder ? ['id' => $defaultOrder] : [];

        $dataProvider = $this->get('api.data_provider');

        $propertyAccessor = $this->get('property_accessor');

        $filterName = strtolower($resource->getShortName());

        $filter = new MappingsFilter($dataProvider,$propertyAccessor, $filterName);

        $filter->setParameters([
            'mappings' => $mappings,
            'id'       => $id
        ]);

        $filter->setRouteName($request->get('_route'));

        $resourceMapping->addFilter($filter);

        $data = $dataProvider->getCollection(
            $resourceMapping,
            [$filterName => $id],
            $order,
            $page,
            $itemsPerPage
        );

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new ObjectEvent($resourceMapping, $data));

        return $this->getSuccessResponse($resourceMapping, $data);
    }

}
