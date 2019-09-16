<?php

namespace Eliberty\ApiBundle\Controller;

use Doctrine\ORM\EntityNotFoundException;
use Dunglas\ApiBundle\Event\Events;
use FOS\RestBundle\Controller\FOSRestController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
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
     * @param $exception
     * @return JsonResponse
     * @throws \Exception
     */
    private function processException($exception) {
        $message = sprintf(
            '%s: An error occurred while processing your request (complete stack trace available on log file)',
            (new \DateTime())->format('Y-m-d H:i:s')
        );
        $this->get('logger')->critical($exception->getMessage());

        return new JsonResponse(['error' => $message], '400');
    }

    /**
     * Edit entity.
     *
     * @param mixed $id
     *
     * @param string $eventName
     * @param null $object
     * @return Response
     */
    public function handleUpdateRequest($id, $eventName = Events::PRE_UPDATE, $object = null)
    {
        $request  = $this->get('request_stack')->getCurrentRequest();
        $resource = $this->getResource($request);

        $resource->isGranted(['EDIT'], true);

        if (null === $object) {
            $object = $this->findOrThrowNotFound($resource, $id);
        }

        try {
            $form = $this->processForm($object);
            $violations = new ConstraintViolationList($this->constraintViolation($form->getErrors(true)));

            return $this->formResponse($object, $violations, $resource, $eventName);
        } catch (\Exception $e) {
            return $this->processException($e);
        }
    }

    /**
     * Create new.
     * @param string $eventName
     * @param null $entity
     * @return Response
     */
    public function handleCreateRequest($eventName = Events::PRE_CREATE, $entity = null)
    {
        $request  = $this->get('request_stack')->getCurrentRequest();
        $resource = $this->getResource($request);

        $resource->isGranted(['CREATE'], true);

        if (null === $entity) {
            $entityName = $this->get('doctrine')->getManager()->getClassMetadata($resource->getEntityClass())->getName();
            $entity     = new $entityName;
        }

        try {
            $form = $this->processForm($entity);
            $violations = new ConstraintViolationList($this->constraintViolation($form->getErrors(true)));

            return $this->formResponse($entity, $violations, $resource, $eventName);
        } catch (\Exception $e) {
            return $this->processException($e);
        }
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
        $request = $this->get('request_stack')->getCurrentRequest();
        $data    = $request->request->all();

        // save fixed values for named form
        $request->request->set($this->getForm()->getName(), $data);
    }

    /**
     * @return FormInterface
     */
    protected function getForm()
    {
        return $this->get('api.form.resolver')->resolve($this->getEntityName(), $this->getApiVersion());
    }

    /**
     * @return ApiFormHandler
     */
    protected function getFormHandler()
    {
        $resolver = $this->get('api.handler.resolver');
        return $resolver->resolve($this->getEntityName(), $this->getApiVersion());
    }

    /**
     * @return string
     */
    protected function getEntityName()
    {
        return $this->entityName;
    }
}
