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

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * Converts {@see \Exception} to a Hydra error representation.
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
     * @param RouterInterface $router
     * @param bool            $debug
     */
    public function __construct(RouterInterface $router, $debug)
    {
        $this->router = $router;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function normalize($violationList, $format = null, array $context = array())
    {
        if ($violationList instanceof \Exception) {
            $message = $violationList->getMessage();

            if ($this->debug) {
                $trace = $violationList->getTrace();
            }
        }

        $data = [
            '@context' => $this->router->generate('api_json_ld_context', ['shortName' => 'ConstraintViolationList']),
            '@type' => 'ConstraintViolationList',
            'hydra:title' => isset($context['title']) ? $context['title'] : 'An error occurred',
            //'hydra:description' => isset($message) ? $message : (string) $violationList,
            'violations' => [],
        ];

        foreach ($violationList as $violation) {
            $data['violations'][$violation->getPropertyPath()] = $violation->getMessage();
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
        return 'hydra-error' === $format && $data instanceof \Exception;
    }
}
