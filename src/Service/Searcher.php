<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 25/3/2561
 * Time: 1:35 น.
 */

namespace Suilven\SphinxSearch\Service;

use Foolz\SphinxQL\Drivers\Pdo\Connection;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Suilven\FreeTextSearch\Indexes;

class Searcher
{

    private $client;

    private $pageSize = 10;

    private $page = 1;

    private $index = 'sitetree';

    /**
     * @var array tokens that are facetted, e.g. Aperture, BlogID
     */
    private $facettedTokens = [];

    /**
     * @var array associative array of filters against tokens
     */
    private $filters = [];

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @param int $pageSize
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
    }

    /**
     * @param string $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }


    /**
     * @param array $facettedTokens
     */
    public function setFacettedTokens($facettedTokens)
    {
        $this->facettedTokens = $facettedTokens;
    }


    /**
     * @param int $page
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    public function __construct()
    {
        $this->client = new Client();
    }


    public function search($q)
    {
        $sphinxSiteID = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'site_id');

        $startMs = round(microtime(true) * 1000);
        $connection = $this->client->getConnection();

        // @todo make fields configurable?
        $siteIndex = $sphinxSiteID . '_' . $this->index;



       // $conn = new Connection();
       // $host = $config = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'host');
       // $port = $config = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'port');

        $query = (new SphinxQL($connection))->select('id')
            ->from([$siteIndex .'_index', $siteIndex  . '_rt']);

        if (!empty($q)) {
            $query->match('?', $q);
        }


        // string int fixes needed here
        foreach($this->filters as $key => $value) {
            if ($key !== 'q') {
                if (ctype_digit($value)) {
                    if (is_int($value + 0)) {
                        $value = (int) $value;
                    }
                    else if (is_float($value + 0)) {
                        $value = (float) $value;
                    }
                }

                $query->where($key, $value);
            }
        }

        foreach($this->facettedTokens as $tokenToFacet) {
            $facet = (new Facet($connection))->facet(array($tokenToFacet));
            $query->facet($facet);
        }


        $query->limit(($this->page-1) * $this->pageSize, $this->pageSize);


        /** @var array $result */
        $result = $query->executeBatch()
            ->getStored();

        /** @var ResultSet $resultSet */
        $resultSet = $result[0];

        $ids = $resultSet->fetchAllAssoc();

        $ctr = 1;
        $facets = [];
        foreach($this->facettedTokens as $token)
        {
            $resultSet = $result[$ctr];
            $rawFacets = $resultSet->fetchAllAssoc();
            $tokenFacets = [];
            foreach($rawFacets as $singleFacet) {
                if (isset($singleFacet[$token])) {
                    $value = $singleFacet[$token];
                    $count = $singleFacet['count(*)'];

                    // do this way to maintain order from Sphinx
                    $nextFacet = ['Value' => $value, 'Count' => $count, 'Name' => $token, 'ExtraParam' => "$token=$value"];
                    $filterForFacet = $this->filters;

                    if (isset($this->filters[$token])) {
                        $nextFacet['Selected'] = true;
                        unset($filterForFacet[$token]);
                    } else {
                        // additional value to the URL, unselected facet
                        $filterForFacet[$token] = $value;
                    }

                    // @todo - escaping?
                    $urlParams = '';
                    foreach($filterForFacet as $n => $v) {
                        //if (!isset($this->filters[$token])) {
                            $urlParams .= "{$n}={$v}&";
                       // }
                    }

                    $nextFacet['Params'] = substr($urlParams, 0, -1);

                    $tokenFacets[] = $nextFacet;
                }
            }
            $ctr++;

            // @todo human readable title
            $facets[] = ['Name' => $token, 'Facets' => new ArrayList($tokenFacets)];
        }

        /**
         * // create a SphinxQL Connection object to use with SphinxQL
        $conn = new Connection();
        $conn->setParams(array('host' => 'domain.tld', 'port' => 9306));

        $query = (new SphinxQL($conn))->select('column_one', 'colume_two')
        ->from('index_ancient', 'index_main', 'index_delta')
        ->match('comment', 'my opinion is superior to yours')
        ->where('banned', '=', 1);

        $result = $query->execute();
         */

        $sphinxql = new SphinxQL($connection);
        $metaQuery = $sphinxql->query('SHOW META;');
        $metaData = $metaQuery->execute();

        $searchInfo = [];
        foreach($metaData->getStored() as $info) {
            $varname = $info['Variable_name'];
            $value = $info['Value'];
            $searchInfo[$varname] = $value;
        }

        $formattedResults = new ArrayList();

        foreach($ids as $assoc) {
            // @todo use array merge to minimize db queries
            // @todo need to get this from the index definition

            $indexesService = new Indexes();
            $indexes = $indexesService->getIndexes();

            $clazz = '';

            // @todo fix this, return an associative array from the above
            foreach($indexes as $indexObj)
            {
                $name = $sphinxSiteID . '_' . $indexObj->getName();
                echo $name;
                if ($name == $siteIndex) {
                    $clazz = $indexObj->getClass();
                    break;
                }
            }

            $dataobject = DataObject::get_by_id($clazz, $assoc['id']);

            // Get highlight snippets, but only if a query parameter was passed in
            if (!empty($q)) {
                //(new Helper($conn))-
                $snippets = (new Helper($connection))->callSnippets(
                // @todo get from index, need all text fields
                    // @todo make part of index configuration
                    $dataobject->Title . ' ' . $dataobject->Content,
                    //@todo hardwired
                    $sphinxSiteID . '_sitetree_index',
                    $q,
                    [
                        // @todo Make configurable
                        'around' => 10,
                        'limit' => 200,
                        'before_match' => '<span class="highlight">',
                        'after_match' => '</span>',
                        'chunk_separator' => ' ... ',
                        'html_strip_mode' => 'strip',
                    ]
                )->execute()->getStored();
                $dataobject->Snippets = $snippets[0]['snippet'];
            }

            $formattedResult = new ArrayData([
                'Record' => $dataobject
            ]);

            $formattedResults->push($formattedResult);
        }

        $elapsed = round(microtime(true) * 1000) - $startMs;

        $pagination = new PaginatedList($formattedResults);
        $pagination->setCurrentPage($this->page);
        $pagination->setPageLength($this->pageSize);
        $pagination->setTotalItems($searchInfo['total_found']);



        return [
            'Records' => $formattedResults,
            'PageSize' => $this->pageSize,
            'Page' => $this->page,
            'TotalPages' => 1+round($searchInfo['total_found'] / $this->pageSize),
            'ResultsFound' => $searchInfo['total_found'],
            'Time' => $elapsed/1000.0,
            'Pagination' => $pagination,
            'AllFacets' => empty($facets) ? False : new ArrayList($facets)
        ];
    }
}
