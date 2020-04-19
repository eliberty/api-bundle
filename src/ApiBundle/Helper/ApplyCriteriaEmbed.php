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
class ApplyCriteriaEmbed
{
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
     * @var array
     */
    private $mappingFilterVar = [
        'boolean' => FILTER_VALIDATE_BOOLEAN,
        'integer' => FILTER_VALIDATE_INT,
        'float' => FILTER_VALIDATE_FLOAT,
    ];

    /**
     * @param EntityManagerInterface $em
     * @param ResourceCollection $resourceResolver
     * @param DataProviderChain $dataProviderChain
     */
    public function __construct(
        EntityManagerInterface $em,
        ResourceCollection $resourceResolver,
        DataProviderChain $dataProviderChain
    ) {
        $this->em                = $em;
        $this->resourceResolver  = $resourceResolver;
        $this->dataProviderChain = $dataProviderChain;
    }

    /**
     * @param Request           $request
     * @param ResourceInterface $resourceEmbed
     * @param                   $data
     *
     * @return \Doctrine\Common\Collections\Collection|PersistentCollection
     */
    public function ApplyCriteria(Request $request, ResourceInterface $resourceEmbed, $data) {
        if ($data instanceof PersistentCollection && $data->count() > 0) {
            $embedClassMeta =  $this->em->getClassMetadata($resourceEmbed->getEntityClass());
            $criteria = Criteria::create();
            foreach ($resourceEmbed->getFilters() as $filter) {
                if ($filter instanceof FilterInterface) {
                    $this->applyFilter($request, $filter, $criteria, $embedClassMeta);
                    $data = $data->matching($criteria);
                }
            }
        }

        return $data;
    }

    /**
     * @param Request         $request
     * @param FilterInterface $filter
     * @param Criteria        $criteria
     * @param ClassMetadata   $embedClassMeta
     *
     * @return null
     */
    protected function applyFilter(
        Request $request,
        FilterInterface $filter ,
        Criteria $criteria,
        ClassMetadata $embedClassMeta
    ) {
        $properties = $filter->getRequestProperties($request);
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
                    $associationTargetClass = $embedClassMeta->getAssociationTargetClass($name);
                    $propertyResource  = $this->resourceResolver->getResourceForEntity($associationTargetClass);
                    $propertyObj = $this->dataProviderChain->getItem($propertyResource, (int)$propertie['value'], true);
                    if ($propertyObj && $propertyResource instanceof ResourceInterface) {
                        $whereCriteria = $expCriterial->in($name, [$propertyObj]);
                        $criteria->where($whereCriteria);
                    }
                } else if ($embedClassMeta->hasField($name)) {
                    $fieldMapping =$embedClassMeta->getFieldMapping($name);
                    $type  = isset($fieldMapping['type']) ? $fieldMapping['type'] : null;
                    $value =  isset($this->mappingFilterVar[$type]) ? filter_var($propertie['value'], $this->mappingFilterVar[$type]) : $propertie['value'] ;
                    $whereCriteria = isset($propertie['precision']) && $propertie['precision'] === 'exact' ?
                        $expCriterial->eq($name, $value) :
                        $expCriterial->contains($name, $propertie['value']);
                    $criteria->where($whereCriteria);
                }
            }
        }
    }
}
