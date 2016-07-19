<?php

/*
 * This file is part of the ApiBundle.
 *
 * (c) Vesin Pilippe <pveliberty@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Nelmio\Formatter;

use Doctrine\ORM\Tools\Pagination\Paginator;
use Dunglas\ApiBundle\Api\ResourceInterface as DunglasResource;
use Eliberty\ApiBundle\Api\ResourceCollection;
use Eliberty\ApiBundle\Doctrine\Orm\Filter\EmbedFilter;
use Eliberty\RedpillBundle\Model\OrderitemInterface;
use Nelmio\ApiDocBundle\Formatter\AbstractFormatter;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Class MkDocsFormatter
 *
 * @package Eliberty\ApiBundle\Formatter
 */
class MkDocsFormatter extends AbstractFormatter
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;
    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var string
     */
    public $resourceName;

    /**
     * @var DunglasResource
     */
    public $apiResource;
    /**
     * @var array
     */
    public $dataCollection;

    /**
     * @var string
     */
    protected $routeDir;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var string
     */
    protected $currentPath;

    /**
     * @var EngineInterface
     */
    protected $engine;
    /**
     * @var AbstractNormalizer
     */
    protected $normalizer;

    /**
     * @var ResourceCollection
     */
    private $resourceCollection;

    /**
     * @var array
     */
    protected $enums = [];
    /**
     * @var DataProviderInterface
     */
    private $dataProvider;

    /**
     * @param                           $routeDir
     * @param EngineInterface           $engine
     * @param AbstractNormalizer        $normalizer
     * @param ResourceCollection        $resourceCollection
     * @param ManagerRegistry           $managerRegistry
     * @param PropertyAccessorInterface $propertyAccessor
     */
    public function __construct(
        $routeDir,
        EngineInterface $engine,
        AbstractNormalizer $normalizer,
        ResourceCollection $resourceCollection,
        ManagerRegistry $managerRegistry,
        PropertyAccessorInterface $propertyAccessor,
        DataProviderInterface $dataProvider
    ) {
        $this->routeDir    = $routeDir;
        $this->engine      = $engine;
        $this->fs          = new Filesystem();
        $this->currentPath = $this->routeDir . '/../apidoc/docs/metadata/';
        $this->fs->mkdir($this->currentPath);
        $this->normalizer         = $normalizer;
        $this->resourceCollection = $resourceCollection;
        $this->managerRegistry    = $managerRegistry;
        $this->propertyAccessor   = $propertyAccessor;
        $this->dataProvider       = $dataProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $collection)
    {
        return $this->render(
            $this->processCollection($collection)
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function renderOne(array $data)
    {
        $path     = $data['methodePath'];
        $markdown = sprintf("### `%s` %s ###\n", $data['method'], $data['uri']);

        if (isset($data['deprecated']) && false !== $data['deprecated']) {
            $markdown .= "### This method is deprecated ###";
            $markdown .= "\n\n";
        }

        if (isset($data['description'])) {
            $markdown .= sprintf("\n_%s_", $data['description']);
        }

        $markdown .= "\n\n";

        if (isset($data['documentation']) && !empty($data['documentation'])) {
            if (isset($data['description']) && 0 === strcmp($data['description'], $data['documentation'])) {
                $markdown .= $data['documentation'];
                $markdown .= "\n\n";
            }
        }

        if (isset($data['requirements']) && !empty($data['requirements']) && !$this->fs->exists(
                $path . '/requirements.html'
            )
        ) {
            $dataFilters = $this->engine->render('ElibertyApiBundle:nelmio:requirements.html.twig', ['data' => $data]);
            $this->fs->dumpFile($path . '/requirements.html', $dataFilters);
        }

        if (isset($data['filters']) && !$this->fs->exists($path . '/filters.html')) {
            $dataFilters = $this->engine->render('ElibertyApiBundle:nelmio:filters.html.twig', ['data' => $data]);
            $this->fs->dumpFile($path . '/filters.html', $dataFilters);
        }

        if (isset($data['parameters']) && !$this->fs->exists($path . '/parameters.html')) {
            $dataFilters = $this->engine->render(
                'ElibertyApiBundle:nelmio:parameters.html.twig',
                ['data' => $data]
            );
            $this->fs->dumpFile($path . '/parameters.html', $dataFilters);
        }

        if (isset($data['statusCodes']) && !$this->fs->exists($path . '/statusCodes.html')) {
            $dataFilters = $this->engine->render('ElibertyApiBundle:nelmio:statusCodes.html.twig', ['data' => $data]);
            $this->fs->dumpFile($path . '/statusCodes.html', $dataFilters);

        }

        if (isset($data['response'])) {
            if (!$this->fs->exists($path . '/responses.html')) {
                $dataFilters = $this->engine->render(
                    'ElibertyApiBundle:nelmio:responses.html.twig',
                    [
                        'data'       => $data,
                        'enums'      => $this->enums,
                        'entityName' => strtolower($this->apiResource->getShortName())
                    ]
                );
                $this->fs->dumpFile($path . '/responses.html', $dataFilters);
            }

            $dataToSerialize = $this->dataProvider->getCollection($this->apiResource, new Request());
            if (!isset($data['tags']['collection']) && $dataToSerialize instanceof Paginator) {
                $dataToSerialize = $dataToSerialize->getIterator()->current();
            }

            if (!$this->fs->exists($path . '/json/responses.json')) {
                try {
                    if (
                        isset($data['tags']['embed']) &&
                        in_array(strtolower($this->apiResource->getShortName()), ['option', 'orderitem'])
                    ) {
                        $this->setEmbed($data, $dataToSerialize);
                    }
                    $dataJson = $this->normalizer->normalize(
                        $dataToSerialize,
                        'json-ld',
                        $this->apiResource->getNormalizationContext(),
                        false
                    );
                    $this->fs->dumpFile($path . '/json/responses.json', json_encode($dataJson, JSON_PRETTY_PRINT));
                } catch (\Exception $ex) {
                    return $markdown;
                }
            }
        }

        return $markdown;
    }

    /**
     * @param $data
     * @param $dataToSerialize
     */
    protected function setEmbed($data, $dataToSerialize)
    {
        if (
            isset($data['tags']['embed']) &&
            isset($data['tags']['collection'])
        ) {
            $filter = new EmbedFilter($this->managerRegistry, $this->propertyAccessor);
            $item   = $dataToSerialize->getIterator()->current();

            if (null === $params = $this->apiResource->getRouteKeyParams($item)) {
                $params['id'] = $this->propertyAccessor->getValue($item, 'id');
            }

            $params['embed'] = $this->apiResource->getShortName();

            $filter->setParameters($params);
            $filter->setRouteName($data['routeName']);
            $this->apiResource->addFilter($filter);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function render(array $collection)
    {
        $markdown = '';
        foreach ($collection as $section => $resources) {
            $markdown .= $this->renderResourceSection($section, $resources);
            $markdown .= "\n";
        }

        return trim($markdown);
    }

    private function renderResourceSection($section, array $resources)
    {
        $markdown = '';
        if ('_others' !== $section) {
            $markdown = sprintf("# %s #\n\n", $section);
        }


        foreach ($resources as $resource => $methods) {
            $this->resourceName = strtolower($section);
            if (!($this->apiResource = $this->resourceCollection->getResourceForShortName(
                ucfirst($this->resourceName),
                'v2'
            ))
            ) {
                throw new \InvalidArgumentException(
                    sprintf('The resource "%s" cannot be found.', ucfirst($this->resourceName))
                );
            }

            if ('_others' === $section && 'others' !== $resource) {
                $markdown .= sprintf("## %s ##\n\n", $resource);
            } elseif ('others' !== $resource) {
                $markdown .= sprintf("## %s ##\n\n", $resource);
            }

            foreach ($methods as $method) {
                $basePath              = $this->currentPath . '/resources/' . $this->resourceName;
                $method['methodePath'] = $basePath . '/' . strtolower($method['method']);
                if (isset($method['tags']['collection'])) {
                    $method['methodePath'] = $basePath . '/collection/' . strtolower($method['method']);
                }

                $this->fs->mkdir($method['methodePath']);
                $markdown .= $this->renderOne($method);
                $markdown .= "\n";
            }
        }

        return $markdown;
    }

    /**
     * @param  array [ApiDoc] $collection
     *
     * @return array
     */
    protected function processCollection(array $collection)
    {
        $array = array();
        foreach ($collection as $coll) {
            $array[$coll['annotation']->getSection()][$coll['resource']][] = array_merge(
                $coll['annotation']->toArray(),
                ['routeName' => $coll['routeName']]
            );
        }

        $processedCollection = array();
        foreach ($array as $section => $resources) {
            foreach ($resources as $path => $annotations) {
                foreach ($annotations as $annotation) {
                    if ($section) {
                        $processedCollection[$section][$path][] = $this->processAnnotation($annotation);
                    } else {
                        $processedCollection['_others'][$path][] = $this->processAnnotation($annotation);
                    }
                }
            }
        }

        ksort($processedCollection);

        return $processedCollection;
    }

    /**
     * @param array $enums
     *
     * @return $this
     */
    public function setEnums($enums)
    {
        $this->enums = $enums;

        return $this;
    }

}
