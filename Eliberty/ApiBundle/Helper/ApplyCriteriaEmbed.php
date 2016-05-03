<?php
namespace Eliberty\ApiBundle\Helper;

use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\PersistentCollection;
use Dunglas\ApiBundle\Api\Filter\FilterInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Model\DataProviderChain;
use Eliberty\ApiBundle\Api\ResourceCollection;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\OrderFilter;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Common\Inflector\Inflector;

/**
 * Class ApplyCriteriaEmbed
 * @package Eliberty\ApiBundle\Helper
 */
class ApplyCriteriaEmbed {

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var EntityManagerInterface
     */
    private $em;
    /**
     * @var ResourceCollection
     */
    private $resourceResolver;
    /**
     * @var DataProviderChain
     */
    private $dataProviderChain;
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     * @param EntityManagerInterface $em
     * @param ResourceCollection $resourceResolver
     * @param DataProviderChain $dataProviderChain
     */
    public function __construct(
        RequestStack $requestStack,
        EntityManagerInterface $em,
        ResourceCollection $resourceResolver,
        DataProviderChain $dataProviderChain
    ) {
        $this->em                = $em;
        $this->resourceResolver  = $resourceResolver;
        $this->dataProviderChain = $dataProviderChain;
        $this->requestStack      = $requestStack;
        $this->request           = $requestStack->getCurrentRequest();
    }

    /**
     * @param ResourceInterface $resourceEmbed
     * @param $data
     * @return \Doctrine\Common\Collections\Collection|PersistentCollection
     */
    public function ApplyCriteria(ResourceInterface $resourceEmbed, $data) {
        if ($data instanceof PersistentCollection && $data->count() > 0) {
            $embedClassMeta =  $this->em->getClassMetadata($resourceEmbed->getEntityClass());
            $criteria = Criteria::create();
            foreach ($resourceEmbed->getFilters() as $filter) {
                if ($filter instanceof FilterInterface) {
                    $this->applyFilter($filter, $criteria, $embedClassMeta);
                }
            }
            $data = $data->matching($criteria);
        }

        return $data;
    }

    /**
     * @param FilterInterface $filter
     * @param Criteria $criteria
     * @param ClassMetadata $embedClassMeta
     * @return null
     */
    protected function applyFilter(
        FilterInterface $filter ,
        Criteria $criteria,
        ClassMetadata $embedClassMeta
    ) {
        $properties = $filter->getRequestProperties($this->requestStack->getCurrentRequest());
        if ($filter instanceof OrderFilter && !empty($properties)) {
            $criteria->orderBy($properties);
            return null;
        }
        if ($filter instanceof SearchFilter) {
            foreach ($properties as $name => $propertie) {
                if (in_array($name, $embedClassMeta->getIdentifier())) {
                    continue;
                }
                $expCriterial = Criteria::expr();
                if ($embedClassMeta->hasAssociation($name)) {
                    $propertyResource  = $this->resourceResolver->getResourceForShortName(ucwords(Inflector::singularize($name)));
                    $propertyObj = $this->dataProviderChain->getItem($propertyResource, (int)$propertie['value'], true);
                    if ($propertyObj) {
                        $whereCriteria = $expCriterial->in($name, [$propertyObj]);
                        $criteria->where($whereCriteria);
                    }
                } else {
                    $whereCriteria = isset($propertie['precision']) && $propertie['precision'] === 'exact' ?
                        $expCriterial->eq($name, $propertie['value']) :
                        $expCriterial->contains($name, $propertie['value']);
                    $criteria->where($whereCriteria);
                }
            }
        }
    }
}
