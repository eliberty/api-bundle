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
use Dunglas\ApiBundle\Mapping\ClassMetadataFactoryInterface;
use Eliberty\ApiBundle\Helper\DocumentationHelper;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class ApiDocumentationBuilder
 * @package Eliberty\ApiBundle\Hydra
 */
class ApiDocumentationBuilder implements ApiDocumentationBuilderInterface
{

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
     * @var string
     */
    private $title;
    /**
     * @var string
     */
    private $description;

    /**
     * @var DocumentationHelper
     */
    private $documentationHelper;

    /**
     * @param ResourceCollectionInterface $resourceCollection
     * @param ContextBuilder $contextBuilder
     * @param RouterInterface $router
     * @param $title
     * @param $description
     * @param DocumentationHelper $documentationHelper
     */
    public function __construct(
        ResourceCollectionInterface $resourceCollection,
        ContextBuilder $contextBuilder,
        RouterInterface $router,
        $title,
        $description,
        DocumentationHelper $documentationHelper
    ) {
        $this->resourceCollection = $resourceCollection;
        $this->contextBuilder = $contextBuilder;
        $this->router = $router;
        $this->title = $title;
        $this->description = $description;
        $this->documentationHelper = $documentationHelper;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocumentation()
    {
        $classes = [];
        $entrypointProperties = [];

        foreach ($this->resourceCollection as $resource) {

            $shortName = $resource->getShortName();
            $prefixedShortName = '#'.$shortName;

            $collectionOperations = [];
            foreach ($resource->getCollectionOperations() as $collectionOperation) {
                $collectionOperations[] = $this->getHydraOperation($resource, $collectionOperation, $prefixedShortName, true);
            }

            $class = [
                '@id' => $prefixedShortName,
                '@type' => 'hydra:Class',
                'rdfs:label' => $resource->getShortName(),
                'hydra:title' => $resource->getShortName(),
                'hydra:description' => 'Object '.$resource->getShortName(),
            ];

            if (!empty($resource->getAlias())) {
                $class['hydra:description']= $class['hydra:description']. ' [alias: '. implode(',', $resource->getAlias()) .']';
            }

            if (!is_null($resource->getParent())) {
                $class['hydra:description'] = $class['hydra:description']. ' (parent: '. $resource->getParent()->getShortName() .')';
            }

            $properties = [];
            $normalizedOutput = $this->documentationHelper->normalizeClassParameter($resource->getEntityClass(), $resource);
            $attributes =  $this->documentationHelper->getParametersParser($normalizedOutput, $resource->getShortName());
            foreach ($attributes as $attributeName => $attributeMetadata) {

                $type = 'rdf:Property';

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
        $methodDoc =  $this->documentationHelper->getReflectionMethod($operation->getRoute()->getDefault('_controller'));
        $annotation = $methodDoc !== null ? $this->documentationHelper->getMethodAnnotation($methodDoc): null;
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
}
