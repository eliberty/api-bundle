<?php
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c)  Philippe Vesin <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Add form to the form resolver
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class FormPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $transformerCollectionDefinition = $container->getDefinition('api.form.resolver');
        foreach ($container->findTaggedServiceIds('api_form') as $serviceId => $tags) {
            $transformerCollectionDefinition->addMethodCall(
                'add',
                [new Reference($serviceId), $serviceId]
            );
        }
    }
}
