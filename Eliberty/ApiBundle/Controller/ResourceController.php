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
use Dunglas\ApiBundle\Doctrine\Orm\Filter;
use Dunglas\ApiBundle\Event\Events;
use FOS\RestBundle\Controller\FOSRestController;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Dunglas\ApiBundle\Event\ObjectEvent;
use Dunglas\ApiBundle\Exception\DeserializationException;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Model\PaginatorInterface;
use Dunglas\ApiBundle\JsonLd\Response;
use Eliberty\ApiBundle\Doctrine\Orm\EmbedFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Serializer\Exception\Exception;
use FOS\RestBundle\Controller\Annotations;

/**
 * Class ResourceController.
 */
class ResourceController extends FOSRestController
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
     * @Annotations\QueryParam(name="orderby",  default={"id"="asc"}, nullable=true, description="Way to sort the rows in the result set.")
     * @Annotations\QueryParam(name="embed",  default="", nullable=true, description="Include resources within other resources.")
     * @Annotations\QueryParam(name="page",  requirements="\d+", nullable=true, default="1", description="How many page start to return.")
     * @Annotations\QueryParam(name="perpage",  requirements="\d+", nullable=true, default="10", description="How many contact return per page.")
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

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new ObjectEvent($resource, $data));

        return $this->getSuccessResponse($resource, $data);
    }

    /**
     * Adds an element to the collection.
     *
     * @param Request $request
     * @ApiDoc(
     *                         resource = true,
     *                         statusCodes = {
     *                         200 = "Returned when successful",
     *                         400 = "Returned when the form has errors"
     *                         }
     *                         )
     *
     * @Annotations\QueryParam(name="embed", default="addresses,rights", description="How man y notes to return.")
     *
     * @return Response
     *
     * @throws DeserializationException
     */
    public function cpostAction(Request $request)
    {
        $resource = $this->getResource($request);
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
     * @ApiDoc(
     *   statusCodes = {
     *     200 = "Returned when successful",
     *     404 = "Returned when the note is not found"
     *   }
     * )
     * @Annotations\QueryParam(name="embed", default="addresses,rights", description="How many notes to return.")
     *
     * @return Response
     *
     * @throws NotFoundHttpException
     * @throws \InvalidArgumentException
     */
    public function getAction(Request $request, $id)
    {
        $resource = $this->getResource($request);
        $object   = $this->findOrThrowNotFound($resource, $id);

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE, new ObjectEvent($resource, $object));

        return $this->getSuccessResponse($resource, $object);
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
     *                         400 = "Returned when the form has errors"
     *                         }
     *                         )
     *
     * @Annotations\QueryParam(name="embed", default="addresses,rights", description="How man y notes to return.")
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
     * @ApiDoc(
     *                         description="Delete Contact",
     *                         resource=true
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
     * @param Request           $request
     *
     * @return PaginatorInterface
     */
    protected function getCollectionData(ResourceInterface $resource, Request $request)
    {
        $page = (int) $request->get('page', 1);

        $itemsPerPage = $this->container->getParameter('api.default.items_per_page');
        $perpage      = (int) $request->get('perpage', $itemsPerPage);
        $defaultOrder = $request->get('orderby', []);
        $orderBy      = $request->get('order', $defaultOrder);
        $order        = $orderBy ? (array) json_decode(str_replace('=', ':', $defaultOrder)) : [];

        return $this->get('api.data_provider')->getCollection(
            $resource,
            $request->query->getIterator()->getArrayCopy(),
            $order,
            $page,
            $perpage
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
     *   },
     *   tags = {
     *          "collection" = "#0040FF"
     *      }
     * )
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

        $resource         = $this->getResource($request);
        $embedShortname   = ucwords(Inflector::singularize($embed));

        $resourceEmbed    = $this->get('api.resource_collection')->getResourceForShortName($embedShortname);

//        $resourceEmbed  = $this->get('api.resource_collection')->getResourceForShortName($embedShortname);

        $page = (int) $request->get('page', 1);

        $itemsPerPage = $this->container->getParameter('api.default.items_per_page');
        $defaultOrder = $this->container->getParameter('api.default.order');
        $order        = $defaultOrder ? ['id' => $defaultOrder] : [];

        $iriConverter = $this->get('api.iri_converter');

        $dataProvider = $this->get('api.data_provider');

        $propertyAccessor = $this->get('property_accessor');

        $filterName = strtolower($resource->getShortName());

        $filter = new EmbedFilter($iriConverter, $propertyAccessor, $filterName);

        $filter->setParameters([
            'embed' => $embed,
            'id'    => $id,
        ]);

        $filter->setRouteName($request->get('_route'));

        $resourceEmbed->addFilter($filter);

        $data = $dataProvider->getCollection(
            $resourceEmbed,
            [$filterName => $id],
            $order,
            $page,
            $itemsPerPage
        );

        $this->get('event_dispatcher')->dispatch(Events::RETRIEVE_LIST, new ObjectEvent($resourceEmbed, $data));

        return $this->getSuccessResponse($resourceEmbed, $data);
    }
}
