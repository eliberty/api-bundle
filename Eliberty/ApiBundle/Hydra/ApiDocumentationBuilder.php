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
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ApiDocumentationBuilder
 * @package Eliberty\ApiBundle\Hydra
 */
class ApiDocumentationBuilder implements ApiDocumentationBuilderInterface
{
    const ANNOTATION_CLASS = 'Nelmio\\ApiDocBundle\\Annotation\\ApiDoc';

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
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocumentation()
    {
        $classes = [];
        $entrypointProperties = [];

        foreach ($this->resourceCollection as $resource) {
            $classMetadata = $this->classMetadataFactory->getMetadataFor(
                $resource->getEntityClass(),
                $resource->getNormalizationGroups(),
                $resource->getDenormalizationGroups(),
                $resource->getValidationGroups()
            );

            $shortName = $resource->getShortName();
            $prefixedShortName = ($iri = $classMetadata->getIri()) ? $iri : '#'.$shortName;

            $collectionOperations = [];
            foreach ($resource->getCollectionOperations() as $collectionOperation) {
                $collectionOperations[] = $this->getHydraOperation($resource, $collectionOperation, $prefixedShortName, true);
            }

            $entrypointProperties[] = [
                '@type' => 'hydra:SupportedProperty',
                'hydra:property' => [
                    '@id' => sprintf('#Entrypoint/%s', lcfirst($shortName)),
                    '@type' => 'hydra:Link',
                    'domain' => '#Entrypoint',
                    'rdfs:label' => sprintf('The collection of %s resources', $shortName),
                    'range' => 'hydra:PagedCollection',
                    'hydra:supportedOperation' => $collectionOperations,
                ],
                'hydra:title' => sprintf('The collection of %s resources', $shortName),
                'hydra:readable' => true,
                'hydra:writable' => false,
            ];

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

            if ($description = $classMetadata->getDescription()) {
                $class['hydra:description'] = $description;
            }

            $properties = [];
            foreach ($classMetadata->getAttributes() as $attributeName => $attributeMetadata) {
                if ($attributeMetadata->isIdentifier() && !$attributeMetadata->isWritable()) {
                    continue;
                }

                if ($attributeMetadata->isNormalizationLink()) {
                    $type = 'Hydra:Link';
                } else {
                    $type = 'rdf:Property';
                }

                $property = [
                    '@type' => 'hydra:SupportedProperty',
                    'hydra:property' => [
                        '@id' => ($iri = $attributeMetadata->getIri()) ? $iri : sprintf('#%s/%s', $shortName, $attributeName),
                        '@type' => $type,
                        'rdfs:label' => $attributeName,
                        'domain' => $prefixedShortName,
                    ],
                    'hydra:title' => $attributeName,
                    'hydra:required' => $attributeMetadata->isRequired(),
                    'hydra:readable' => $attributeMetadata->isIdentifier() ? false : $attributeMetadata->isReadable(),
                    'hydra:writable' => $attributeMetadata->isWritable(),
                ];

                if ($range = $this->getRange($attributeMetadata)) {
                    $property['hydra:property']['range'] = $range;
                }

                if ($description = $attributeMetadata->getDescription()) {
                    $property['hydra:description'] = $description;
                }

                $properties[] = $property;
            }
            $class['hydra:supportedProperty'] = $properties;

            $operations = [];
            foreach ($resource->getItemOperations() as $itemOperation) {
                $operations[] = $this->getHydraOperation($resource, $itemOperation, $prefixedShortName, false);
            }

            $class['hydra:supportedOperation'] = $operations;
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

//        // Constraint violation
//        $classes[] = [
//            '@id' => '#ConstraintViolation',
//            '@type' => 'hydra:Class',
//            'hydra:title' => 'A constraint violation',
//            'hydra:supportedProperty' => [
//                [
//                    '@type' => 'hydra:SupportedProperty',
//                    'hydra:property' => [
//                        '@id' => '#ConstraintViolation/propertyPath',
//                        '@type' => 'rdf:Property',
//                        'rdfs:label' => 'propertyPath',
//                        'domain' => '#ConstraintViolation',
//                        'range' => 'xmls:string',
//                    ],
//                    'hydra:title' => 'propertyPath',
//                    'hydra:description' => 'The property path of the violation',
//                    'hydra:readable' => true,
//                    'hydra:writable' => false,
//                ],
//                [
//                    '@type' => 'hydra:SupportedProperty',
//                    'hydra:property' => [
//                        '@id' => '#ConstraintViolation/message',
//                        '@type' => 'rdf:Property',
//                        'rdfs:label' => 'message',
//                        'domain' => '#ConstraintViolation',
//                        'range' => 'xmls:string',
//                    ],
//                    'hydra:title' => 'message',
//                    'hydra:description' => 'The message associated with the violation',
//                    'hydra:readable' => true,
//                    'hydra:writable' => false,
//                ],
//            ],
//        ];
//
//        // Constraint violation list
//        $classes[] = [
//            '@id' => '#ConstraintViolationList',
//            '@type' => 'hydra:Class',
//            'subClassOf' => 'hydra:Error',
//            'hydra:title' => 'A constraint violation list',
//            'hydra:supportedProperty' => [
//                [
//                    '@type' => 'hydra:SupportedProperty',
//                    'hydra:property' => [
//                        '@id' => '#ConstraintViolationList/violation',
//                        '@type' => 'rdf:Property',
//                        'rdfs:label' => 'violation',
//                        'domain' => '#ConstraintViolationList',
//                        'range' => '#ConstraintViolation',
//                    ],
//                    'hydra:title' => 'violation',
//                    'hydra:description' => 'The violations',
//                    'hydra:readable' => true,
//                    'hydra:writable' => false,
//                ],
//            ],
//        ];

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
     * @param AttributeMetadataInterface $attributeMetadata
     *
     * @return string|null
     */
    private function getRange(AttributeMetadataInterface $attributeMetadata)
    {
        if (isset($attributeMetadata->getTypes()[0])) {
            $type = $attributeMetadata->getTypes()[0];

            if ($type->isCollection() && $collectionType = $type->getCollectionType()) {
                $type = $collectionType;
            }

            switch ($type->getType()) {
                case 'string':
                    return 'xmls:string';

                case 'int':
                    return 'xmls:integer';

                case 'float':
                    return 'xmls:double';

                case 'bool':
                    return 'xmls:boolean';

                case 'object':
                    $class = $type->getClass();

                    if ($class) {
                        if ('DateTime' === $class) {
                            return 'xmls:dateTime';
                        }

                        if ($resource = $this->resourceCollection->getResourceForEntity($type->getClass())) {
                            return sprintf('#%s', $resource->getShortName());
                        }
                    }
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
}
