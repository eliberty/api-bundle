<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\JsonLd;

use Dunglas\ApiBundle\Api\IriConverterInterface;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Symfony\Component\Routing\RouterInterface;
use Dunglas\ApiBundle\JsonLd\EntrypointBuilder as BaseEntrypointBuilder;
/**
 * API Entrypoint builder.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class EntrypointBuilder extends BaseEntrypointBuilder
{

    /**
     * @var ResourceCollectionInterface
     */
    protected $resourceCollection;
    /**
     * @var IriConverterInterface
     */
    protected $iriConverter;
    /**
     * @var RouterInterface
     */
    protected $router;

    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        IriConverterInterface $iriConverter,
        RouterInterface $router
    ) {
        $this->resourceCollection = $resourceCollection;
        $this->iriConverter = $iriConverter;
        $this->router = $router;
    }

    /**
     * Gets the entrypoint of the API.
     *
     * return array
     */
    public function getEntrypoint()
    {
        $entrypoint = [
            '@context' => $this->router->generate('api_json_ld_entrypoint_context'),
            '@id' => $this->router->generate('api_json_ld_entrypoint'),
            '@type' => 'Entrypoint',
        ];

        foreach ($this->resourceCollection as $resource) {
            if (is_null($resource->getParent())) {
                foreach ($resource->getCollectionOperations() as $operation) {
                    if (in_array('GET', $operation->getRoute()->getMethods())) {
                        $entrypoint[lcfirst($resource->getShortName())] = $this->iriConverter->getIriFromResource($resource);
                        break;
                    }
                }
            }
        }

        return $entrypoint;
    }
}
