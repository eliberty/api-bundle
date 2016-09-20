<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\JsonLd\Serializer;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Eliberty\ApiBundle\Hydra\Serializer\ErrorViolationListNormalizer as HydraErrorViolationListNormalizer;
/**
 * Converts {@see \Exception} to a json violation error representation.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ErrorViolationListNormalizer extends HydraErrorViolationListNormalizer
{
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var bool
     */
    private $debug;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @param RouterInterface $router
     * @param bool $debug
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(RouterInterface $router, $debug, PropertyAccessorInterface $propertyAccessor)
    {
        $this->router           = $router;
        $this->debug            = $debug;
        $this->propertyAccessor = $propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($violationList, $format = null, array $context = [])
    {
        $data = parent::normalize($violationList, $format, $context);

        if (isset($data['@type'])) {
            $data['type'] = $data['@type'];
            unset($data['@type']);
        }

        return $data;
    }


    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return 'json' === $format && $data instanceof ConstraintViolationList;
    }
}
