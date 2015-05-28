<?php

namespace Eliberty\ApiBundle\Controller;

use Doctrine\ORM\EntityNotFoundException;
use Dunglas\ApiBundle\Event\Events;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use FOS\RestBundle\Util\Codes;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

abstract class FormResourceController extends ResourceController
{

    /**
     * Entityname
     * @var string
     */
    protected $entityName ;

    /**
     * Edit entity.
     *
     * @param mixed $id
     *
     * @return Response
     */
    public function handleUpdateRequest($id)
    {
        $resource = $this->getResource($this->container->get('request'));

        $object   = $this->findOrThrowNotFound($resource, $id);

        $form = $this->processForm($object);

        $violations = new ConstraintViolationList($this->constraintViolation($form->getErrors(true)));

        return $this->formResponse($object, $violations, $resource, Events::PRE_UPDATE);
    }

    /**
     * Create new.
     *
     * @param mixed $_ [optional] Arguments will be passed to createEntity method
     *
     * @return Response
     */
    public function handleCreateRequest($_ = null)
    {
        $request = $this->container->get('request');
        $resource = $this->getResource($request);
        $entityName = $this->get('doctrine')->getManager()->getClassMetadata($resource->getEntityClass())->getName();
        $entity      = new $entityName;

        $form = $this->processForm($entity);

        $violations = new ConstraintViolationList($this->constraintViolation($form->getErrors(true)));

        return $this->formResponse($entity, $violations, $resource, Events::PRE_CREATE);
    }

    /**
     * @param FormErrorIterator $errors
     * @return array
     */
    protected function constraintViolation(FormErrorIterator $errors)
    {
        $violations = [];
        foreach ($errors as $error) {
            if ($error->getCause() instanceof ConstraintViolation) {
                $violations[] = $error->getCause();
            }
        }

        return $violations;
    }

    /**
     * Process form.
     *
     * @param mixed $entity
     *
     * @return bool
     */
    protected function processForm($entity)
    {
        $this->fixRequestAttributes();

        return $this->getFormHandler()->process($entity);
    }

    /**
     * Convert REST request to format applicable for form.
     */
    protected function fixRequestAttributes()
    {
        $apiVersion = $this->get('router')->getContext()->getApiVersion();
        $request    = $this->container->get('request');

        $data = $request->request->all();

        // save fixed values for named form
        $request->request->set($this->getForm()->getName(), $data);
    }

    /**
     * @return FormInterface
     */
    protected function getForm()
    {
        return $this->get('form.'.$this->getEntityName().'.api.'.$this->get('router')->getContext()->getApiVersion());
    }

    /**
     * @return ApiFormHandler
     */
    protected function getFormHandler()
    {
        return $this->get('handler.'.$this->getEntityName().'.api.'.$this->get('router')->getContext()->getApiVersion());
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        return $this->entityName;
    }
}
