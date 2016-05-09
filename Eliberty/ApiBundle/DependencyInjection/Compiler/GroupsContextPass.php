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
 * Add groups context to the group context resolver
 *
 * @author Philippe Vesin <pvesin@eliberty.fr>
 */
class GroupsContextPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $groupContextCollectionDefinition = $container->getDefinition('api.group.context.resolver');
        foreach ($container->findTaggedServiceIds('api_group_context') as $serviceId => $tags) {
            $priority = isset($tags[0]['priority']) ? $tags[0]['priority'] : 0;
            $groupContextCollectionDefinition->addMethodCall(
                'add',
                [new Reference($serviceId), $priority]
            );
        }
    }
}
