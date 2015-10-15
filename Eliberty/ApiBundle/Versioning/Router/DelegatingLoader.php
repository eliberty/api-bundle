<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Versioning\Router;

use Symfony\Bundle\FrameworkBundle\Routing\DelegatingLoader as BaseDelegatingLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * DelegatingLoader delegates route loading to other loaders using a loader resolver.
 *
 * This implementation resolves the _controller attribute from the short notation
 * to the fully-qualified form (from a:b:c to class:method).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DelegatingLoader extends BaseDelegatingLoader
{
    /**
     * Loads a resource.
     *
     * @param mixed  $resource A resource
     * @param string $type     The resource type
     *
     * @return RouteCollection A RouteCollection instance
     */
    public function load($resource, $type = null)
    {
        $collection = parent::load($resource, $type);

        foreach ($collection->all() as $key => $route) {
            preg_match("/api_(v[-+]?(\d*[.])?\d+)_(.*)/", $key, $output_array);
            if (!empty($output_array) && isset($output_array[1])) {
                $apiVersion = $output_array[1];
                $route->setCondition("context.getApiVersion() === '".$apiVersion."'");
                /*
                 * @TODO check if always necessary
                 */
                $route->setMethods(array_merge($route->getMethods(), ['OPTIONS']));
            } elseif ("fos_oauth_server_token" == $key) {
                /*
                 * @TODO check if always necessary
                 */
                $route->setMethods(array_merge($route->getMethods(), ['OPTIONS']));
            }
        }

        return $collection;
    }
}
