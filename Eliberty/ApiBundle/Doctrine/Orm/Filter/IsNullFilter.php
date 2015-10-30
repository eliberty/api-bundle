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
use Eliberty\ApiBundle\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;


/**
 * Class IsNullFilter
 */
class IsNullFilter extends SearchFilter
{
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
                'type' => '\boolean',
                'required' => false,
                'description' => 'return the entity with'. $fieldName .' is true/false',
                'requirement'  => '0|1'
            ],
        ];
    }
}
