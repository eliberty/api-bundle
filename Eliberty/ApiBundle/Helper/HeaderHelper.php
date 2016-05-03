<?php

namespace Eliberty\ApiBundle\Helper;
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class HeaderHelper
 */
class HeaderHelper {

    /**
     * @param Request $request
     *
     * @return mixed|string
     */
    public function getContext(Request $request)
    {
        $context = 'ld-json';
        $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'))->all();
        foreach ($acceptHeader as $acceptHeaderItem) {
            if ($acceptHeaderItem->hasAttribute('version')) {
                $context = str_ireplace('application/', '', $acceptHeaderItem->getValue());
                break;
            }
        }
        return $context;
    }
}