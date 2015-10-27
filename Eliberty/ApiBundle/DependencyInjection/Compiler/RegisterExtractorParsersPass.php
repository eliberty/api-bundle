<?php

namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class RegisterExtractorParsersPass
 * @package Eliberty\ApiBundle\DependencyInjection\Compiler
 */
class RegisterExtractorParsersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!array_key_exists('NelmioApiDocBundle', $container->getParameter('kernel.bundles'))) {
            return;
        }

        if (false === $container->hasDefinition('api.documentation.helper')) {
            return;
        }

        $definition = $container->getDefinition('api.documentation.helper');

        //find registered parsers and sort by priority
        $sortedParsers = array();
        foreach ($container->findTaggedServiceIds('nelmio_api_doc.extractor.parser') as $id => $tagAttributes) {
            foreach ($tagAttributes as $attributes) {
                $priority = isset($attributes['priority']) ? $attributes['priority'] : 0;
                $sortedParsers[$priority][] = $id;
            }
        }

        //add parsers if any
        if (!empty($sortedParsers)) {
            krsort($sortedParsers);
            $sortedParsers = call_user_func_array('array_merge', $sortedParsers);

            //add method call for each registered parsers
            foreach ($sortedParsers as $id) {
                $definition->addMethodCall('addParser', array(new Reference($id)));
            }
        }
    }
}
