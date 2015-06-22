<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c)  Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\DefinitionDecorator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Add resources to the resource collection and populate operations if necessary.
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class ResourcePass implements CompilerPassInterface
{
    const BUILTIN_RESOURCE = 'Eliberty\ApiBundle\Api\Resource';

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $resourceCollectionDefinition = $container->getDefinition('api.resource_collection');

        foreach ($container->findTaggedServiceIds('api.resource') as $serviceId => $tags) {
            $resourceDefinition = $container->getDefinition($serviceId);

            if (!$resourceDefinition->hasMethodCall('addItemOperation')) {
                $resourceDefinition->addMethodCall('initItemOperations', [[
                    $this->createOperation($container, $serviceId, 'GET', false),
                    $this->createOperation($container, $serviceId, 'PUT', false),
                    $this->createOperation($container, $serviceId, 'DELETE', false),
                ]]);
            }

//            if (!$resourceDefinition->hasMethodCall('addCollectionOperation')) {
//                $resourceDefinition->addMethodCall('initCollectionOperations', [[
//                    //$this->createOperation($container, $serviceId, 'GET', true),
//                    //$this->createOperation($container, $serviceId, 'POST', true),
//                ]]);
//            }

            //$resourceCollectionDefinition->addMethodCall('addResource', [new Reference($serviceId)]);
        }

    }

    /**
     * Adds an operation.
     *
     * @param ContainerBuilder $container
     * @param string           $serviceId
     * @param string           $method
     * @param bool             $collection
     *
     * @return Reference
     */
    private function createOperation(ContainerBuilder $container, $serviceId, $method, $collection)
    {
        if ($collection) {
            $factoryMethodName = 'createCollectionOperation';
            $operationId = '.collection_operation.';
        } else {
            $factoryMethodName = 'createItemOperation';
            $operationId = '.item_operation.';
        }

        $operation = new Definition(
            'Dunglas\ApiBundle\Api\Operation\Operation',
            [new Reference($serviceId), $method]
        );
        $operation->setFactory([new Reference('api.operation_factory'), $factoryMethodName]);
        $operation->setLazy(true);

        $operationId = $serviceId.$operationId.$method;
        $container->setDefinition($operationId, $operation);

        return new Reference($operationId);
    }

    /**
     * Gets class of the given definition.
     *
     * @param ContainerBuilder $container
     * @param Definition       $definition
     *
     * @return string|null
     */
    private function getClass(ContainerBuilder $container, Definition $definition)
    {
        if ($class = $definition->getClass()) {
            return $class;
        }

        if ($definition instanceof DefinitionDecorator) {
            return $container->getDefinition($definition->getParent())->getClass();
        }
    }
}
