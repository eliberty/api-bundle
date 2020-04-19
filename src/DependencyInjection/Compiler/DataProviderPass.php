<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
/**
 * Class DataProviderPass
 * @package Eliberty\ApiBundle\DependencyInjection\Compiler
 */
class DataProviderPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('api.doctrine.orm.default_data_provider');
        foreach ($container->findTaggedServiceIds('api.data_provider') as $serviceId => $tags) {
            if ('api.doctrine.orm.default_data_provider' === $serviceId) {
                continue;
            }
            $definition->addMethodCall(
                'addDataProvider',
                [new Reference($serviceId), $container->getDefinition($serviceId)->getClass()]
            );
        }
    }
}
