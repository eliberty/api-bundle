<?php
namespace Eliberty\ApiBundle\Context;

use Doctrine\Common\Inflector\Inflector;
use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;

/**
 * Class GroupsContextResolver
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContextResolver extends BaseResolver
{
    /**
     * @param GroupsContextInterface $groupsContext
     * @param                        $priority
     */
    public function add(GroupsContextInterface $groupsContext, $priority)
    {
        $resource  = $groupsContext->getResource();
        $shortname = strtolower($resource->getShortName());
        $position    = isset($this->mapping[$resource->getVersion()][$shortname][$priority]) ?
            $priority + 1:
            $priority
        ;
        $this->mapping[$resource->getVersion()][$shortname][$position] = $groupsContext;
        ksort($this->mapping[$resource->getVersion()][$shortname], SORT_ASC);
    }

    /**
     * @param $entityName
     *
     * @return array
     */
    public function resolve($entityName)
    {
        $entityName = strtolower($entityName);
        $key        = Inflector::singularize($entityName);
        if (isset($this->mapping[$this->version][$key])) {
            return $this->mapping[$this->version][$key];
        }

        return [];
    }
}