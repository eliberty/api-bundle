<?php

namespace Eliberty\ApiBundle\Context;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class GroupsContextLoader
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContextLoader
{
    protected array $bundles;

    protected CacheInterface $cache;

    public function __construct(array $bundles, CacheInterface $cache)
    {
        $this->cache   = $cache;
        $this->bundles = $bundles;
    }

    /**
     * @throws \ReflectionException
     */
    public function getContexts(string $entityName, ?string $version): ?array
    {
        $cache = $this->getCacheContext($version);

        return $cache[$entityName] ?? null;
    }

    /**
     * get config webhook for current webinstance
     *
     * @throws \ReflectionException
     */
    public function getCacheContext(?string $version): array
    {
        if (!$config = $this->cache->get($this->getCacheKey())) {
            $config = $this->createConfig($version);
            $this->cache->set($this->getCacheKey(), $config, 86400);
        }

        return $config;
    }

    /**
     * created the config for cache
     *
     * @throws \ReflectionException
     */
    protected function createConfig(?string $version): array
    {
        $cacheData = [];
        $basedir = '/Resources/config/api/' . $version . '/context';
        foreach ($this->bundles as $bundle) {
            $reflection = new \ReflectionClass($bundle);
            $dirname    = dirname($reflection->getFileName());
            if (is_dir($dir = $dirname . $basedir)) {
                foreach (Finder::create()->files()->in($dir)->name('*.yml') as $file) {
                    $grpName = $file->getBasename('.'.$file->getExtension());
                    $contexts = Yaml::parse($file->getContents());
                    foreach ($contexts as $key => $value) {
                        $cacheData[$key][$grpName] = $value;
                    }
                }
            }
        }

        return $cacheData;
    }

    /**
     * get key parameter for cache redis
     */
    protected function getCacheKey(): string
    {
        return 'group_context_api';
    }
}