<?php

namespace Eliberty\ApiBundle\Fractal\Pagination;

/**
 * Class PaginationUrlBuilder
 *
 * Builds the hydra pagination urls of a paged collection.
 *
 * Those urls must carry the filters, the sort and the embeds of the current request: rebuilt
 * from the bare collection route, they silently paginate over the whole unfiltered collection.
 *
 * @package Eliberty\ApiBundle\Fractal\Pagination
 */
class PaginationUrlBuilder
{
    /**
     * @var string
     */
    const PAGE_PARAMETER = 'page';

    /**
     * @var string
     */
    const ITEMS_PER_PAGE_PARAMETER = 'perpage';

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * Filters, sort and embeds of the current request, without the pagination parameters.
     *
     * @var array
     */
    private $queryParameters;

    /**
     * @var mixed
     */
    private $itemsPerPage;

    /**
     * @param string $baseUrl         route of the collection, without any query string
     * @param array  $queryParameters query parameters of the current request
     * @param mixed  $itemsPerPage    page size the paginator actually applied
     */
    public function __construct($baseUrl, array $queryParameters, $itemsPerPage)
    {
        unset($queryParameters[self::PAGE_PARAMETER], $queryParameters[self::ITEMS_PER_PAGE_PARAMETER]);

        $this->baseUrl         = (string) $baseUrl;
        $this->queryParameters = $queryParameters;
        $this->itemsPerPage    = $itemsPerPage;
    }

    /**
     * Url a page number is appended to, e.g. "/api/orders?perpage=50&embed=orderitems&page=".
     *
     * @return string
     */
    public function getPageUrlPrefix()
    {
        $parameters = array_merge(
            [self::ITEMS_PER_PAGE_PARAMETER => $this->itemsPerPage],
            $this->queryParameters
        );

        return $this->baseUrl.'?'.$this->buildQuery($parameters).'&'.self::PAGE_PARAMETER.'=';
    }

    /**
     * The first page keeps its historical bare route form when there is no filter to carry over.
     *
     * @return string
     */
    public function getFirstPageUrl()
    {
        if (empty($this->queryParameters)) {
            return $this->baseUrl;
        }

        return $this->getPageUrlPrefix().'1';
    }

    /**
     * @param array $parameters
     *
     * @return string
     */
    private function buildQuery(array $parameters)
    {
        $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        // Keep list parameters as foo[]=a&foo[]=b rather than foo[0]=a&foo[1]=b
        return preg_replace('/%5B\d+%5D/', '%5B%5D', $query);
    }
}
