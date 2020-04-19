<?php
namespace Eliberty\ApiBundle\Api;

use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Class ApiSerializer
 * @package Eliberty\ApiBundle\Api
 */
class ApiSerializer
{
    /**
     * @param NormalizerInterface $normalizer
     * @param                     $serviceId
     *
     * @internal param HandlerInterface $handler
     */
    public function add($normalizer, $serviceId)
    {
        $this->mapping[$serviceId] = $normalizer;
    }

    /**
     * @param        $data
     * @param        $format
     * @param string $context
     *
     * @return array|\Symfony\Component\Serializer\Normalizer\scalar
     */
    public function serialize($data, $format, $context = 'error')
    {
        foreach ($this->mapping as $normalizer) {
            /** @var NormalizerInterface  $normalizer */
            if ($normalizer->supportsNormalization($data, $format)) {
                return $normalizer->normalize($data);
            }
        }

        return $data;
    }
}