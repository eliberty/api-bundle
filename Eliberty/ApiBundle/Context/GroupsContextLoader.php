<?php
/**
 *
 */
namespace Eliberty\ApiBundle\Context;

use Doctrine\Common\Cache\Cache;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\FileLocator;

/**
 * Class GroupsContextLoader
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContextLoader
{

    /**
     * @var string
     */
    protected $bundles;

    /**
     * @var Cache
     */
    protected $cache;

    /**
     * GroupsContextLoader constructor.
     *
     * @param              $bundles
     * @param Cache        $cache
     */
    public function __construct($bundles, Cache $cache)
    {
        $this->cache   = $cache;
        $this->bundles = $bundles;
    }

    /**
     * @param $entityName
     * @param $version
     *
     * @return null
     */
    public function getContexts($entityName, $version) {
        $cache = $this->getCacheContext($version);

        return isset($cache[$entityName]) ? $cache[$entityName] : null;
    }

    /**
     * get config webhook for current webinstance
     *
     * @param $version
     *
     * @return mixed
     */
    public function getCacheContext($version)
    {
        if (!$config = $this->cache->fetch($this->getCacheKey())) {
            $config = $this->createConfig($version);
            $this->cache->save($this->getCacheKey(), $config, 86400);
        }

        return $config;
    }

    /**
     * created the config for cache
     *
     * @param $version
     *
     * @return array
     */
    protected function createConfig($version) {
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
    protected function getCacheKey()
    {
        return 'group_context_api';
    }
}