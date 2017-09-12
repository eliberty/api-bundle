<?php
/**
 * @since 12/09/2017 - 22:57
 */

namespace Eliberty\ApiBundle\DependencyInjection\Compiler;

use Eliberty\ApiBundle\Versioning\Router\DelegatingLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Eliberty\ApiBundle\Versioning\Router\RequestContext;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;

class OverrideServicesCompilerPass implements CompilerPassInterface
{
    /**
     * List of overriden services in api bundle
     * @var array
     */
    private static $servicesMap = [
        'router.request_context' => RequestContext::class,
        'router.default'         => ApiRouter::class,
        'routing.loader'         => DelegatingLoader::class,
    ];

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        foreach (self::$servicesMap as $service => $className) {
            $definition = $container->getDefinition($service);
            $definition->setClass($className);
        }
    }
}