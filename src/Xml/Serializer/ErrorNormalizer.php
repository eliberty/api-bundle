<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace  Eliberty\ApiBundle\Xml\Serializer;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Translation\TranslatorInterface;
/**
 * Converts {@see \Exception} to a xml error representation.
 *
 * @author Philippe Vesin <pvesin@gmail.com>
 */
class ErrorNormalizer implements  NormalizerInterface
{
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var bool
     */
    private $debug;

    /**
     * @param TranslatorInterface $translator
     * @param bool                $debug
     */
    public function __construct(TranslatorInterface $translator, $debug)
    {
        $this->translator = $translator;
        $this->debug      = $debug;
    }

    /**
     * @param object $object
     * @param null $format
     * @param array $context
     * @return array|bool|float|int|null|string
     */
    public function normalize($object, $format = null, array $context = array())
    {
        if ($object instanceof \Exception) {
            $message = $object->getMessage();

            if ($this->debug) {
                $trace = $object->getTrace();
            }
        }

        $data = [
            'context' => 'Error',
            'type' => 'Error',
            'title' => 'An error occurred',
            'description' => isset($message) ? $message : (string) $object,
        ];

//        if (isset($trace)) {
//            $data['trace'] = $trace;
//        }

        $msgError = $object->getMessage();
        if (method_exists($object, 'getErrors')) {
            $errors = [];
            foreach ($object->getErrors() as $key => $error) {
                foreach ($error as $name => $message) {
                    $errors[$key][$name] =  $this->translator->trans($message);
                }
            }
            if (!empty($errors) && isset($data['description'])) {
                $data['description'] = [
                    'title'     => isset($msgError) ? $msgError : (string)$object,
                    'xml-error' => $errors
                ];
            }
        }

        return $data;
    }

    /**
     * Checks whether the given class is supported for normalization by this normalizer.
     *
     * @param mixed  $data   Data to normalize
     * @param string $format The format being (de-)serialized from or into
     *
     * @return bool
     */
    public function supportsNormalization($data, $format = null) {
        return 'xml' === $format && $data instanceof \Exception;
    }
}
