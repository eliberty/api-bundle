<?php
/**
 *
 */
namespace Eliberty\ApiBundle\Context;

use Doctrine\Common\Cache\Cache;
use Eliberty\ApiBundle\Resolver\BaseResolver as BaseVersioning;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Eliberty\ApiBundle\Versioning\Router\ApiRouter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\FileLocator;

/**
 * Class GroupsContextLoader
 *
 * @package Eliberty\ApiBundle\Context
 */
class GroupsContextLoader extends BaseVersioning
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
     * @param RequestStack $bundles
     * @param RequestStack $requestStack
     * @param Cache        $cache
     */
    public function __construct($bundles, RequestStack $requestStack, Cache $cache)
    {
        $this->cache        = $cache;
        $this->bundles      = $bundles;
        parent::__construct($requestStack);
    }

    /**
     * @param $entityName
     *
     * @return null
     */
    public function getContexts($entityName) {
        $cache = $this->getCacheContext();

        return isset($cache[$entityName]) ? $cache[$entityName] : null;
    }

    /**
     * get config webhook for current webinstance
     *
     * @return mixed
     */
    public function getCacheContext()
    {
        if (!$config = $this->cache->fetch($this->getCacheKey())) {
            $config = $this->createConfig();
            $this->cache->save($this->getCacheKey(), $config, 86400);
        }

        return $config;
    }

    /**
     * created the config for cache
     */
    protected function createConfig() {
        $cacheData = [];
        $basedir = '/Resources/config/api/' . $this->version . '/context';
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