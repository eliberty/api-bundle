<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace  Eliberty\ApiBundle\Hydra\Serializer;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Dunglas\ApiBundle\Hydra\Serializer\ErrorNormalizer as BaseErrorNormalizer;
use Eliberty\ApiBundle\Helper\HeaderHelper;
/**
 * Converts {@see \Exception} to a Hydra error representation.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Samuel ROZE <samuel.roze@gmail.com>
 * @author Philippe Vesin <pvesin@gmail.com>
 */
class ErrorNormalizer extends  BaseErrorNormalizer
{

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param RouterInterface     $router
     * @param TranslatorInterface $translator
     * @param bool                $debug
     */
    public function __construct(RouterInterface $router, TranslatorInterface $translator, $debug)
    {
        $this->translator = $translator;
        parent::__construct($router, $debug);
    }

    /**
     * @param object $object
     * @param null $format
     * @param array $context
     * @return array|bool|float|int|null|string
     */
    public function normalize($object, $format = null, array $context = array())
    {
        $data = parent::normalize($object, $format, $context);
        $msgError = $object->getMessage();
        if (method_exists($object, 'getErrors')) {
            $errors = [];
            foreach ($object->getErrors() as $key => $error) {
                foreach ($error as $name => $message) {
                    $errors[$key][$name] =  $this->translator->trans($message);
                }
            }
            if (!empty($errors) && isset($data['hydra:description'])) {
                $data['hydra:description'] = [
                    'hydra:title' => isset($msgError) ? $msgError : (string)$object,
                    'hydra-error' => $errors
                ];
            }
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsNormalization($data, $format = null)
    {
        return 'ld+json' === $format && $data instanceof \Exception;
    }
}
