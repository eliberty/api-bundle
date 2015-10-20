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
        $metadata = $this->getClassMetadata($resource);
        $fieldNames = array_flip($metadata->getFieldNames());

        foreach ($this->extractProperties($request) as $property => $value) {
            if (!is_array($value) || !$this->isPropertyEnabled($property)  || !isset($value['in'])) {
                continue;
            }

            if (isset($fieldNames[$property])) {
                $in_datas = explode(',', $value['in']);
                $listname = 'f_' . $property . '_list';
                $queryBuilder
                    ->andWhere(sprintf('o.%s IN (:%s)', $property, $listname))
                    ->setParameter($listname, $in_datas)
                ;
            }
        }
    }
}
