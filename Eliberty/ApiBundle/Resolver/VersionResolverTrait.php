<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Resolver;

use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\Request;


/**
 * This class helps to guess api version which header is associated from the request.
 *
 * @author Philippe Vesin <pvesin@gmail.com>
 */
trait VersionResolverTrait
{
    /**
     * @param Request $request
     *
     * @return mixed|string
     */
    public function getVersion(Request $request = null)
    {
        $version = 'v2';
        if ($request instanceof Request) {
            $acceptHeader = AcceptHeader::fromString($request->headers->get('Accept'))->all();
            foreach ($acceptHeader as $acceptHeaderItem) {
                if ($acceptHeaderItem->hasAttribute('version')) {
                    $version = $acceptHeaderItem->getAttribute('version');
                    break;
                }
            }
        }

        return $version;
    }
}