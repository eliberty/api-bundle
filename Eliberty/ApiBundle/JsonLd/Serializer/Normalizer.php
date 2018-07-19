<?php

/*
 * This file is part of the DunglasJsonLdApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\JsonLd\Serializer;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\PersistentCollection;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Eliberty\ApiBundle\Fractal\Manager;
use Eliberty\ApiBundle\Fractal\Pagination\DunglasPaginatorAdapter;
use Eliberty\ApiBundle\Fractal\Pagination\PagerfantaPaginatorAdapter;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Eliberty\ApiBundle\Fractal\Serializer\ArraySerializer;
use League\Fractal\TransformerAbstract;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Dunglas\ApiBundle\Doctrine\Orm\Paginator;

/**
 * Converts between objects and array including JSON-LD and Hydra metadata.
 */
class Normalizer
{
    const EMBED_AVAILABLE = 'e-embed-available';
    /**
     * @var TransformerHelper
     */
    private $transformerHelper;

    /**
     * @var Logger
     */
    private $logger;
    /**
     * Normalizer constructor.
     *
     * @param TransformerHelper $transformerHelper
     * @param Logger            $logger
     */
    public function __construct(
        TransformerHelper $transformerHelper,
        Logger $logger
    ) {
        $this->logger            = $logger;
        $this->transformerHelper = $transformerHelper;
    }

    /**
     * @param                   $object
     * @param ResourceInterface $dunglasResource
     * @param Manager           $fractalManager
     * @param Request|null      $request
     * @param bool              $defaultIncludes
     *
     * @return array
     * @throws \Exception
     */
    public function normalize(
        $object,
        ResourceInterface $dunglasResource,
        Manager $fractalManager,
        Request $request = null,
        $defaultIncludes = true
    ) {
        $transformer = $this->transformerHelper->getTransformer($dunglasResource->getShortName());

        if (null !== $request) {
            $fractalManager->parseIncludes($this->getEmbedsWithoutOptions($transformer, $request));
        }

        $resource = new Item($object, $transformer);
        if ($object instanceof Paginator || $object instanceof \Doctrine\Common\Collections\Collection) {
            $resource = new Collection($object, $transformer);
            if ($fractalManager->getSerializer() instanceof ArraySerializer && $object instanceof Paginator) {
                $resource->setPaginator(
                    new DunglasPaginatorAdapter($object, $resource)
                );
            }
        }

        $rootScope = $fractalManager->createData($resource, $dunglasResource->getShortName());
        $rootScope->setDunglasResource($dunglasResource);

        if ($defaultIncludes === false) {
            $transformer->setDefaultIncludes([]);
        }

        $transformer
            ->setCurrentScope($rootScope)
            ->setEmbed($dunglasResource->getShortName());

        return $rootScope->toArray();
    }

    /**
     * @param TransformerAbstract $transformer
     * @param Request             $request
     *
     * @return array
     */
    private function getEmbedsWithoutOptions(TransformerAbstract $transformer, Request $request)
    {
        $embeds = $request->query->get('embed', null);

        if (null === $embeds) {
            $embeds = implode(',', $transformer->getDefaultIncludes());
        }

        $embedAvailable = $request->headers->get(self::EMBED_AVAILABLE, 0);
        if ($request->headers->has(self::EMBED_AVAILABLE) && !$embedAvailable) {
            $transformer->setDefaultIncludes([]);
        }

        if ($embedAvailable) {
            if (is_null($embeds)) {
                $embeds = implode(',', $transformer->getAvailableIncludes());
            } else {
                $arrayEmbed = array_merge(explode(',', $embeds), $transformer->getAvailableIncludes());
                $embeds     = implode(',', $arrayEmbed);
            }
        }

        return explode(',', $embeds);
    }
}
