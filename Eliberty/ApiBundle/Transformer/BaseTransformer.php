<?php

namespace Eliberty\ApiBundle\Transformer;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
use League\Fractal\Scope;
use Doctrine\ORM\EntityManager;
use Eliberty\ApiBundle\Fractal\Pagination\PagerfantaPaginatorAdapter;
use League\Fractal\TransformerAbstract;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Doctrine\Common\Collections\Criteria;
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
    protected $embeds = [];

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
     * check if user have overwride the default value
     * @var bool
     */
    public $overwrideDefaultIncludes = false;

    /**
     * @var Array
     */
    public $objectTranslations = [];

    /**
     * Constructor.
     *
     * @param EntityManager $em
     *
     * @internal param Kernel $kernel
     */
    public function __construct(EntityManager $em)
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

        return $this->collection($resource, $transformer);
    }

    /**
     * @return string
     */
    public function getCurrentEmbed()
    {
        return $this->currentEmbed;
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
     * @return array
     */
    public function getRequestEmbeds() {
        return $this->embeds;
    }
    /**
     * @param \Datetime $datetime
     *
     * @return null|string
     */
    protected function dateFormat(\Datetime $datetime = null, $format = \DateTime::ISO8601)
    {
        if (null === $datetime) {
            return null;
        }

        return $datetime->format($format);
    }

    /////Embed//////////////////////////////////////////////////////////////////////

    /**
     * @param $requestEmbed
     *
     * @return array
     */
    protected function getEmbeds($requestEmbed)
    {
        $embeds = preg_split("/[(a-zA-Z|}),],/", $requestEmbed, -1, PREG_SPLIT_OFFSET_CAPTURE);

        return $this->getEmbedsWithParentEmbed($embeds);
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
            $this->requestEmbed = $this->request->query->get('embed');
            $this->setEmbeds($this->getEmbeds($this->requestEmbed));
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
     * @param $embeds
     */
    public function setEmbeds($embeds)
    {
        $this->embeds = $embeds;
        foreach ($embeds as $key => $params) {
            $parentKey = str_replace($this->currentResourceKey.'.', '', $key);
            if (array_search($parentKey, $this->availableIncludes)) {
                $this->defaultIncludes[] = $parentKey;
                $this->overwrideDefaultIncludes = true;
            }
        }
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

    /////Transformer abstract fractal//////////////////////////////////////////////////////////////////////
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
        if (method_exists($scope , 'setData')) {
            $scope->setData($data);
        }
        return parent::callIncludeMethod($scope, $includeName, $data);
    }

    #region managements of locals
    /**
     *  get the multi language for a entity and field
     * @param $object
     * @param $field
     * @return array|mixed
     */
    protected function getMutliLanguages($object, $field)
    {
        if (!$this->request->headers->get('e-languages-available', 0)) {
            return $this->getDefaultTranslation($object, $field);
        }

        if (!isset($this->objectTranslations[$object->getId()])){
            $this->objectTranslations[$object->getId()] = new \Doctrine\Common\Collections\ArrayCollection();
        }

        $dataResponse = [];
        $translations = $this->getTranslationsFields($object, $field);

        foreach ($translations as $trans) {
            $dataResponse[$trans->getLocale()] = $trans->getContent();
        }

        return empty($dataResponse) ? $this->getDefaultTranslation($object, $field) : $dataResponse;
    }

    /**
     * @param $object
     * @param $field
     * @return mixed
     */
    public function getDefaultTranslation($object, $field) {
        return $this->em->getClassMetadata(get_class($object))->getFieldValue($object, $field);
    }

    /**
     * @param $object
     * @param $field
     * @return mixed
     */
    public function getTranslationsFields($object, $field)
    {

        if ($this->objectTranslations[$object->getId()]->count() > 0) {
            return $this->getCurrentTranslation($object, $field);
        }

        $repoTranslation = $this->em->getRepository('Gedmo\\Translatable\\Entity\\Translation');
        $languages = array_merge(
            $this->request->getLanguages(),
            [
                $this->request->getDefaultLocale()
            ]
        );

        $elements = $repoTranslation->findBy([
            'foreignKey'  => $object->getId(),
            'objectClass' => get_class($object),
            'locale'      => $languages
        ]);

        foreach($elements as $element){
            $this->objectTranslations[$object->getId()]->add($element);
        }

        return $this->getCurrentTranslation($object, $field);
    }

    /**
     * @return Collection
     */
    public function getCurrentTranslation($object, $field) {
        $criteria = Criteria::create()->where(Criteria::expr()->eq("field", $field));

        return $this->objectTranslations[$object->getId()]->matching($criteria);
    }

    #endregion

    /**
     * @param $entity
     * @return null
     */
    public function getIdOrNull($entity) {

        if (is_null($entity)) {
            return null;
        }

        if ($entity instanceof Collection) {
            $response = [];
            if ($entity->count() > 0) {
                foreach ($entity as $data) {
                    $response[] = $data->getId();
                }
            }

            return $response;
        }


        return $entity->getId();
    }

}
