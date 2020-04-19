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
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\OrderFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\SearchFilter;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Common\Inflector\Inflector;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;

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
     * @var RouterInterface
     */
    private $router;


    /**
     * @param RouterInterface $router
     * @param ResourceCollection $resourceResolver
     * @param ManagerRegistry $managerRegistry
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(
        RouterInterface $router,
        ResourceCollection $resourceResolver,
        ManagerRegistry $managerRegistry,
        PropertyAccessorInterface $propertyAccessor
    )
    {
        $this->resourceResolver = $resourceResolver;
        $this->managerRegistry  = $managerRegistry;
        $this->propertyAccessor = $propertyAccessor;
        $this->router           = $router;
    }

    /**
     * @param Request $request
     * @param         $id
     * @param         $embed
     *
     * @return ResourceInterface
     */
    public function initFilterEmbed(Request $request ,$id, $embed)
    {
        $embedShortname = ucwords(Inflector::singularize($embed));

        /** @var $resourceEmbed ResourceInterface */
        $resourceEmbed = $this->resourceResolver->getResourceForShortName($embedShortname, $this->router->getContext()->getApiVersion());

        $filter = new EmbedFilter($this->managerRegistry, $this->propertyAccessor);

        $params = !$request->request->has('embedParams') ? [
            'embed' => $embed,
            'id'    => $id,
        ] : $request->request->get('embedParams');

        $filter->setParameters($params);

        $filter->setRouteName($request->get('_route'));

        $resourceEmbed->addFilter($filter);

        return $resourceEmbed;
    }
}
