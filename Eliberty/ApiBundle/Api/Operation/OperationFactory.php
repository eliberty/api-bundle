<?php

namespace Eliberty\ApiBundle\Api\Operation;

use Dunglas\JsonLdApiBundle\Api\Operation\Operation;
use Dunglas\JsonLdApiBundle\Api\Operation\OperationInterface;
use Doctrine\Common\Inflector\Inflector;
use Dunglas\JsonLdApiBundle\Api\ResourceInterface;
use Symfony\Component\Routing\Route;
/**
 * Class OperationFactory
 * @package Eliberty\ApiBundle\Api\Operation
 */
class OperationFactory
{
    const ROUTE_NAME_PREFIX = 'api_';
    const DEFAULT_CONTROLLER = 'ElibertyApiBundle:Resource';

    /**
     * @var array
     */
    private static $inflectorCache = [];

    /**
     * Creates collection operation.
     *
     * @param ResourceInterface $resource
     * @param string|array      $methods
     * @param string|null       $path
     * @param null              $controller
     * @param null              $routeName
     * @param array             $context
     *
     * @return Operation
     */
    public function createCollectionOperation(
        ResourceInterface $resource,
        $methods,
        $path = null,
        $controller = null,
        $routeName = null,
        array $context = []
    ) {
        return $this->createOperation($resource, true, $methods, $path, $controller, $routeName, $context);
    }

    /**
     * Creates item operation.
     *
     * @param ResourceInterface $resource
     * @param string|array      $methods
     * @param string|null       $path
     * @param null              $controller
     * @param null              $routeName
     * @param array             $context
     *
     * @return Operation
     */
    public function createItemOperation(
        ResourceInterface $resource,
        $methods,
        $path = null,
        $controller = null,
        $routeName = null,
        array $context = []
    ) {
        return $this->createOperation($resource, false, $methods, $path, $controller, $routeName, $context);
    }

    /**
     * Creates operation.
     *
     * @param ResourceInterface $resource
     * @param bool              $collection
     * @param string|array      $methods
     * @param string|null       $path
     * @param null              $controller
     * @param null              $routeName
     * @param array             $context
     *
     * @return Operation
     */
    private function createOperation(
        ResourceInterface $resource,
        $collection,
        $methods,
        $path = null,
        $controller = null,
        $routeName = null,
        array $context = []
    ) {
        $shortName = $resource->getShortName();

        if (!isset(self::$inflectorCache[$shortName])) {
            self::$inflectorCache[$shortName] = Inflector::pluralize(Inflector::tableize($shortName));
        }

        // Populate path
        if (!$path) {
            $path = '/'.self::$inflectorCache[$shortName];

            if (!$collection) {
                $path .= '/{id}';
            }
        }

        // Guess default method
        if (is_array($methods)) {
            $defaultMethod = $methods[0];
        } else {
            $defaultMethod = $methods;
        }

        // Populate controller
        if (!$controller) {
            $defaultAction = strtolower($defaultMethod);

            if ($collection) {
                $defaultAction = 'c'.$defaultAction;
            }

            $controller = self::DEFAULT_CONTROLLER.':'.$defaultAction;

            // Populate route name
            if (!$routeName) {
                $routeName = self::$inflectorCache[$shortName].'_'.$defaultAction;
            }
        }

        return new Operation(
            new Route(
                $path,
                [
                    '_controller' => $controller,
                    '_resource' => $shortName,
                ],
                [],
                [],
                '',
                [],
                $methods
            ),
            $routeName,
            $context
        );
    }
}
