<?php

/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Xml\Serializer;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintViolationList;
use Eliberty\ApiBundle\JsonLd\Serializer\ErrorViolationListNormalizer as JsonErrorViolationListNormalizer;

/**
 * Converts {@see \Exception} to a xml violation error representation.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ErrorViolationListNormalizer extends JsonErrorViolationListNormalizer
{
    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return 'xml' === $format && $data instanceof ConstraintViolationList;
    }
}
