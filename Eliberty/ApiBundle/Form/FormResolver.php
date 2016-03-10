<?php
namespace Eliberty\ApiBundle\Form;

use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\Form\AbstractType;

/**
 * Class FormResolver
 * @package Eliberty\ApiBundle\Form
 */
class FormResolver extends BaseResolver
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
     * @return object
     * @throws \Exception
     */
    public function resolve($entityName)
    {
        $serviceId = 'form.'.strtolower($entityName).'.api.'.$this->version;
        if (isset($this->mapping[$serviceId])) {
            return $this->mapping[$serviceId];
        }

        throw new \Exception('form not found for '.$serviceId);
    }
}
