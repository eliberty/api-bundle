<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Hydra\Serializer;

use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Converts {@see \Exception} to a Hydra violation error representation.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ErrorViolationListNormalizer implements NormalizerInterface
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
        if ($violationList instanceof \Exception) {
            if ($this->debug) {
                $trace = $violationList->getTrace();
            }
        }

        $data = [
            '@context'    => $this->router->generate('api_json_ld_context', ['shortName' => 'ConstraintViolationList']),
            '@type'       => 'ConstraintViolationList',
            'title'       => 'An error occurred',
            'violations'  => [],
        ];

        foreach ($violationList as $violation) {
            $key          = $violation->getPropertyPath();
            $invalidValue = $violation->getInvalidValue();
            if (method_exists($violation->getRoot(), '__toString')) {
                $invalidValue = $this->propertyAccessor->getValue($violation->getRoot(), $violation->getPropertyPath());
            }
            if ($violation->getConstraint() instanceof UniqueEntity) {
                $class     = method_exists($violation->getRoot(), 'getConfig') ? $violation->getRoot()->getConfig() : $violation->getRoot();
                $reflexion = new \ReflectionClass($class);
                $key       = strtolower($reflexion->getShortname());
            }
            $data['violations'][$key][] = [
                'property'     => $violation->getPropertyPath(),
                'invalidValue' => $invalidValue,
                'message'      => $violation->getMessage()
            ];
        }

        if (isset($trace)) {
            $data['trace'] = $trace;
        }

        return $data;
    }


    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return (('ld+json' === $format || 'json' === $format) && $data instanceof ConstraintViolationList);
    }
}
