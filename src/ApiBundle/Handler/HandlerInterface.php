<?php

namespace Eliberty\ApiBundle\Handler;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

interface HandlerInterface
{


    /**
     * execute process
     * @param $entity
     * @return mixed
     */
    public function process($entity);

    /**
     * success process
     * @param $entity
     * @return mixed
     */
    public function onSuccess($entity);

}