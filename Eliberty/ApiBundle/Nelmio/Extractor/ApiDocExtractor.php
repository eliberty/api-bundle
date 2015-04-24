<?php

namespace Eliberty\ApiBundle\Nelmio\Extractor;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\Util\ClassUtils;
use Eliberty\ApiBundle\Helper\TransformerHelper;
use Eliberty\ApiBundle\Transformer\Listener\TransformerResolver;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\DataTypes;
use Nelmio\ApiDocBundle\Parser\ParserInterface;
use Nelmio\ApiDocBundle\Parser\PostParserInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
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
     * @param ContainerInterface $container
     * @param RouterInterface $router
     * @param Reader $reader
     * @param DocCommentExtractor $commentExtractor
     * @param array $handlers
     * @param TransformerHelper $transformerHelper
     */
    public function __construct(
        ContainerInterface $container,
        RouterInterface $router,
        Reader $reader,
        DocCommentExtractor $commentExtractor,
        array $handlers,
        TransformerHelper $transformerHelper
    ){
        $this->container        = $container;
        $this->router           = $router;
        $this->reader           = $reader;
        $this->commentExtractor = $commentExtractor;
        $this->handlers         = $handlers;
        $this->transformerHelper = $transformerHelper;
        parent::__construct($container, $router, $reader, $commentExtractor, $handlers);
    }

    /**
     * Returns a new ApiDoc instance with more data.
     *
     * @param  ApiDoc            $annotation
     * @param  Route             $route
     * @param  \ReflectionMethod $method
     * @return ApiDoc
     */
    protected function extractData(ApiDoc $annotation, Route $route, \ReflectionMethod $method)
    {
        // create a new annotation
        $annotation = clone $annotation;

        $annotation->addTag($this->router->getContext()->getApiVersion(),'#ff0000');
        // doc
        $annotation->setDocumentation($this->commentExtractor->getDocCommentText($method));

        // parse annotations
        $this->parseAnnotations($annotation, $route, $method);

        $resource = $route->getDefault('_resource');

        // route
        $annotation->setRoute($route);

        $entityClass = null;

        if ('DELETE' !== $annotation->getMethod()) {
            $entityClass = $this->transformerHelper->getEntityClass($resource);
        }

        // input (populates 'parameters' for the formatters)
        if (null !== $input = $entityClass) {

            $parameters      = array();
            $normalizedInput = $this->normalizeClassParameter($input);
            $supportedParsers = array();
            foreach ($this->getParsers($normalizedInput) as $parser) {
                if ($parser->supports($normalizedInput)) {
                    $supportedParsers[] = $parser;
                    $parameters         = $this->mergeParameters($parameters, $parser->parse($normalizedInput));

                }
            }

            $this->transformerHelper->getOutputAttr($resource, $parameters, 'doc');

            foreach ($supportedParsers as $parser) {
                if ($parser instanceof PostParserInterface) {
                    $parameters = $this->mergeParameters(
                        $parameters,
                        $parser->postParse($normalizedInput, $parameters)
                    );
                }
            }

            $parameters = $this->clearClasses($parameters);
            $parameters = $this->generateHumanReadableTypes($parameters);

            if ('PUT' === $annotation->getMethod()) {
                // All parameters are optional with PUT (update)
                array_walk($parameters, function ($val, $key) use (&$parameters) {
                    $parameters[$key]['required'] = false;
                });
            }

            $annotation->setParameters($parameters);
        }


        // output (populates 'response' for the formatters)
        if (null !== $output = $entityClass) {

            $response         = array();
            $supportedParsers = array();

            $normalizedOutput = $this->normalizeClassParameter($output);

            foreach ($this->getParsers($normalizedOutput) as $parser) {
                if ($parser->supports($normalizedOutput)) {
                    $supportedParsers[] = $parser;
                    $response = $this->mergeParameters($response, $parser->parse($normalizedOutput));
                }
            }

            foreach ($supportedParsers as $parser) {
                if ($parser instanceof PostParserInterface) {
                    $mp = $parser->postParse($normalizedOutput, $response);
                    $response = $this->mergeParameters($response, $mp);
                }
            }

            $this->transformerHelper->getOutputAttr($resource, $response, 'doc');
            $response = $this->clearClasses($response);
            $response = $this->generateHumanReadableTypes($response);

            $annotation->setResponse($response);
            $annotation->setResponseForStatusCode($response, $normalizedOutput, 200);
        }

        if (count($annotation->getResponseMap()) > 0) {

            foreach ($annotation->getResponseMap() as $code => $modelName) {

                if ('200' === (string) $code && isset($modelName['type']) && isset($modelName['model'])) {
                    /*
                     * Model was already parsed as the default `output` for this ApiDoc.
                     */
                    continue;
                }

                $normalizedModel = $this->normalizeClassParameter($modelName);

                $parameters = array();
                $supportedParsers = array();
                foreach ($this->getParsers($normalizedModel) as $parser) {
                    if ($parser->supports($normalizedModel)) {
                        $supportedParsers[] = $parser;
                        $parameters = $this->mergeParameters($parameters, $parser->parse($normalizedModel));
                    }
                }

                foreach ($supportedParsers as $parser) {
                    if ($parser instanceof PostParserInterface) {
                        $mp = $parser->postParse($normalizedModel, $parameters);
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


    private function getParsers(array $parameters)
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


}
