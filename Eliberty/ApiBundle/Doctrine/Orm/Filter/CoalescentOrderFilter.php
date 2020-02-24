<?php

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\ORM\Query\AST\CoalesceExpression;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\OrderFilter as BaseOrderFilter;
use Doctrine\Common\Persistence\ManagerRegistry;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Util\QueryNameGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Coalescent order by the collection by given properties.
 */
class CoalescentOrderFilter extends BaseOrderFilter
{
    /**
     * @var string Keyword used to retrieve the value.
     */
    private $coalesceOrderParameter = 'corder';

    /**
     * @param ManagerRegistry $managerRegistry
     * @param array|null      $properties      List of property names on which the filter will be enabled.
     */
    public function __construct(ManagerRegistry $managerRegistry, array $properties = null)
    {
        parent::__construct($managerRegistry, $properties);
        $this->properties = $properties;
    }

    /**
     * @param Request $request
     * @return array|mixed
     */
    public function getRequestProperties(Request $request)
    {
        return $this->extractProperties($request);
    }

    /**
     * {@inheritdoc}
     *
     * Orders collection by properties. The order of the ordered properties is the same as the order specified in the
     * query.
     * For each property passed, if the resource does not have such property or if the order value is different from
     * `asc` or `desc` (case insensitive), the property is ignored.
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        $properties = $this->extractProperties($request);

        foreach ($properties as $property) {
            $fields = \explode(',', $property);

            if (\count($fields) === 0) {
                continue;
            }

            $order = $fields[\count($fields) - 1];
            $order = strtoupper($order);
            if (!in_array($order, ['ASC', 'DESC'])) {
                $order = 'ASC';
            }

            $orderByList = [];
            foreach ($fields as $field) {
                if (!$this->isPropertyEnabled($field) || !$this->isPropertyMapped(
                        $field,
                        $resource
                    )) {
                    continue;
                }

                $alias = 'o';
                $finalField = $field;

                if ($this->isPropertyNested($field)) {
                    $propertyParts = $this->splitPropertyParts($field);

                    $parentAlias = $alias;

                    foreach ($propertyParts['associations'] as $association) {
                        $alias = QueryNameGenerator::generateJoinAlias($association);
                        $queryBuilder->leftJoin(
                            sprintf('%s.%s', $parentAlias, $association),
                            $alias
                        );
                        $parentAlias = $alias;
                    }

                    $finalField = $propertyParts['field'];
                }

                $orderByList[] = sprintf('%s.%s', $alias, $finalField);
            }

            $queryBuilder->addOrderBy('COALESCE(' . \implode(',', $orderByList) . ')', $order);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(ResourceInterface $resource)
    {
        $description = [];
        $metadata = $this->getClassMetadata($resource);

        foreach ($metadata->getFieldNames() as $fieldName) {
            if ($this->isPropertyEnabled($fieldName)) {
                $description[sprintf('%s[]', $this->coalesceOrderParameter)] = [
                    'property' => $fieldName,
                    'type' => 'string',
                    'required' => false,
                    'requirement'  => 'Comma separated list of fields to coalesce',
                    'description' => 'Coalescent order by '.$fieldName
                ];
            }
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function extractProperties(Request $request)
    {
        return $request->query->get($this->coalesceOrderParameter, []);
    }
}
