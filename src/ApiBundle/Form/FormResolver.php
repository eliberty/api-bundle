<?php
namespace Eliberty\ApiBundle\Form;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\Form\AbstractType;

/**
 * Class FormResolver
 * @package Eliberty\ApiBundle\Form
 */
class FormResolver
{
    /**
     * @param AbstractType $form
     * @param $serviceId
     */
    public function add(AbstractType $form, $serviceId)
    {
        $this->mapping[$serviceId] = $form;
    }

    /**
     * @param $entityName
     * @param $version
     *
     * @return mixed
     * @throws \Exception
     */
    public function resolve($entityName, $version)
    {
        $serviceId = 'form.'.strtolower($entityName).'.api.'.$version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('form not found for '.$serviceId);
    }
}
