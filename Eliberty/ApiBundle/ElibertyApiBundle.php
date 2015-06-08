<?php

namespace Eliberty\ApiBundle;

use Eliberty\ApiBundle\DependencyInjection\Compiler\DoctrineEntityListenerPass;
use Eliberty\ApiBundle\DependencyInjection\Compiler\ResourcePass;
use Eliberty\ApiBundle\DependencyInjection\Compiler\TransformerRessourcePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ElibertyApiBundle extends Bundle
{

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new ResourcePass());
        $container->addCompilerPass(new DoctrineEntityListenerPass());
        $container->addCompilerPass(new TransformerRessourcePass());
    }
}
