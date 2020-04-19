<?php

namespace Eliberty\ApiBundle\Xml;

use Symfony\Component\HttpFoundation\Response as XmlResponse;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Xml Response.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class Response extends XmlResponse
{

    /**
     * Response constructor.
     *
     * @param string $content
     * @param int    $status
     * @param array  $headers
     */
    public function __construct($content = '', $status = 200, $headers = array()) {
        $encoders     = [new XmlEncoder()];
        $normalizers  = [new GetSetMethodNormalizer()];
        $serializer   = new Serializer($normalizers, $encoders);
        $contents_xml = $serializer->serialize($content, 'xml');
        $headers['Content-Type'] = 'application/xml';
        parent::__construct($contents_xml, $status = 200, $headers);
    }

    /**
     * {@inheritdoc}
     */
    protected function update()
    {
        // Only set the header when there is none or when it equals 'application/ld+json' (from a previous update with callback)
        // in order to not overwrite a custom definition.
        if (!$this->headers->has('Content-Type') || 'application/xml' === $this->headers->get('Content-Type')) {
            $this->headers->set('Content-Type', 'application/xml');
        }

        return $this->setContent($this->data);
    }
}
