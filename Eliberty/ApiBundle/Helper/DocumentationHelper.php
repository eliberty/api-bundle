<?php

namespace Eliberty\ApiBundle\Helper;
/*
 * This file is part of the ElibertyApiBundle package.
 *
 * (c) Vesin Philippe <pvesin@eliberty.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Nelmio\ApiDocBundle\Parser\JmsMetadataParser;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Eliberty\ApiBundle\Api\Resource as DunglasResource;
use Nelmio\ApiDocBundle\Parser\ParserInterface;
use Nelmio\ApiDocBundle\Parser\PostParserInterface;
use Doctrine\Common\Annotations\Reader;
/**
 * Class DocumentationHelper
 */
class DocumentationHelper {

    const ANNOTATION_CLASS = 'Nelmio\\ApiDocBundle\\Annotation\\ApiDoc';
    /**
     * @var Reader
     */
    private $reader;
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ParserInterface[]
     */
    protected $parsers = array();
    /**
     * @var TransformerHelper
     */
    public $transformerHelper;

    /**
     * @param ControllerNameParser $controllerNameParser
     * @param Reader $reader
     * @param TransformerHelper $transformerHelper
     * @param ContainerInterface $container
     */
    public function __construct(
        ControllerNameParser $controllerNameParser,
        Reader $reader,
        TransformerHelper $transformerHelper,
        ContainerInterface $container
    ) {
        $this->controllerNameParser  = $controllerNameParser;
        $this->container = $container;
        $this->reader = $reader;
        $this->transformerHelper = $transformerHelper;
    }


    /**
     * @param $methodDoc
     * @return null|object
     */
    public function getMethodAnnotation($methodDoc) {
        return $this->reader->getMethodAnnotation($methodDoc, self::ANNOTATION_CLASS);
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
    public function normalizeClassParameter($input, DunglasResource $resource)
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
