<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\AbstractFilter;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\SearchFilter as BaseSearchFilter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Filter the collection by given properties.
 *
 * @author philippe Vesin <pvesin@eliberty.fr>
 */
class SearchFilter extends AbstractFilter
{
    /**
     * @var string Exact matching.
     */
    const STRATEGY_EXACT = 'exact';
    /**
     * @var string The value must be contained in the field.
     */
    const STRATEGY_PARTIAL = 'partial';

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @param ManagerRegistry           $managerRegistry
     * @param PropertyAccessorInterface $propertyAccessor
     * @param null|array                $properties       Null to allow filtering on all properties with the exact strategy or a map of property name with strategy.
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        PropertyAccessorInterface $propertyAccessor,
        array $properties = null
    ) {
        parent::__construct($managerRegistry, $properties);
        $this->propertyAccessor = $propertyAccessor;
    }


    /**
     * @return array|null
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param Request $request
     * @return array|mixed
     */
    public function getRequestProperties(Request $request)
    {
        $requestProperties = [];
        if (empty($this->properties)) {
            return $requestProperties;
        }

        foreach ($this->properties as $name => $precision) {
            $dataRequest = $request->get($name, null);
            if (!is_null($dataRequest)) {
                $requestProperties[$name] = [
                    'precision' => $precision,
                    'value' => $dataRequest
                    ]
                ;
            }
        }
        return $requestProperties;
    }

    /**
     * @inheritdocs
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        $metadata = $this->getClassMetadata($resource);
        $fieldNames = array_flip($metadata->getFieldNames());

        foreach ($this->extractProperties($request) as $property => $value) {
            if (!is_string($value) || !$this->isPropertyEnabled($property)) {
                continue;
            }

            $partial = null !== $this->properties && self::STRATEGY_PARTIAL === $this->properties[$property];
            $lcValue = strtolower($value);
            $propertyValue = $partial ? sprintf('%%%s%%', $lcValue) : $lcValue;

            if (isset($fieldNames[$property])) {
                $shouldLower = 'string' === $metadata->getTypeOfField($property);
                if ($shouldLower){
                    $equalityString = $partial ? 'lower(o.%1$s) LIKE :%1$s' : 'lower(o.%1$s) = :%1$s';
                } else {
                    $equalityString = 'o.%1$s = :%1$s';
                }

                $queryBuilder
                    ->andWhere(sprintf($equalityString, $property))
                    ->setParameter($property, $propertyValue);
            } elseif ($metadata->isSingleValuedAssociation($property)
                || $metadata->isCollectionValuedAssociation($property)
            ) {
                $queryBuilder
                    ->join(sprintf('o.%s', $property), $property)
                    ->andWhere(sprintf('%1$s.id = :%1$s', $property))
                    ->setParameter($property, $propertyValue)
                ;
            }
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
            $found = isset($this->properties[$fieldName]);
            if ($found || null === $this->properties) {
                $description[$fieldName] = [
                    'property' => $fieldName,
                    'type' => $metadata->getTypeOfField($fieldName),
                    'required' => false,
                    'strategy' => $found ? $this->properties[$fieldName] : self::STRATEGY_EXACT,
                ];
            }
        }

        foreach ($metadata->getAssociationNames() as $associationName) {
            if ($this->isPropertyEnabled($associationName)) {
                $description[$associationName] = [
                    'property' => $associationName,
                    'type' => 'iri',
                    'required' => false,
                    'strategy' => self::STRATEGY_EXACT,
                ];
            }
        }

        return $description;
    }
}
