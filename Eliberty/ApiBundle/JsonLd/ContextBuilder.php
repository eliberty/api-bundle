<?php


namespace Eliberty\ApiBundle\JsonLd;

use Dunglas\ApiBundle\JsonLd\ContextBuilder as BaseContextBuilder;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * JSON-LD Context Builder.
 *
 */
class ContextBuilder extends BaseContextBuilder
{
    const HYDRA_NS = 'http://www.w3.org/ns/hydra/core#';
    const RDF_NS = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    const RDFS_NS = 'http://www.w3.org/2000/01/rdf-schema#';
    const XML_NS = 'http://www.w3.org/2001/XMLSchema#';
    const OWL_NS = 'http://www.w3.org/2002/07/owl#';

    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;
    /**
     * @var TransformerHelper
     */
    private $transformerHelper;

    /**
     * @param RouterInterface $router
     * @param ResourceCollectionInterface $resourceCollection
     * @param TransformerHelper $transformerHelper
     * @internal param ClassMetadataFactory $classMetadataFactory
     * @internal param TransformerResolver $transformerResolver
     */
    public function __construct(
        RouterInterface $router,
        ResourceCollectionInterface $resourceCollection,
        TransformerHelper $transformerHelper
    ) {
        $this->classMetadataFactory = $transformerHelper->getClassMetadataFactory();
        $this->transformerHelper = $transformerHelper;
        parent::__construct($router, $this->classMetadataFactory, $resourceCollection);
    }


    /**
     * Builds the JSON-LD context for the given resource.
     *
     * @param ResourceInterface|null $resource
     *
     * @return array
     */
    public function getContext(ResourceInterface $resource = null)
    {
        $context = parent::getContext($resource);
        if ($resource) {
            $this->transformerHelper->getOutputAttr($resource->getShortName(), $context);
        }

        return $context;
    }

    /**
     * Gets the base context.
     *
     * @return array
     */
    private function getBaseContext()
    {
        return [
            '@vocab' => '',
            'hydra' => '',
            'hydra:embed'
        ];
    }
}
