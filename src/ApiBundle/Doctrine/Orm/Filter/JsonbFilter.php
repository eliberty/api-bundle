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
 * USAGE
 * ------
 * Format: property[key] = values
 *
 * property: the database field (in jsonb format)
 * key: the name of an attribute inside the jsonb object (must be an array)
 * values: comma separated list of string values to filter inside property[key] array
 *
 * EMPTY is a special value used to match empty property[key] arrays
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
            if (!isset($jsonbFieldNames[$property])) {
                continue;
            }

            foreach ($values as $key => $value) {
                $conditions = $this->parseValues($queryBuilder, $property, $key, $value);

                if ($conditions !== null) {
                    $queryBuilder->andWhere($conditions);
                }
            }
        }
    }

    /**
     * Parses values givent in property[key] attribute.
     *
     * @param QueryBuilder $queryBuilder
     * @param string $property
     * @param string $key
     * @param string $values
     *
     * @return Expr\Orx|null
     */
    private function parseValues(QueryBuilder $queryBuilder, $property, $key, $values)
    {
        if ($values === "") {
            return null;
        }

        $conditions = $queryBuilder->expr()->orX();
        $queryFilters = \explode(',', $values);

        foreach ($queryFilters as $filter) {
            if ($filter === "") {
                continue;
            }

            if ($filter === 'EMPTY') {
                $conditions->add($this->addEmptyFilter($queryBuilder, $property, $key));
            } else {
                $conditions->add($this->addValueFilter($queryBuilder, $property, $key, $filter));
            }
        }

        return $conditions;
    }

    /**
     * Convertes a value to a query condition.
     *
     * @param QueryBuilder $queryBuilder
     * @param string $property
     * @param string $key
     * @param string $filter
     *
     * @return Expr\Andx
     */
    private function addValueFilter(QueryBuilder $queryBuilder, $property, $key, $filter)
    {
        $condition = $queryBuilder->expr()->andX();
        $expression = sprintf('{"%s": ["%s"]}', $key, $filter);
        $condition->add(sprintf("JSON_CONTAINS(o.%s, '%s') = TRUE", $property, $expression));

        return $condition;
    }

    /**
     * Convertes special value 'EMPTY' to a query condition.
     *
     * @param QueryBuilder $queryBuilder
     * @param string $property
     * @param string $key
     *
     * @return Expr\Orx
     */
    private function addEmptyFilter(QueryBuilder $queryBuilder, $property, $key)
    {
        $condition = $queryBuilder->expr()->orX();
        $condition->add(sprintf("JSONCHILD(o.%s, '%s') = '[]'", $property, $key));
        $condition->add(sprintf("o.%s IS NULL", $property));

        return $condition;
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
        $classMetadata   = $this->getClassMetadata($resource);
        $jsonbFieldNames = [];

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if ($classMetadata->getTypeOfField($fieldName) === 'json') {
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
