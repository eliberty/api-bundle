<?php
namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class DoctrineEntityListenerPass
 * @package Eliberty\ApiBundle\DependencyInjection\Compiler
 */
class DoctrineEntityListenerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('webhook.doctrine.entity_listener_resolver');
        foreach ($container->findTaggedServiceIds('doctrine.entity_listener') as $serviceId => $tags) {
            $definition->addMethodCall(
                'addMapping',
                [new Reference($serviceId), $container->getDefinition($serviceId)->getClass()]
            );
        }
    }
}