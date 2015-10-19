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
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;


/**
 * Class IsNullFilter
 */
class IsNullFilter extends AbstractFilter
{
    /**
     * @var IriConverterInterface
     */
    private $iriConverter;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @param ManagerRegistry           $managerRegistry
     * @param IriConverterInterface     $iriConverter
     * @param PropertyAccessorInterface $propertyAccessor
     * @param null|array                $properties       Null to allow filtering on all properties with the exact strategy or a map of property name with strategy.
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        IriConverterInterface $iriConverter,
        PropertyAccessorInterface $propertyAccessor,
        array $properties = null
    ) {
        parent::__construct($managerRegistry, $properties);

        $this->iriConverter = $iriConverter;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * @param ResourceInterface $resource
     * @param QueryBuilder      $queryBuilder
     * @param Request           $request
     */
    public function apply(ResourceInterface $resource, QueryBuilder $queryBuilder, Request $request)
    {
        $metadata = $this->getClassMetadata($resource);
        $fieldNames = array_flip($metadata->getFieldNames());

        foreach ($this->extractProperties($request) as $property => $value) {
            if (!is_array($value) || !$this->isPropertyEnabled($property)  || !isset($value['isnull'])) {
                continue;
            }

            if (isset($fieldNames[$property]) || $metadata->isSingleValuedAssociation($property)) {
                $term = $value['isnull'] == 1 ? 'IS NULL' : 'IS NOT NULL';
                $queryBuilder
                    ->andWhere(sprintf('o.%s %s', $property, $term))
                ;
            }
        }
    }

    /**
     * @param ResourceInterface $resource
     *
     * @return array
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

    /**
     * Gets the ID from an URI or a raw ID.
     *
     * @param string $value
     *
     * @return string
     */
    private function getFilterValueFromUrl($value)
    {
        try {
            if ($item = $this->iriConverter->getItemFromIri($value)) {
                return $this->propertyAccessor->getValue($item, 'id');
            }
        } catch (\InvalidArgumentException $e) {
            // Do nothing, return the raw value
        }

        return $value;
    }
}
