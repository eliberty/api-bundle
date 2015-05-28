<?php
namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TransformerRessourcePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {

        $transformerCollectionDefinition = $container->getDefinition('api.ressource.transformer_resolver');
        //$request = $container->get('request');
        foreach ($container->findTaggedServiceIds('api_transformer') as $serviceId => $tags) {
            $transformerCollectionDefinition->addMethodCall(
                'add',
                [new Reference($serviceId), $serviceId]
            );
//            $definition->addMethodCall(
//                'addMapping',
//                array($service, $container->getDefinition($service)->getClass())
//            );
        }
    }
}