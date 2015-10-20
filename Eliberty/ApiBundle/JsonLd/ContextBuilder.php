<?php


namespace Eliberty\ApiBundle\JsonLd;

use Dunglas\ApiBundle\JsonLd\ContextBuilder as BaseContextBuilder;
use Dunglas\ApiBundle\Api\ResourceCollectionInterface;
use Dunglas\ApiBundle\Api\ResourceInterface;
use Dunglas\ApiBundle\Mapping\ClassMetadataFactory;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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
     * @var TransformerHelper
     */
    protected $transformerHelper;

    /**
     * @var ClassMetadataFactory
     */
    private $classMetadataFactory;

    /**
     * @param RouterInterface $router
     * @param EventDispatcherInterface $eventDispatcher
     * @param ResourceCollectionInterface $resourceCollection
     * @param ClassMetadataFactory $classMetadataFactory
     * @param TransformerHelper $transformerHelper
     */
    public function __construct(
        RouterInterface $router,
        EventDispatcherInterface $eventDispatcher,
        ResourceCollectionInterface $resourceCollection,
        ClassMetadataFactory $classMetadataFactory,
        TransformerHelper $transformerHelper
    ) {
        $this->classMetadataFactory = $classMetadataFactory;
        $this->transformerHelper = $transformerHelper;
        parent::__construct($router, $eventDispatcher, $resourceCollection);
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
            $this->transformerHelper->setClassMetadataFactory($this->classMetadataFactory);
            $this->transformerHelper->getOutputAttr($resource->getShortName(), $context);
            $embeds = $this->transformerHelper->getAvailableIncludes($resource->getShortName());
            $data['@embed'] = implode(',', $embeds);
            $context = array_merge($data, $context);
        }

        return $context;
    }
}
