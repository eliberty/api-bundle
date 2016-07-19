<?php
namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class SerializerPass
 * @package Eliberty\ApiBundle\DependencyInjection\Compiler
 */
class SerializerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $serializerCollectionDefinition = $container->getDefinition('eliberty.api.serializer');
        foreach ($container->findTaggedServiceIds('api.serializer.normalizer') as $serviceId => $tags) {
            $serializerCollectionDefinition->addMethodCall(
                'add',
                [new Reference($serviceId), $serviceId]
            );
        }
    }
}