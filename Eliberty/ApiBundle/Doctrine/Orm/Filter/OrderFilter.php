<?php

/*
 * This file is part of the ElibertyBundle package.
 *
 * (c) philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Doctrine\Orm\Filter;


use Doctrine\ORM\QueryBuilder;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Filter\OrderFilter as BaseOrderFilter;
use Doctrine\Common\Persistence\ManagerRegistry;
use Dunglas\ApiBundle\Api\IriConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Order by the collection by given properties.
 *
 * @author philippe Vesin <pvesin@eliberty.fr>
 */
class OrderFilter extends BaseOrderFilter
{

    /**
     * @var string Keyword used to retrieve the value.
     */
    private $orderParameter;

    /**
     * @param ManagerRegistry $managerRegistry
     * @param string          $orderParameter  Keyword used to retrieve the value.
     * @param array|null      $properties      List of property names on which the filter will be enabled.
     */
    public function __construct(ManagerRegistry $managerRegistry, $orderParameter, array $properties = null)
    {
        parent::__construct($managerRegistry, $properties);
        $this->properties = $properties;
        $this->orderParameter = $orderParameter;
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
     */
    public function getDescription(ResourceInterface $resource)
    {
        $description = [];
        $metadata = $this->getClassMetadata($resource);

        foreach ($metadata->getFieldNames() as $fieldName) {
            if ($this->isPropertyEnabled($fieldName)) {
                $description[sprintf('%s[%s]', $this->orderParameter, $fieldName)] = [
                    'property' => $fieldName,
                    'type' => 'string',
                    'required' => false,
                    'requirement'  => 'ASC|DESC',
                    'description' => 'Order by '.$fieldName
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
        return $request->query->get($this->orderParameter, []);
    }
}
