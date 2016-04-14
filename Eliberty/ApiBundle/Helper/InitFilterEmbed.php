<?php
namespace Eliberty\ApiBundle\Helper;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Dunglas\ApiBundle\Api\Filter\FilterInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Model\DataProviderChain;
use Eliberty\ApiBundle\Api\Resource;
use Eliberty\ApiBundle\Api\ResourceCollection;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\DateFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\OrderFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Class InitFilterEmbed
 * @package Eliberty\ApiBundle\Helper
 */
class InitFilterEmbed
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ResourceCollection
     */
    private $resourceResolver;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     * @param ResourceCollection $resourceResolver
     * @param ManagerRegistry $managerRegistry
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(
        RequestStack $requestStack,
        ResourceCollection $resourceResolver,
        ManagerRegistry $managerRegistry,
        PropertyAccessorInterface $propertyAccessor
    )
    {
        $this->resourceResolver = $resourceResolver;
        $this->requestStack     = $requestStack;
        $this->request          = $requestStack->getCurrentRequest();
        $this->managerRegistry  = $managerRegistry;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @param $id
     * @param $embed
     * @return ResourceInterface
     */
    public function initFilterEmbed($id, $embed)
    {
        $embedShortname = ucwords(Inflector::singularize($embed));

        /** @var $resourceEmbed ResourceInterface */
        $resourceEmbed = $this->resourceResolver->getResourceForShortName($embedShortname);

        $filter = new EmbedFilter($this->managerRegistry, $this->propertyAccessor);

        $params = !$this->request->request->has('embedParams') ? [
            'embed' => $embed,
            'id'    => $id,
        ] : $this->request->request->get('embedParams');

        $filter->setParameters($params);

        $filter->setRouteName($this->request->get('_route'));

        $resourceEmbed->addFilter($filter);

        return $resourceEmbed;
    }
}
