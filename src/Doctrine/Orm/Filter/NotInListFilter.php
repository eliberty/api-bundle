<?php

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
 * Class NotInListFilter
 */
class NotInListFilter extends SearchFilter
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
            if (!is_array($value) || !$this->isPropertyEnabled($property)  || !isset($value['notin'])) {
                continue;
            }

            if (isset($fieldNames[$property])) {
                $in_datas = explode(',', $value['notin']);
                $listname = 'f_' . $property . '_list';
                $queryBuilder
                    ->andWhere(sprintf('o.%s NOT IN (:%s)', $property, $listname))
                    ->setParameter($listname, $in_datas)
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
            sprintf('%s[%s]', $fieldName, 'notin') => [
                'property' => $fieldName,
                'type' => '\array',
                'required' => false,
                'description' => 'Excludes ids into the collection from query results',
                'requirement'  => '[a-zA-Z0-9-]+'
            ],
        ];
    }

}
