<?php

namespace Eliberty\ApiBundle\Nelmio\Extractor;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\Common\Util\ClassUtils;
use Eliberty\ApiBundle\Api\Resource as DunglasResource;
use Eliberty\ApiBundle\Api\ResourceCollection;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Eliberty\ApiBundle\JsonLd\Serializer\Normalizer;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\DataTypes;
use Nelmio\ApiDocBundle\Parser\JmsMetadataParser;
use Nelmio\ApiDocBundle\Parser\ParserInterface;
use Nelmio\ApiDocBundle\Parser\PostParserInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Nelmio\ApiDocBundle\Util\DocCommentExtractor;
use Nelmio\ApiDocBundle\Extractor\ApiDocExtractor as BaseApiDocExtractor;

class ApiDocExtractor extends BaseApiDocExtractor
{
    const ANNOTATION_CLASS = 'Nelmio\\ApiDocBundle\\Annotation\\ApiDoc';

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var Reader
     */
    protected $reader;

    /**
     * @var DocCommentExtractor
     */
    private $commentExtractor;

    /**
     * @var ParserInterface[]
     */
    protected $parsers = array();

    /**
     * @var HandlerInterface[]
     */
    protected $handlers;
    /**
     * @var TransformerHelper
     */
    private $transformerHelper;

    /**
     * @var ResourceCollection
     */
    protected $resourceCollection;

    /**
     * @var string
     */
    protected $versionApi;
    /**
     * @var Normalizer
     */
    private $normailzer;
    /**
     * @var FormRegistryInterface
     */
    private $registry;

    /**
     * @param ContainerInterface $container
     * @param RouterInterface $router
     * @param Reader $reader
     * @param DocCommentExtractor $commentExtractor
     * @param array $handlers
     * @param TransformerHelper $transformerHelper
     * @param Normalizer $normailzer
     * @param ResourceCollection $resourceCollection
     * @param FormRegistryInterface $registry
     */
    public function __construct(
        ContainerInterface $container,
        RouterInterface $router,
        Reader $reader,
        DocCommentExtractor $commentExtractor,
        array $handlers,
        TransformerHelper $transformerHelper,
        Normalizer $normailzer,
        ResourceCollection $resourceCollection,
        FormRegistryInterface $registry
    ) {
        $this->container         = $container;
        $this->router            = $router;
        $this->reader            = $reader;
        $this->commentExtractor  = $commentExtractor;
        $this->handlers          = $handlers;
        $this->transformerHelper = $transformerHelper;
        $this->resourceCollection  = $resourceCollection;
        $this->normailzer = $normailzer;

        $this->transformerHelper->setClassMetadataFactory($normailzer->getClassMetadataFactory());
        parent::__construct($container, $router, $reader, $commentExtractor, $handlers);
        $this->registry = $registry;
    }

    /**
     * Returns an array of data where each data is an array with the following keys:
     *  - annotation
     *  - resource
     *
     * @param array $routes array of Route-objects for which the annotations should be extracted
     *
     * @return array
     */
    public function extractAnnotations(array $routes)
    {
        $array           = array();
        $resources       = array();
        $excludeSections = $this->container->getParameter('nelmio_api_doc.exclude_sections');

        $paramsRoute = $this->container->get('request')->attributes->get('_route_params');
        $this->versionApi = isset($paramsRoute['version']) ? $paramsRoute['version'] : 'v1';
        $this->transformerHelper->setVersion($this->versionApi);

        foreach ($routes as $name => $route) {
            if (!$route instanceof Route) {
                throw new \InvalidArgumentException(sprintf('All elements of $routes must be instances of Route. "%s" given', gettype($route)));
            }

            if (is_null($route->getDefault('_resource'))) {
                continue;
            }

            if ($method = $this->getReflectionMethod($route->getDefault('_controller'))) {
                $annotation = $this->reader->getMethodAnnotation($method, self::ANNOTATION_CLASS);
                if ($annotation && !in_array($annotation->getSection(), $excludeSections)) {
                    if ($annotation->isResource()) {
                        if ($resource = $annotation->getResource()) {
                            $resources[] = $resource;
                        } else {
                            // remove format from routes used for resource grouping
                            $resources[] = str_replace('.{_format}', '', $route->getPattern());
                        }
                    }

                    $path = $route->getPath();
                    if (false === strstr($path, '{embed}')) {
                        $dunglasResource = $this->resourceCollection->getResourceForShortName($route->getDefault('_resource'));
                        $array[] = ['annotation' => $this->extractData($annotation, $route, $method, $dunglasResource)];
                        continue;
                    }

                    $availableIncludes = $this->transformerHelper->getAvailableIncludes($route->getDefault('_resource'));
                    foreach ($availableIncludes as $include) {
                        $route->setPath(str_replace('{embed}', $include, $path));
                        $name = Inflector::singularize($include);
                        $dunglasResource = $this->resourceCollection->getResourceForShortName(ucfirst($name));
                        $route->addDefaults(['_resource' => $dunglasResource->getShortName()]);
                        $array[] = ['annotation' => $this->extractData($annotation, $route, $method, $dunglasResource)];
                    }
                }
            }
        }

        rsort($resources);
        foreach ($array as $index => $element) {
            $hasResource = false;
            $pattern     = $element['annotation']->getRoute()->getPattern();

            foreach ($resources as $resource) {
                if (0 === strpos($pattern, $resource) || $resource === $element['annotation']->getResource()) {
                    $array[$index]['resource'] = $resource;

                    $hasResource = true;
                    break;
                }
            }

            if (false === $hasResource) {
                $array[$index]['resource'] = 'others';
            }
        }

        $methodOrder = array('GET', 'POST', 'PUT', 'DELETE');
        usort($array, function ($a, $b) use ($methodOrder) {
            if ($a['resource'] === $b['resource']) {
                if ($a['annotation']->getRoute()->getPattern() === $b['annotation']->getRoute()->getPattern()) {
                    $methodA = array_search($a['annotation']->getRoute()->getRequirement('_method'), $methodOrder);
                    $methodB = array_search($b['annotation']->getRoute()->getRequirement('_method'), $methodOrder);

                    if ($methodA === $methodB) {
                        return strcmp(
                            $a['annotation']->getRoute()->getRequirement('_method'),
                            $b['annotation']->getRoute()->getRequirement('_method')
                        );
                    }

                    return $methodA > $methodB ? 1 : -1;
                }

                return strcmp(
                    $a['annotation']->getRoute()->getPattern(),
                    $b['annotation']->getRoute()->getPattern()
                );
            }

            return strcmp($a['resource'], $b['resource']);
        });

        return $array;
    }

    /**
     * @param $normalizedInput
     * @param null $resource
     * @return array
     */
    public function getParametersParser($normalizedInput, $resource = null)
    {
        $supportedParsers = [];
        $parameters       = [];
        foreach ($this->getParsers($normalizedInput) as $parser) {
            if ($parser->supports($normalizedInput)) {
                $supportedParsers[] = $parser;
                $attributes = $parser->parse($normalizedInput);
                if ($parser instanceof JmsMetadataParser) {
                    foreach ($attributes as $key => $value) {
                        if ($key === 'id' && !empty($value)) {
                            $parameters['id']  = $value;
                        }
                        if (isset($parameters[$key]) && isset($value['description'])) {
                            $parameters[$key]['description'] = $value['description'];
                        }
                    }

                    if (!is_null($resource)) {
                        $this->transformerHelper->getOutputAttr($resource, $parameters, 'doc', $attributes);
                    }

                    continue;
                }
                $parameters         = $this->mergeParameters($parameters, $attributes);
            }
        }

        foreach ($supportedParsers as $parser) {
            if ($parser instanceof PostParserInterface) {
                $parameters = $this->mergeParameters(
                    $parameters,
                    $parser->postParse($normalizedInput, $parameters)
                );
            }
        }

        return $parameters;
    }

    /**
     * @param $formName
     * @param array $formParams
     * @param DunglasResource $dunglasResource
     * @return array
     */
    protected function processFormParams($formName, array $formParams, DunglasResource $dunglasResource)
    {
        if (!is_null($formParams) && isset($formParams[$formName]['children'])) {
            $parameters = $formParams[$formName]['children'];
            $entityClass = $dunglasResource->getEntityClass();
            $metadataAttributes = $this->normailzer->getClassMetadataFactory()->getMetadataFor(new $entityClass)->getAttributes();
            array_walk($parameters, function ($val, $key) use (&$parameters, $metadataAttributes) {

                $format = isset($parameters[$key]['format']) ? $parameters[$key]['format'] : null;

                $metatdata = isset($metadataAttributes[strtolower($key)]) ? $metadataAttributes[strtolower($key)] : null;

                if (is_string($format) && is_object(json_decode($format))) {
                    $parameters[$key]['format'] = '['.implode('|', array_keys(json_decode($format, true))).']';
                }

                if (!is_null($metatdata) && count($metatdata->getTypes())> 0) {
                    $property = $metatdata->getTypes()[0];
                    $parameters[$key]['format'] = $property->getType();

                    if ($parameters[$key]['dataType'] === 'boolean') {
                        $parameters[$key]['format'] = '0/1';
                    }

                    if ($property->getType() === 'object' && $parameters[$key]['dataType'] !== 'boolean') {
                        $parameters[$key]['dataType']   = 'integer';
                        //substr($property->getClass(), strrpos($property->getClass(), '\\') + 1);
                    }

                    if ($property->getClass() instanceof Collection) {
                        $parameters[$key]['dataType'] = 'array of '.$property->getType();
                    }
                }
            });

            return $parameters;
        }

        return $formParams;
    }

    /**
     * @param array $parameters
     * @return array|\Nelmio\ApiDocBundle\Parser\ParserInterface[]
     */
    protected function getParsers(array $parameters)
    {
        if (isset($parameters['parsers'])) {
            $parsers = array();
            foreach ($this->parsers as $parser) {
                if (in_array(get_class($parser), $parameters['parsers'])) {
                    $parsers[] = $parser;
                }
            }
        } else {
            $parsers = $this->parsers;
        }

        return $parsers;
    }

    /**
     * Returns a new ApiDoc instance with more data.
     *
     * @param  ApiDoc $annotation
     * @param  Route $route
     * @param  \ReflectionMethod $method
     * @param DunglasResource $dunglasResource
     * @return ApiDoc
     */
    protected function extractData(ApiDoc $annotation, Route $route, \ReflectionMethod $method, DunglasResource $dunglasResource)
    {
        // create a new annotation
        $annotation = clone $annotation;

        $annotation->addTag($this->versionApi, '#ff0000');
        // doc
        $annotation->setDocumentation($this->commentExtractor->getDocCommentText($method));

        // parse annotations
        $this->parseAnnotations($annotation, $route, $method);

        $resource = $route->getDefault('_resource');

        // route
        $annotation->setRoute($route);

        $entityClassInput = $entityClassOutput = null;

        //section
        $annotation->setSection($resource);

        if ('DELETE' !== $annotation->getMethod()) {
            $entityClassInput = $entityClassOutput = $this->transformerHelper->getEntityClass($resource);
        }

        $this->addFilters($resource, $annotation);
        if ('GET' === $annotation->getMethod()) {
            $entityClassInput = null;
        }

        $formName = 'api_v2_'.strtolower($dunglasResource->getShortName());
        if ($this->registry->hasType($formName)) {
            $type = $this->registry->getType($formName);
            if ($type instanceof ResolvedFormTypeInterface) {
                $entityClassInput = get_class($type->getInnerType());
            }
        }

        // input (populates 'parameters' for the formatters)
        if (null !== $input = $entityClassInput) {

            $normalizedInput  = $this->normalizeClassParameter($input);

            $parameters = $this->getParametersParser($normalizedInput);

            $parameters = $this->processFormParams($formName, $parameters, $dunglasResource);

            $parameters = $this->clearClasses($parameters);
            $parameters = $this->generateHumanReadableTypes($parameters);

            if ($annotation->getMethod() === 'POST') {
                if (isset($parameters['id'])) {
                    unset($parameters['id']);
                }
            }

            if (in_array($annotation->getMethod(), ['PUT','PATCH'])) {
                // All parameters are optional with PUT (update)
                array_walk($parameters, function ($val, $key) use (&$parameters) {
                    $parameters[$key]['required'] = false;
                });
            }

            $annotation->setParameters($parameters);
        }


        // output (populates 'response' for the formatters)
        if (null !== $output = $entityClassOutput) {

            $normalizedOutput = $this->normalizeClassParameter($output);

            $response = $this->getParametersParser($normalizedOutput, $resource);

            $response = $this->clearClasses($response);
            $response = $this->generateHumanReadableTypes($response);

            $annotation->setResponse($response);
            $annotation->setResponseForStatusCode($response, $normalizedOutput, 200);
        }

        if (count($annotation->getResponseMap()) > 0) {

            foreach ($annotation->getResponseMap() as $code => $modelName) {

                if ('200' === (string)$code && isset($modelName['type']) && isset($modelName['model'])) {
                    /*
                     * Model was already parsed as the default `output` for this ApiDoc.
                     */
                    continue;
                }

                $normalizedModel = $this->normalizeClassParameter($modelName);

                $parameters       = array();
                $supportedParsers = array();
                foreach ($this->getParsers($normalizedModel) as $parser) {
                    if ($parser->supports($normalizedModel)) {
                        $supportedParsers[] = $parser;
                        $parameters         = $this->mergeParameters($parameters, $parser->parse($normalizedModel));
                    }
                }

                foreach ($supportedParsers as $parser) {
                    if ($parser instanceof PostParserInterface) {
                        $mp         = $parser->postParse($normalizedModel, $parameters);
                        $parameters = $this->mergeParameters($parameters, $mp);
                    }
                }


                $parameters = $this->clearClasses($parameters);
                $parameters = $this->generateHumanReadableTypes($parameters);

                $annotation->setResponseForStatusCode($parameters, $normalizedModel, $code);

            }

        }

        return $annotation;
    }

    /**
     * @param $resource
     * @param ApiDoc $annotation
     * @throws \Exception
     */
    private function addFilters($resource, ApiDoc $annotation)
    {
        //filter embed
        if ('DELETE' !== $annotation->getMethod()) {
            $availableIncludes = $this->transformerHelper->getAvailableIncludes($resource);
            $defaultIncludes   = $this->transformerHelper->getDefaultIncludes($resource);
            $annotation->addFilter('embed', [
                'requirement' => '\t',
                'description' => 'Include resources within other resources.',
                'available'   => is_array($availableIncludes) ? implode(',', $availableIncludes) : $availableIncludes,
                'default'     => is_array($defaultIncludes) ? implode(',', $defaultIncludes) : $defaultIncludes
            ]);
        }

        $data = $annotation->toArray();
        if (isset($data['tags']) && false !== array_search('collection', $data['tags'])) {
            foreach ($resource->getFilters() as $filter) {
                $annotation->addFilter('perpage', [
                    'requirement' => '\d+',
                    'description' => 'How many resource return per page.',
                    'default'     => 30
                ]);
            }


            //filter perpage
            $annotation->addFilter('perpage', [
                'requirement' => '\d+',
                'description' => 'How many resource return per page.',
                'default'     => 30
            ]);

            //filter perpage
            $annotation->addFilter('page', [
                'requirement' => '\d+',
                'description' => 'How many page start to return.',
                'default'     => 1
            ]);

            //filter orderby
            $annotation->addFilter('orderby', [
                'requirement' => '\t',
                'description' => 'Way to sort the rows in the result set.',
                'default'     => urldecode('{"id":"asc"}')
            ]);
        }

        //return $annotation;
    }


}
