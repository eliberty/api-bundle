<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 * (c) Philippe Vesin <pvesin@eliberty.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Api;

use Dunglas\ApiBundle\Exception\InvalidArgumentException;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface as BaseResourceCollectionInterface;

/**
 * A collection of {@see ResourceInterface} classes.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Philippe Vesin <pvesin@eliberty.com>
 */
interface ResourceCollectionInterface extends BaseResourceCollectionInterface
{
    /**
     * Initializes the {@see ResourceInterface} collection.
     *
     * @param ResourceInterface[] $resources
     *
     * @throws InvalidArgumentException
     */
    public function init(array $resources);

    /**
     * Gets the {@see ResourceInterface} instance associated with the given entity class or null if not found.
     *
     * @param string|object $entityClass
     * @param string $version
     *
     * @return ResourceInterface|null
     */
    public function getResourceForEntityWithVersion($entityClass, $version);

    /**
     * Gets the {@see ResourceInterface} instance associated with the given short name or null if not found.
     *
     * @param string $shortName
     * @param string $version
     *
     * @return ResourceInterface|null
     */
    public function getResourceForShortNameWithVersion($shortName, $version);
}