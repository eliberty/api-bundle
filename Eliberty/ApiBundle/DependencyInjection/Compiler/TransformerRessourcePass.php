<?php
namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class TransformerRessourcePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {

        $definition = $container->getDefinition('api.ressource.transformer_resolver');

        $services = $container->findTaggedServiceIds('api_transformer');
        foreach ($services as $service => $attributes) {
            $definition->addMethodCall(
                'addMapping',
                array($service, $container->getDefinition($service)->getClass())
            );
        }
    }
}