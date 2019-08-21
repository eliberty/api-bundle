<?php
/**
 * Created by: xav On Date: 17/10/2015
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Types\Type;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\AbstractFilter;
use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Util\QueryNameGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Class InListFilter
 */
class InListFilter extends SearchFilter
{
    /**
     * @param ResourceInterface $resource
     * @param QueryBuilder      $queryBuilder
     * @param Request           $request
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        foreach ($this->extractProperties($request) as $property => $value) {
            if (!$this->isPropertyEnabled($property) || !isset($value['in'])) {
                continue;
            }

            $alias = 'o';
            $field = $property;

            $metadata = $this->getClassMetadata($resource);

            // Manage filter on embed field (1toN association only)

            if ($this->isPropertyNested($property)) {
                $propertyParts = $this->splitPropertyParts($property);

                $parentAlias = $alias;

                foreach ($propertyParts['associations'] as $association) {
                    $alias = QueryNameGenerator::generateJoinAlias($association);
                    $queryBuilder->join(sprintf('%s.%s', $parentAlias, $association), $alias);
                    $parentAlias = $alias;
                }

                $field = $propertyParts['field'];

                $metadata = $this->getNestedMetadata($resource, $propertyParts['associations']);
            }

            // TODO Add support for NtoN association using conditions :
            //  if ($metadata->isSingleValuedAssociation($field)) { ... }
            //  if ($metadata->isCollectionValuedAssociation($field)) { ... }

            if ($metadata->hasField($field)) {
                $valueParameter = QueryNameGenerator::generateParameterName($field);

                $queryBuilder
                    ->andWhere(sprintf('%s.%s IN (:%s)', $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, explode(',', $value['in']));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(ResourceInterface $resource)
    {
        $description = [];
        foreach ($this->getClassMetadata($resource)->getFieldNames() as $fieldName) {
            if ($this->isPropertyEnabled($fieldName)) {
                $description += $this->getFilterDescription($fieldName);
            }
        }

        return $description;
    }

    /**
     * Gets filter description.
     * @param $fieldName
     * @return array
     */
    private function getFilterDescription($fieldName)
    {
        return [
            sprintf('%s[%s]', $fieldName, 'in') => [
                'property' => $fieldName,
                'type' => '\array',
                'required' => false,
                'description' => 'Find the id into the collection',
                'requirement'  => '[a-zA-Z0-9-]+'
            ],
        ];
    }

}
