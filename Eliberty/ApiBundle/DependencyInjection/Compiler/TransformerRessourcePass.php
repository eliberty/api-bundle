<?php
namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class TransformerRessourcePass
 * @package Eliberty\ApiBundle\DependencyInjection\Compiler
 */
class TransformerRessourcePass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $transformerCollectionDefinition = $container->getDefinition('api.ressource.transformer_resolver');
        foreach ($container->findTaggedServiceIds('api_transformer') as $serviceId => $tags) {
            $transformerCollectionDefinition->addMethodCall(
                'add',
                [new Reference($serviceId), $serviceId]
            );
        }
    }
}