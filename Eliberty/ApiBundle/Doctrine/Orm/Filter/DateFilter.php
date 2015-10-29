<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\AbstractFilter;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\DateFilter as BaseDateFilter;
/**
 * Filters the collection by date intervals.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Théo FIDRY <theo.fidry@gmail.com>
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class DateFilter extends AbstractFilter
{
    const PARAMETER_LESS = 'lt';
    const PARAMETER_LESS_EQUAL = 'lte';
    const PARAMETER_GREATER = 'gt';
    const PARAMETER_GREATER_EQUAL = 'gte';
    const EXCLUDE_NULL = 0;
    const INCLUDE_NULL_BEFORE = 1;
    const INCLUDE_NULL_AFTER = 2;

    /**
     * @var array
     */
    private static $doctrineDateTypes = [
        'date' => true,
        'datetime' => true,
        'datetimetz' => true,
        'time' => true,
    ];

    /**
     * {@inheritdoc}
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        $fieldNames = $this->getDateFieldNames($resource);

        foreach ($this->extractProperties($request) as $property => $values) {
            // Expect $values to be an array having the period as keys and the date value as values
            if (!isset($fieldNames[$property]) || !is_array($values) || !$this->isPropertyEnabled($property)) {
                continue;
            }

            $nullManagement = isset($this->properties[$property]) ? $this->properties[$property] : null;

            if (self::EXCLUDE_NULL === $nullManagement) {
                $queryBuilder->andWhere($queryBuilder->expr()->isNotNull(sprintf('o.%s', $property)));
            }

            if (isset($values[self::PARAMETER_LESS])) {
                $this->addWhere(
                    $queryBuilder,
                    $property,
                    self::PARAMETER_LESS,
                    $values[self::PARAMETER_LESS],
                    $nullManagement
                );
            }

            if (isset($values[self::PARAMETER_LESS_EQUAL])) {
                $this->addWhere(
                    $queryBuilder,
                    $property,
                    self::PARAMETER_LESS_EQUAL,
                    $values[self::PARAMETER_LESS_EQUAL],
                    $nullManagement
                );
            }

            if (isset($values[self::PARAMETER_GREATER])) {
                $this->addWhere(
                    $queryBuilder,
                    $property,
                    self::PARAMETER_GREATER,
                    $values[self::PARAMETER_GREATER],
                    $nullManagement
                );
            }

            if (isset($values[self::PARAMETER_GREATER_EQUAL])) {
                $this->addWhere(
                    $queryBuilder,
                    $property,
                    self::PARAMETER_GREATER_EQUAL,
                    $values[self::PARAMETER_GREATER_EQUAL],
                    $nullManagement
                );
            }
        }
    }

    /**
     * Adds the where clause accordingly to the choosed null management.
     *
     * @param QueryBuilder $queryBuilder
     * @param string       $property
     * @param string       $parameter
     * @param string       $value
     * @param int|null     $nullManagement
     */
    private function addWhere(QueryBuilder $queryBuilder, $property, $parameter, $value, $nullManagement)
    {
        $queryParameter = sprintf('date_%s_%s', $parameter, $property);
        $symbole = $this->getCompareSymbole($parameter);
        $where = sprintf('o.%s %s :%s', $property, $symbole, $queryParameter);
//var_dump($where);exit;
        $queryBuilder->setParameter($queryParameter, new \DateTime($value));

        if (null === $nullManagement || self::EXCLUDE_NULL === $nullManagement) {
            $queryBuilder->andWhere($where);

            return;
        }

        if (
            (in_array($parameter, [self::PARAMETER_LESS, self::PARAMETER_LESS_EQUAL]) && self::INCLUDE_NULL_BEFORE === $nullManagement) ||
            (in_array($parameter, [self::PARAMETER_GREATER, self::PARAMETER_GREATER_EQUAL]) && self::INCLUDE_NULL_AFTER === $nullManagement)
        ) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                $where,
                $queryBuilder->expr()->isNull(sprintf('o.%s', $property))
            ));

            return;
        }

        $queryBuilder->andWhere($queryBuilder->expr()->andX(
            $where,
            $queryBuilder->expr()->isNotNull(sprintf('o.%s', $property))
        ));
    }

    /**
     * @param $parameter
     * @return null|string
     */
    public function getCompareSymbole($parameter) {
        $value = null;

        switch($parameter) {
            case self::PARAMETER_LESS:
                    $value = '<';
                break;
            case self::PARAMETER_LESS_EQUAL:
                    $value = '<=';
                break;
            case self::PARAMETER_GREATER:
                    $value = '>';
                break;
            case self::PARAMETER_GREATER_EQUAL:
                $value = '>=';
            break;
        }

        return $value;
    }
    /**
     * {@inheritdoc}
     */
    public function getDescription(ResourceInterface $resource)
    {
        $description = [];
        foreach ($this->getClassMetadata($resource)->getFieldNames() as $fieldName) {
            if ($this->isPropertyEnabled($fieldName)) {
                $description += $this->getFilterDescription($fieldName, self::PARAMETER_LESS);
                $description += $this->getFilterDescription($fieldName, self::PARAMETER_LESS_EQUAL);
                $description += $this->getFilterDescription($fieldName, self::PARAMETER_GREATER);
                $description += $this->getFilterDescription($fieldName, self::PARAMETER_GREATER_EQUAL);
            }
        }

        return $description;
    }

    /**
     * Gets filter description.
     *
     * @param string $fieldName
     * @param string $period
     *
     * @return array
     */
    private function getFilterDescription($fieldName, $period)
    {
        return [
            sprintf('%s[%s]', $fieldName, $period) => [
                'property' => $fieldName,
                'type' => '\DateTime',
                'required' => false,
                'description' => $this->getDescriptionFilter($period, $fieldName),
                'requirement' => 'Y-m-d\TH:i:sO'
            ],
        ];
    }

    /**
     * @param $period
     * @return null|string
     */
    private function getDescriptionFilter($period, $fieldName) {
        $trans = null;

        switch($period) {
            case self::PARAMETER_LESS:
                $trans = 'Less than date filter';
                break;
            case self::PARAMETER_LESS_EQUAL:
                $trans = 'Less or equal date filter';
                break;
            case self::PARAMETER_GREATER:
                $trans = 'Greater than date filter';
                break;
            case self::PARAMETER_GREATER_EQUAL:
                $trans = 'Greater than or equal date filter';
                break;
        }

        return $trans;
    }

    /**
     * Gets names of fields with a date type.
     *
     * @param ResourceInterface $resource
     *
     * @return array
     */
    private function getDateFieldNames(ResourceInterface $resource)
    {
        $classMetadata = $this->getClassMetadata($resource);
        $dateFieldNames = [];

        foreach ($classMetadata->getFieldNames() as $fieldName) {
            if (isset(self::$doctrineDateTypes[$classMetadata->getTypeOfField($fieldName)])) {
                $dateFieldNames[$fieldName] = true;
            }
        }

        return $dateFieldNames;
    }

    /**
     * @param Request $request
     * @return array|mixed
     */
    public function getRequestProperties(Request $request)
    {
        return $this->extractProperties($request);
    }
}
