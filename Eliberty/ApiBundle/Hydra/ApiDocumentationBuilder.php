<?php

/*
 * This file is part of the DunglasApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Eliberty\ApiBundle\Hydra;

use Doctrine\Common\Annotations\Reader;
use Dunglas\ApiBundle\Api\Operation\OperationInterface;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Hydra\ApiDocumentationBuilderInterface;
use Dunglas\ApiBundle\JsonLd\ContextBuilder;
use Dunglas\ApiBundle\Mapping\AttributeMetadataInterface;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactoryInterface;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Nelmio\ApiDocBundle\Parser\JmsMetadataParser;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;
use Eliberty\ApiBundle\Api\Resource as DunglasResource;
use Nelmio\ApiDocBundle\Parser\ParserInterface;
/**
 * Class ApiDocumentationBuilder
 * @package Eliberty\ApiBundle\Hydra
 */
class ApiDocumentationBuilder implements ApiDocumentationBuilderInterface
{
    const ANNOTATION_CLASS = 'Nelmio\\ApiDocBundle\\Annotation\\ApiDoc';
    /**
     * @var TransformerHelper
     */
    protected $transformerHelper;

    /**
     * @var ResourceCollectionInterface
     */
    private $resourceCollection;
    /**
     * @var ContextBuilder
     */
    private $contextBuilder;
    /**
     * @var RouterInterface
     */
    private $router;
    /**
     * @var ClassMetadataFactoryInterface
     */
    private $classMetadataFactory;
    /**
     * @var string
     */
    private $title;
    /**
     * @var string
     */
    private $description;
    /**
     * @var Reader
     */
    private $reader;
    /**
     * @var ControllerNameParser
     */
    private $controllerNameParser;
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ParserInterface[]
     */
    protected $parsers = array();

    /**
     * @param ResourceCollectionInterface $resourceCollection
     * @param ContextBuilder $contextBuilder
     * @param RouterInterface $router
     * @param ClassMetadataFactoryInterface $classMetadataFactory
     * @param string $title
     * @param string $description
     * @param Reader $reader
     * @param ControllerNameParser $controllerNameParser
     * @param ContainerInterface $container
     */
    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        ContextBuilder $contextBuilder,
        RouterInterface $router,
        ClassMetadataFactoryInterface $classMetadataFactory,
        $title,
        $description,
        Reader $reader,
        ControllerNameParser $controllerNameParser,
        ContainerInterface $container
    ) {
        $this->resourceCollection = $resourceCollection;
        $this->contextBuilder = $contextBuilder;
        $this->router = $router;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->title = $title;
        $this->description = $description;
        $this->reader = $reader;
        $this->controllerNameParser = $controllerNameParser;
        $this->container = $container;
        $this->transformerHelper = $this->container->get('api.transformer.helper');
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocumentation()
    {
        $classes = [];
        $entrypointProperties = [];

        foreach ($this->resourceCollection as $resource) {
//            $classMetadata = $this->classMetadataFactory->getMetadataFor(
//                $resource->getEntityClass(),
//                $resource->getNormalizationGroups(),
//                $resource->getDenormalizationGroups(),
//                $resource->getValidationGroups()
//            );

            $shortName = $resource->getShortName();
            $prefixedShortName = '#'.$shortName;

            $collectionOperations = [];
            foreach ($resource->getCollectionOperations() as $collectionOperation) {
                $collectionOperations[] = $this->getHydraOperation($resource, $collectionOperation, $prefixedShortName, true);
            }

//            $entrypointProperties[] = [
//                '@type' => 'hydra:SupportedProperty',
//                'hydra:property' => [
//                    '@id' => sprintf('#Entrypoint/%s', lcfirst($shortName)),
//                    '@type' => 'hydra:Link',
//                    'domain' => '#Entrypoint',
//                    'rdfs:label' => sprintf('The collection of %s resources', $shortName),
//                    'range' => 'hydra:PagedCollection',
//                    'hydra:supportedOperation' => $collectionOperations,
//                ],
//                'hydra:title' => sprintf('The collection of %s resources', $shortName),
//                'hydra:readable' => true,
//                'hydra:writable' => false,
//            ];

            $class = [
                '@id' => $prefixedShortName,
                '@type' => 'hydra:Class',
                'rdfs:label' => $resource->getShortName(),
                'hydra:title' => $resource->getShortName(),
                'hydra:description' => 'Object '.$resource->getShortName(),
            ];

            if (!empty($resource->getAlias())) {
                $class['hydra:description']= $class['hydra:description']. '[alias: '. implode(',', $resource->getAlias()) .']';
            }

            if (!is_null($resource->getParent())) {
                $class['hydra:description'] = $class['hydra:description']. '(parent: '. $resource->getParent()->getShortName() .')';
            }

//            if ($description = $classMetadata->getDescription()) {
//                $class['hydra:description'] = $description;
//            }

            $properties = [];
            $normalizedOutput = $this->normalizeClassParameter($resource->getEntityClass(), $resource);
            $attributes = $this->getParametersParser($normalizedOutput, $resource);
            foreach ($attributes as $attributeName => $attributeMetadata) {

//                var_dump($attributeName);
//                var_dump($attributeMetadata);exit;
//                if ($attributeMetadata->isIdentifier() && !$attributeMetadata->isWritable()) {
//                    continue;
//                }

//                if ($attributeMetadata->isNormalizationLink()) {
//                    $type = 'Hydra:Link';
//                } else {
                    $type = 'rdf:Property';
                //}

                $property = [
                    '@type' => 'hydra:SupportedProperty',
                    'hydra:property' => [
                        '@id' => sprintf('#%s/%s', $shortName, $attributeName),
                        '@type' => $type,
                        'rdfs:label' => $attributeName,
                        'domain' => $prefixedShortName,
                    ],
                    'hydra:title' => $attributeName,
                    'hydra:required' => $attributeMetadata['required'],
                    'hydra:readable' => $attributeMetadata['readonly'],
                    'hydra:writable' => !$attributeMetadata['readonly'],
                ];

                if ($range = $this->getRange($attributeMetadata)) {
                    $property['hydra:property']['range'] = $range;
                }

                if (isset($attributeMetadata['description'])) {
                    $property['hydra:description'] = $description = $attributeMetadata['description'];
                }

                $properties[] = $property;
            }
            $class['hydra:supportedProperty'] = $properties;

            $operations = [];
            foreach ($resource->getItemOperations() as $itemOperation) {
                $operations[] = $this->getHydraOperation($resource, $itemOperation, $prefixedShortName, false);
            }

            $class['hydra:supportedOperation'] = array_merge($operations, $collectionOperations);
            $classes[] = $class;
        }

        // Entrypoint
        $classes[] = [
            '@id' => '#Entrypoint',
            '@type' => 'hydra:Class',
            'hydra:title' => 'The API entrypoint',
            'hydra:supportedProperty' => $entrypointProperties,
            'hydra:supportedOperation' => [
                '@type' => 'hydra:Operation',
                'hydra:method' => 'GET',
                'rdfs:label' => 'The API entrypoint.',
                'returns' => '#EntryPoint',
            ],
        ];

        return [
            '@context' => $this->getContext(),
            '@id' => $this->router->generate('api_hydra_vocab'),
            'hydra:title' => $this->title,
            'hydra:description' => $this->description,
            'hydra:entrypoint' => $this->router->generate('api_json_ld_entrypoint'),
            'hydra:supportedClass' => $classes,
        ];
    }

    /**
     * Gets and populates if applicable a Hydra operation.
     *
     * @param ResourceInterface  $resource
     * @param OperationInterface $operation
     * @param string             $prefixedShortName
     * @param bool               $collection
     *
     * @return array
     */
    protected function getHydraOperation(ResourceInterface $resource, OperationInterface $operation, $prefixedShortName, $collection)
    {
        $method = $operation->getRoute()->getMethods();
        if (is_array($method)) {
            // If all methods are allowed, default to GET
            $method = isset($method[0]) ? $method[0] : 'GET';
        }
        $methodDoc = $this->getReflectionMethod($operation->getRoute()->getDefault('_controller'));
        $annotation = $methodDoc !== null ? $this->reader->getMethodAnnotation($methodDoc, self::ANNOTATION_CLASS): null;
        $hydraOperation = $operation->getContext();

        $hydraOperation['hydra:entrypoint'] = $operation->getRoute()->getPath();

        switch ($method) {
            case 'GET':
                if ($collection) {
                    if (!isset($hydraOperation['hydra:title'])) {
                        $hydraOperation['hydra:title'] = sprintf('Retrieves the collection of %s resources.', $resource->getShortName());
                    }

                    if (!isset($hydraOperation['returns'])) {
                        $hydraOperation['returns'] = 'hydra:PagedCollection';
                    }
                    foreach ($resource->getFilters() as $filter) {
                        foreach ($filter->getDescription($resource) as $key => $value) {
                            $hydraOperation["hydra:search"][$key] = [
                                'requirement' => '[a-zA-Z0-9-]+',
                                'description' => $key . ' filter',
                                'default'     => ''
                            ];
                        }
                    }
                } else {
                    if (!isset($hydraOperation['hydra:title'])) {
                        $hydraOperation['hydra:title'] =
                            null !== $annotation && null !== $annotation->getDescription()?
                                $annotation->getDescription() :
                                sprintf('Retrieves %s resource.', $resource->getShortName());
                    }
                    $hydraOperation['returns'] = $annotation->getOutput();
                }
                break;

            case 'POST':
                if (!isset($hydraOperation['@type'])) {
                    $hydraOperation['@type'] = 'hydra:CreateResourceOperation';
                }

                if (!isset($hydraOperation['hydra:title'])) {
                    $hydraOperation['hydra:title'] = sprintf('Creates a %s resource.', $resource->getShortName());
                }
                break;

            case 'PUT':
                if (!isset($hydraOperation['@type'])) {
                    $hydraOperation['@type'] = 'hydra:ReplaceResourceOperation';
                }

                if (!isset($hydraOperation['hydra:title'])) {
                    $hydraOperation['hydra:title'] = sprintf('Replaces the %s resource.', $resource->getShortName());
                }
                break;

            case 'DELETE':
                if (!isset($hydraOperation['hydra:title'])) {
                    $hydraOperation['hydra:title'] = sprintf('Deletes the %s resource.', $resource->getShortName());
                }

                if (!isset($hydraOperation['returns'])) {
                    $hydraOperation['returns'] = 'owl:Nothing';
                }
                break;
        }

        if (!isset($hydraOperation['returns']) &&
            (
                ('GET' === $method && !$collection) ||
                'POST' === $method ||
                'PUT' === $method
            )
        ) {
            $hydraOperation['returns'] = $prefixedShortName;
        }

        if (!isset($hydraOperation['expects']) &&
            ('POST' === $method || 'PUT' === $method)) {
            $hydraOperation['expects'] = $prefixedShortName;
        }

        if (!isset($hydraOperation['@type'])) {
            $hydraOperation['@type'] = 'hydra:Operation';
        }

        if (!isset($hydraOperation['hydra:method'])) {
            $hydraOperation['hydra:method'] = $method;
        }

        if (!isset($hydraOperation['rdfs:label']) && isset($hydraOperation['hydra:title'])) {
            $hydraOperation['rdfs:label'] = $hydraOperation['hydra:title'];
        }

        ksort($hydraOperation);

        return $hydraOperation;
    }

    /**
     * Gets the range of the property.
     *
     * @param array $attributeMetadata
     *
     * @return string|null
     */
    private function getRange($attributeMetadata)
    {
        if (isset($attributeMetadata['dataType'])) {
            $type = $attributeMetadata['dataType'];

            switch ($type) {
                case 'string':
                    return 'xmls:string';

                case 'int':
                    return 'xmls:integer';

                case 'float':
                    return 'xmls:double';

                case 'bool':
                    return 'xmls:boolean';

                case 'DateTime':
                    return 'xmls:dateTime';
                    break;
            }
        }
    }

    /**
     * Builds the JSON-LD context for the API documentation.
     *
     * @return array
     */
    private function getContext()
    {
        return array_merge(
            $this->contextBuilder->getContext(),
            [
                'rdf' => ContextBuilder::RDF_NS,
                'rdfs' => ContextBuilder::RDFS_NS,
                'xmls' => ContextBuilder::XML_NS,
                'owl' => ContextBuilder::OWL_NS,
                'domain' => ['@id' => 'rdfs:domain', '@type' => '@id'],
                'range' => ['@id' => 'rdfs:range', '@type' => '@id'],
                'subClassOf' => ['@id' => 'rdfs:subClassOf', '@type' => '@id'],
                'expects' => ['@id' => 'hydra:expects', '@type' => '@id'],
                'returns' => ['@id' => 'hydra:returns', '@type' => '@id'],
            ]
        );
    }

    /**
     * Returns the ReflectionMethod for the given controller string.
     *
     * @param string $controller
     * @return \ReflectionMethod|null
     */
    public function getReflectionMethod($controller)
    {
        if (false === strpos($controller, '::') && 2 === substr_count($controller, ':')) {
            $controller = $this->controllerNameParser->parse($controller);
        }

        if (preg_match('#(.+)::([\w]+)#', $controller, $matches)) {
            $class = $matches[1];
            $method = $matches[2];
        } elseif (preg_match('#(.+):([\w]+)#', $controller, $matches)) {
            $controller = $matches[1];
            $method = $matches[2];
            if ($this->container->has($controller)) {
                $this->container->enterScope('request');
                $this->container->set('request', new Request(), 'request');
                $class = ClassUtils::getRealClass(get_class($this->container->get($controller)));
                $this->container->leaveScope('request');
            }
        }

        if (isset($class) && isset($method)) {
            try {
                return new \ReflectionMethod($class, $method);
            } catch (\ReflectionException $e) {
            }
        }

        return null;
    }

    /**
     * @param $normalizedInput
     * @param DunglasResource $resource
     * @param string $type
     * @return array
     */
    public function getParametersParser($normalizedInput,  DunglasResource $resource, $type = 'Output')
    {
        $supportedParsers = [];
        $parameters       = [];

        foreach ($this->getParsers($normalizedInput) as $parser) {
            if ($parser->supports($normalizedInput)) {
                $supportedParsers[] = $parser;

                if ($parser instanceof JmsMetadataParser) {
                    $normalizedInput['groups'] = [];
                    $attributes         = $parser->parse($normalizedInput);
                    foreach ($attributes as $key => $value) {
                        if ($key === 'id' && !empty($value)) {
                            $parameters['id'] = $value;
                        }
                        if (isset($parameters[$key]) && isset($value['description'])) {
                            $parameters[$key]['description'] = $value['description'];
                        }
                    }

                    $this->transformerHelper->getOutputAttr($resource->getShortName(), $parameters, 'doc', $attributes);

                    continue;
                }

                $attributes = $parser->parse($normalizedInput);

                $parameters = $this->mergeParameters($parameters, $attributes);
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
     * @param $input
     * @return array
     */
    protected function normalizeClassParameter($input, DunglasResource $resource)
    {
        $defaults = array(
            'class'   => '',
            'groups'  => array(),
            'options'  => array(),
        );

        // normalize strings
        if (is_string($input)) {
            $input = array('class' => $input);
        }

        $collectionData = array();

        /*
         * Match array<Fully\Qualified\ClassName> as alias; "as alias" optional.
         */
        if (preg_match_all("/^array<([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(?:\\\\[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)*)>(?:\\s+as\\s+(.+))?$/", $input['class'], $collectionData)) {
            $input['class'] = $collectionData[1][0];
            $input['collection'] = true;
            $input['collectionName'] = $collectionData[2][0];
        } elseif (preg_match('/^array</', $input['class'])) { //See if a collection directive was attempted. Must be malformed.
            throw new \InvalidArgumentException(
                sprintf(
                    'Malformed collection directive: %s. Proper format is: array<Fully\\Qualified\\ClassName> or array<Fully\\Qualified\\ClassName> as collectionName',
                    $input['class']
                )
            );
        }

        $dataResponse = array_merge($defaults, $input);
        $dataResponse['groups'] = $resource->getValidationGroups();

        return $dataResponse;
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
     * Registers a class parser to use for parsing input class metadata
     *
     * @param ParserInterface $parser
     */
    public function addParser(ParserInterface $parser)
    {
        $this->parsers[] = $parser;
    }

    /**
     * Merges two parameter arrays together. This logic allows documentation to be built
     * from multiple parser passes, with later passes extending previous passes:
     *  - Boolean values are true if any parser returns true.
     *  - Requirement parameters are concatenated.
     *  - Other string values are overridden by later parsers when present.
     *  - Array parameters are recursively merged.
     *  - Non-null default values prevail over null default values. Later values overrides previous defaults.
     *
     * However, if newly-returned parameter array contains a parameter with NULL, the parameter is removed from the merged results.
     * If the parameter is not present in the newly-returned array, then it is left as-is.
     *
     * @param  array $p1 The pre-existing parameters array.
     * @param  array $p2 The newly-returned parameters array.
     * @return array The resulting, merged array.
     */
    protected function mergeParameters($p1, $p2)
    {
        $params = $p1;

        foreach ($p2 as $propname => $propvalue) {

            if ($propvalue === null) {
                unset($params[$propname]);
                continue;
            }

            if (!isset($p1[$propname])) {
                $params[$propname] = $propvalue;
            } elseif (is_array($propvalue)) {
                $v1 = $p1[$propname];

                foreach ($propvalue as $name => $value) {
                    if (is_array($value)) {
                        if (isset($v1[$name]) && is_array($v1[$name])) {
                            $v1[$name] = $this->mergeParameters($v1[$name], $value);
                        } else {
                            $v1[$name] = $value;
                        }
                    } elseif (!is_null($value)) {
                        if (in_array($name, array('required', 'readonly'))) {
                            $v1[$name] = $v1[$name] || $value;
                        } elseif (in_array($name, array('requirement'))) {
                            if (isset($v1[$name])) {
                                $v1[$name] .= ', ' . $value;
                            } else {
                                $v1[$name] = $value;
                            }
                        } elseif ($name == 'default') {
                            $v1[$name] = $value ?: $v1[$name];
                        } else {
                            $v1[$name] = $value;
                        }
                    }
                }

                $params[$propname] = $v1;
            }
        }

        return $params;
    }
}
