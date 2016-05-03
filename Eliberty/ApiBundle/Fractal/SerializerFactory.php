<?php

namespace Eliberty\ApiBundle\Fractal;

use Eliberty\ApiBundle\Fractal\Serializer\DataHydraSerializer;
use Eliberty\ApiBundle\Fractal\Serializer\DataXmlSerializer;
use Eliberty\ApiBundle\Fractal\Serializer\ArraySerializer;
use Eliberty\ApiBundle\Helper\HeaderHelper;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class SerializerFactory
 *
 * @package Eliberty\ApiBundle\Fractal
 */
class SerializerFactory
{
    /**
     *  hydra serializer
     */
    const HYDRA = 'ld+json';

    /**
     *  json serializer
     */
    const JSON = 'json';

    /**
     *  xml serializer
     */
    const XML = 'xml';

    /**
     * is the function for select the selializer for the fractal mapping
     *
     * @param Request $request
     *
     * @return ArraySerializer|DataHydraSerializer|DataXmlSerializer|null
     */
    public function getSerializer(Request $request)
    {
        $serializer = null;
        switch(HeaderHelper::getContext($request)) {
            case 'json':
                $serializer = new ArraySerializer();
                break;
            case 'xml':
                $serializer = new DataXmlSerializer();
                break;
            default:
                $serializer = new DataHydraSerializer();
                break;
        }
        return $serializer;
    }
}
