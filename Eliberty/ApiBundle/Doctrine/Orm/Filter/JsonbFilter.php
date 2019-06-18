<?php

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\AbstractFilter;
use Symfony\Component\HttpFoundation\Request;
use iter;

/**
 * Filters the Jsonb column.
 *
 */
class JsonbFilter extends AbstractFilter
{
    /**
     * {@inheritdoc}
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        $jsonbFieldNames = $this->getJsonbFieldNames($resource);

        foreach ($this->extractProperties($request) as $property => $values) {
            // Expect $property to be a jsonb field
            if (!isset($jsonbFieldNames[$property]) /* || !$this->isPropertyEnabled($property)  */) {
                continue;
            }

            foreach ($values as $key => $value) {
                $valueList = \explode(',', $value);
                $conditions = $queryBuilder->expr()->orX();
                $filters = $queryBuilder->expr()->andX();
                $empty = $queryBuilder->expr()->orX();

                foreach ($valueList as $value) {
                    if ($value === 'UNSET') {
                        $empty->add(sprintf("JSONCHILD(o.%s, '%s') = '[]'", $property, $key));
                        $empty->add(sprintf("o.%s IS NULL", $property));
                    } else {
                        $expression = sprintf('{"%s": ["%s"]}', $key, $value);
                        $filters->add(sprintf("JSON_CONTAINS(o.%s, '%s') = TRUE", $property, $expression));
                    }
                }

                if ($filters->count() > 0) {
                    $conditions->add($filters);
                }

                if ($empty->count() > 0) {
                    $conditions->add($empty);
                }

                if ($conditions->count() > 0) {
                    $queryBuilder->andWhere($conditions);
                }
            }
        }
    }
    
    /**
     * Gets names of fields with a jsonb type.
     *
     * @param ResourceInterface $resource
     *
     * @return array
     */
    private function getJsonbFieldNames(ResourceInterface $resource)
    {
        $classMetadata    = $this->getClassMetadata($resource);
        $jsonbFieldNames = [];

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if ($classMetadata->getTypeOfField($fieldName) === 'json_array_text') {
                $jsonbFieldNames[$fieldName] = true;
            }
        }

        return $jsonbFieldNames;
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
}
