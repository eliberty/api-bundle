<?php

namespace Eliberty\ApiBundle\Transformer;

use Eliberty\ApiBundle\Fractal\Scope;
use Doctrine\ORM\EntityManager;
use Eliberty\ApiBundle\Fractal\Pagination\PagerfantaPaginatorAdapter;
use League\Fractal\TransformerAbstract;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Doctrine\Common\Collections\Collection;
use League\Fractal\Resource\Item;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class BaseTransformer.
 */
class BaseTransformer extends TransformerAbstract
{
    /**
     * Is the name of the current ressource.
     *
     * @var string
     */
    protected $currentResourceKey;

    /**
     * Is the scope parent embed.
     *
     * @var string
     */
    public $parentEmbed;

    /**
     * List of the url parameter options for embed.
     *
     * @var array
     */
    public $paramsByEmbed = [];

    /**
     * Uniform Resource Identifier.
     *
     * @var string
     */
    protected $uri;

    /**
     * Is the current embed for the scope.
     *
     * @var string
     */
    protected $currentEmbed;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var array
     */
    private $embeds = [];

    /**
     * List of the url parameter for embed.
     *
     * @var string
     */
    protected $requestEmbed;

    /**
     * List of resources possible to embed via this processor.
     *
     * @var array
     */
    protected $availableIncludes = [];

    /**
     * List of resources to automatically include.
     *
     * @var array
     */
    protected $defaultIncludes = [];

    /**
     * @var string
     */
    protected $entityClass;

    /**
     * Constructor.
     *
     * @param Request       $request
     * @param EntityManager $em
     *
     * @internal param Kernel $kernel
     */
    public function __construct(EntityManager $em, Request $request = null)
    {
        $this->em = $em;
    }

    /**
     * @return EntityManager
     */
    public function getEm()
    {
        return $this->em;
    }

    /**
     * @param Collection          $resource
     * @param TransformerAbstract $transformer
     *
     * @return Collection|\League\Fractal\Resource\Collection
     */
    public function paginate(Collection $resource, TransformerAbstract $transformer)
    {
        $this->initTransformer($transformer);

        if (!isset($this->embeds[$this->getEmbed()]) || null === $this->embeds[$this->getEmbed()]) {
            return $this->collection($resource, $transformer);
        }

        $optionEmbed = isset($this->embeds[$this->getEmbed()]) ? $this->embeds[$this->getEmbed()] : null;

        $collection = new Pagerfanta(new ArrayAdapter($resource->toArray()));

        $limit = isset($optionEmbed['perpage']) ? $optionEmbed['perpage'] : 10;

        $collection->setMaxPerPage($limit);

        $page = isset($optionEmbed['page']) && ($optionEmbed['page'] <= $collection->getNbPages()) ?
            $optionEmbed['page'] : 1;

        $collection->setCurrentPage($page);

        $resource = $this->collection($collection, $transformer);

        $adapter = $this->getAdapter($collection, $this->uri, $optionEmbed, $this->currentEmbed);

        $resource->setPaginator($adapter);

        return $resource;
    }

    /**
     * @param $collection
     * @param $uri
     * @param array $optionEmbed
     *
     * @return PagerfantaPaginatorAdapter
     */
    public function getAdapter($collection, $uri, $optionEmbed = [])
    {
        $currentEmbed = $this->getEmbed();

        $adapter = new PagerfantaPaginatorAdapter($collection, function ($page) use ($uri, $optionEmbed, $currentEmbed) {
            $i      = 0;
            $search = $replace = $currentEmbed;
            foreach ($optionEmbed as $property => $value) {
                if ($i === 0) {
                    $replace = $replace.'{';
                    $search  = $search.'{';
                }
                $i++;

                //find the last carac for build new option
                $endStr  = (count($optionEmbed) !== $i) ? ',' : '}';
                $search  = $search.'"'.trim($property).'"='.trim($value).$endStr;
                $prefixe = $replace.'"'.trim($property).'"=';

                $replace = ($property === 'page') ?
                    $prefixe.$page.$endStr :
                    $prefixe.trim($value).$endStr;
            }

            if (empty($optionEmbed)) {
                $search = $search.'{}';
            }

            //check if property page not existing add
            if (!isset($optionEmbed['page'])) {
                $replace = $replace !== $currentEmbed ?
                    str_replace('}', ',"page"='.$page.'}', $replace) :
                    $replace.'{"page"='.$page.'}';
            }

            //delete white space into uri
            $uriBaseTrim = str_replace(' ', '', $uri);
            //check if i find data int uri
            $isFind = preg_match('/[=|,]'.$search.'[,|{|&]/', $uriBaseTrim, $matches);

            //if embed is find
            if ((bool) $isFind && !empty($matches)) {
                $currentMatch   = array_shift($matches);
                $prefixeReplace = substr($currentMatch, 0, 1);
                $sufixeReplace  = substr($currentMatch, (strlen($currentMatch) - 1), 1);
                $uriBaseTrim    = str_replace($currentMatch, $prefixeReplace.$replace.$sufixeReplace, $uriBaseTrim);

                return urldecode($uriBaseTrim);
            }

            return urldecode(str_replace($search, $replace, $uriBaseTrim));
        });

        return $adapter;
    }

    /**
     * Create a new item resource object.
     *
     * @param mixed                    $data
     * @param BaseTransformer|callable $transformer
     * @param string                   $resourceKey
     *
     * @return Item
     */
    protected function item($data, $transformer, $resourceKey = null)
    {
        $this->initTransformer($transformer);

        return new Item($data, $transformer, $resourceKey);
    }

    /**
     * @param $transformer
     */
    protected function initTransformer(BaseTransformer $transformer)
    {
        $transformer
            ->setCurrentScope($this->currentScope)
            ->setParamRequestEmbed($this->requestEmbed)
            ->setParentEmbed($this->getEmbed());
    }

    /**
     * @param \Datetime $datetime
     *
     * @return null|string
     */
    protected function dateFormat(\Datetime $datetime = null)
    {
        if (null === $datetime) {
            return;
        }

        return $datetime->format(\DateTime::ISO8601);
    }

    /////Embed/////

    /**
     * @param $requestEmbed
     *
     * @return array
     */
    protected function getEmbeds($requestEmbed)
    {
        $explodeEmbeds    = preg_split("/[(a-zA-Z|}),],/", $requestEmbed, -1, PREG_SPLIT_OFFSET_CAPTURE);
        $embedWithOptions = [];
        foreach ($explodeEmbeds as $key => $value) {
            //if the current offset of capture is into the position 0
            if ($value[1] === 0) {
                $embedWithOptions[$key] = $value[0];
                continue;
            }
            //else get the last carac of the embed because is split with the preg_split Function
            $lastCarac                  = $value[1] - 2;
            $lastCarac                  = str_split($requestEmbed, 1)[$lastCarac];
            $embedWithOptions[$key - 1] = $explodeEmbeds[$key - 1][0].$lastCarac;
            $embedWithOptions[$key]     = $explodeEmbeds[$key][0];
        }

        return $this->getSplitEmbedOption($embedWithOptions);
    }

    /**
     * split the embed name and the option embed.
     *
     * @param array $embedWithOptions
     *
     * @return array
     */
    protected function getSplitEmbedOption($embedWithOptions = [])
    {
        $embedAndOption = [];
        foreach ($embedWithOptions as $key => $value) {
            $tab     = explode('{', $value);
            $options = count($tab) > 1 ? '{'.array_pop($tab) : null;

            $data = $options !== null ? (array) json_decode(str_replace('=', ':', $options)) : null;

            $embedAndOption[array_shift($tab)] = $data;
        }

        return $this->getEmbedsWithParentEmbed($embedAndOption);
    }

    /**
     * include the sub Embed if contact.addresses then include contact.
     *
     * @param $splitEmbedOption
     * c'est le mal *****************************************
     *
     * @return array
     */
    protected function getEmbedsWithParentEmbed($splitEmbedOption)
    {

        foreach ($splitEmbedOption as $embed => $options) {
            $scopeEmbed = explode('.', $embed);
            if (count($scopeEmbed) === 1) {
                continue;
            }
            foreach ($scopeEmbed as $key => $embedName) {
                $parent = implode('.', array_slice($scopeEmbed, 0, $key));
                $parent = !empty($parent) ? $parent.'.' : '';
                if (!isset($splitEmbedOption[$parent.$embedName])) {
                    $splitEmbedOption[$parent.$embedName] = null;
                }
            }
        }

        return $splitEmbedOption;
    }

    ///////////////SETTER////////////
    /**
     * @param Request $request
     *
     * @return $this
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
        $this->setParamRequestEmbed();

        if (null !== $request) {
            $this->uri     = $this->request->getPathInfo();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setParamRequestEmbed()
    {
        if ($this->request) {
            $this->requestEmbed = $this->request->get('embed');
            $this->embeds       = $this->getEmbeds($this->requestEmbed);
        }

        return $this;
    }

    /**
     * @param $embed
     */
    public function setEmbed($embed)
    {
        $this->currentEmbed = $embed;
    }

    /**
     * @param string $parentEmbed
     *
     * @return $this
     */
    public function setParentEmbed($parentEmbed)
    {
        $this->parentEmbed = $parentEmbed;

        return $this;
    }

    ///////////////GETTER////////////

    /**
     * @return string
     */
    private function getEmbed()
    {
        return !empty($this->parentEmbed) ? $this->parentEmbed.'.'.$this->currentEmbed : $this->currentEmbed;
    }

    /**
     * Getter the current resource key for the transformer.
     * @return string
     */
    public function getCurrentResourceKey()
    {
        return $this->currentResourceKey;
    }

    /**
     * Getter available includes for current transformer.
     * @return array
     */
    public function getAvailableIncludes()
    {
        return $this->availableIncludes;
    }

    /**
     * Getter default includes for current transformer.
     * @return array
     */
    public function getDefaultIncludes()
    {
        return $this->defaultIncludes;
    }

    /**
     * Getter for current entity Class.
     * @return string
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * Getter for currentScope.
     *
     * @return \Eliberty\ApiBundle\Fractal\Scope
     */
    public function getCurrentScope()
    {
        return $this->currentScope;
    }

    /**
     * is the child transformer
     * @return bool
     */
    public function isChild()
    {
        return !is_null($this->parentEmbed);
    }

    //transformer abstract fractal
    /**
     * This method is fired to loop through available includes, see if any of
     * them are requested and permitted for this scope.
     *
     * @internal
     *
     * @param Scope $scope
     * @param mixed $data
     *
     * @return array
     */
    public function processIncludedResources(Scope $scope, $data)
    {
        $includedData = parent::processIncludedResources($scope, $data);
        if (is_array($includedData)) {
            foreach ($includedData as $include => $data) {
                if (false !== strpos($include, 'childrens')) {
                    $key                = str_replace($this->currentResourceKey, '', $include);
                    $includedData[$key] = $includedData[$include];
                    unset($includedData[$include]);
                }
            }
        }

        return $includedData;
    }


    /**
     * Call Include Method.
     *
     * @internal
     *
     * @param Scope  $scope
     * @param string $includeName
     * @param mixed  $data
     *
     * @throws \Exception
     *
     * @return \League\Fractal\Resource\ResourceInterface
     */
    protected function callIncludeMethod(Scope $scope, $includeName, $data)
    {
        $scope->setData($data);
        return parent::callIncludeMethod($scope, $includeName, $data);
    }
}
