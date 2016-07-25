<?php
/**
 *
 */
namespace Eliberty\ApiBundle\Context;

use Eliberty\ApiBundle\Resolver\BaseResolver;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * Class GroupsContextChainer
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContextChainer
{

    /**
     * @var GroupsContextLoader
     */
    protected $contextLoader;

    /**
     * @var string
     */
    protected $groupName;

    /**
     * GroupsContextChainer constructor.
     *
     * @param GroupsContextLoader $contextLoader
     */
    public function __construct(GroupsContextLoader $contextLoader)
    {
        $this->contextLoader = $contextLoader;
    }

    /**
     * @param RequestStack $requestStack
     */
    public function setRequestStack(RequestStack $requestStack) {
        $this->setGroupName($this->request->headers->get('e-serializer-group', null));
    }

    /**
     * @param $entityName
     *
     * @param $version
     *
     * @return array
     */
    public function getContext($entityName, $version) {
        return $this->contextLoader->getContexts($entityName, $version);
    }

    /**
     * @param $data
     *
     * @return array
     */
    public function getGroupsContexts($data) {
        $grpContexts = [];
        foreach ($data as $group => $property) {
            $grpContexts[] = new GroupsContext($property, $group);
        }

        return $grpContexts;
    }

    /**
     * @param       $shortname
     * @param array $data
     *
     * @return array
     */
    public function serialize($shortname, $data = [], $version) {
        $groupsData = $this->getContext($shortname, $version);

        if (empty($groupsData) || null === $this->groupName) {
            return $data;
        }

        $dataResponse = [];
//        /** inverse array for respect priority */
        $groups = $this->getGroupsContexts($groupsData);
        /** @var GroupsContext $group */
        foreach ($groups as $group) {
            if ($group->getGroupName() !== $this->groupName || null === $group->getProperties()) {
                continue;
            }

            switch ($group->getStrategy()) {
                case GroupsContext::STRATEGY_REMOVING:
                    $dataResponse = $this->excludingStrategy($group, $data);
                    break;
                case GroupsContext::STRATEGY_ADDING:
                    $dataResponse = $this->addingStrategy($group, $data);
                    break;
                default:
                    $dataResponse = $data;
                    break;
            }
        }

        return !empty($dataResponse) ? $dataResponse : $data;
    }

    /**
     * @param GroupsContext $group
     * @param               $data
     *
     * @return array
     */
    protected function excludingStrategy(GroupsContext $group, $data) {
        return array_diff_key($data, $group->getProperties());
    }

    /**
     * @param GroupsContext $group
     * @param               $data
     *
     * @return array
     */
    protected function addingStrategy(GroupsContext $group, $data) {
        $properties = $group->getProperties();
        array_walk($data, function($elem, $key) use (&$properties){
            if (false !== stristr($key,'@')) {
                $properties[$key] = null;
            }
        });

        return array_intersect_key($data, $properties);
    }

    /**
     * @return mixed
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @param mixed $groupName
     *
     * @return $this
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;

        return $this;
    }
}