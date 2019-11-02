<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 25/3/2561
 * Time: 1:35 น.
 */

namespace Suilven\SphinxSearch\Service;

use SilverStripe\Core\Extensible;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Suilven\FreeTextSearch\Index;
use Suilven\FreeTextSearch\Indexes;

class Searcher
{

    use Extensible;

    private $client;

    private $pageSize = 10;

    private $page = 1;

    private $indexName = 'sitetree';

    /** @var Index */
    private $index = null;

    /**
     * @var array associative mapping of mvatitle to SS Class to search IDs against
     */
    private $mvaFields = [];

    /**
     * @var array tokens that are facetted, e.g. Aperture, BlogID
     */
    private $facettedTokens = [];

    /**
     * @var array tokens from MVA.  These will need some PHP love to get them renderable
     */
    private $hasManyTokens = [];

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
    public function setIndexName($indexName)
    {
        $this->indexName = $indexName;

        $indexesService = new Indexes();
        $indexesObj = $indexesService->getIndexes();
        foreach($indexesObj as $indexObj) {
            if($indexObj->getName() == $indexName) {
                $this->index = $indexObj;
                break;
            };
        }
    }


    /**
     * @param array $facettedTokens an array of facet column names in lowercase
     */
    public function setFacettedTokens($facettedTokens)
    {
        $this->facettedTokens = $facettedTokens;
    }

    public function setHasManyTokens($hasManyTokens)
    {
        // @todo This appears to work for MVA, but IDs are returned
       // array_push($facettedTokens, 'flickrtagid');
        $this->hasManyTokens = [];
        foreach($hasManyTokens as $token) {
            $token = substr($token, 0, -1);
            $token = strtolower($token);
            $this->hasManyTokens[] = $token . 'id';
        }
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
        $indexName = $sphinxSiteID . '_' . $this->index->getName();

        $query = (new SphinxQL($connection))->select('id')
            ->from([$indexName .'_index', $indexName  . '_rt']);

        // leaving $q empty searches for everything
        if (!empty($q)) {
            $query->match('?', $q);
        }


        // string int fixes needed here
        foreach($this->filters as $key => $value) {
            // skip the flush parameter
            if ($key == 'flush') {
                continue;
            }

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

        // this works at an OR level, not an AND one :(
       // $query->where('flickrtagid', 'IN', [39]);


        // add the tokens as facets
        foreach($this->facettedTokens as $tokenToFacet) {
            $facet = (new Facet($connection))->facet(array($tokenToFacet));
            $facet->orderBy("count(*)", "desc");

            // @todo Make configurable
            $facet->limit(1000);
            //$facet->orderBy('iso', 'desc');
            $query->facet($facet);
        }

        $hasManyTokens = $this->hasManyTokens;


        // add MVA as facets - note these will come back with numerical IDs only, cannot be sorted, and need PHP massage
        foreach($hasManyTokens as $tokenToFacet) {
            $this->getClassNameForMVATitle($tokenToFacet);
            $facet = (new Facet($connection))->facet([$tokenToFacet]);

            // @todo Make configurable
            $facet->limit(1000);
            $query->facet($facet);
        }


        // testing
       // $facet = (new Facet($connection))->facet('flickrtagid');
      //  $query->facet($facet);


        $query->limit(($this->page-1) * $this->pageSize, $this->pageSize);

        /** @var array $result */
        $result = $query->executeBatch()
            ->getStored();


        /** @var ResultSet $resultSet */
        $resultSet = $result[0];

        $ids = $resultSet->fetchAllAssoc();

        $ctr = 1;
        $facets = [];

        $tokens = array_merge($this->facettedTokens, $this->hasManyTokens);

        foreach($tokens as $token)
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
                    $filterKeys = array_keys($filterForFacet);

                    if (in_array($token, $hasManyTokens)) {
                        $name = $nextFacet['Name'];

                        if (in_array($name, $filterKeys) && isset($filterForFacet[$name])) {
                            // a break is needed here for the correct condition
                            if ($value != $filterForFacet[$name]) {
                                // if the values do not match, i.e. the MVA id does not match, skip rendering
                                // We only wish to show the selected ones
                                continue;
                            }
                        }
                    }

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

                    $nextFacet['Params'] = substr($urlParams, 0, -1) ;

                    $tokenFacets[] = $nextFacet;
                }
            }
            $ctr++;




            $mvaKeys = array_keys($this->mvaFields);


            if (in_array($token, $mvaKeys)) {
                $tokenFacets = $this->makeMVAFacetsHumanReadable($token, $tokenFacets);
            } else {
                $this->makeFacetsHumanReadable($token,$tokenFacets);
            }

           // list($token,$facets) =
            $facetTitle = $token;
            $facetResults = $tokenFacets;

            // this is coming back as an array, no idea why
            $this->extend('postProcessFacetTitle', $facetTitle);

            // @todo There is an extra layer of array being introduced here, cannot immediately see why.  This is currently
            // $result[0] holding the final result, it is correct at the exit point within the method
            $processedFacetResults = $this->extend('postProcessFacetResults', $facetTitle, $facetResults);


            $facets[] = ['Name' => $facetTitle, 'Facets' => new ArrayList($processedFacetResults[0])];
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
                if ($name == $indexName) {
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
                    $dataobject->Title . ' ' . $dataobject->Content, // @todo this is incorrect for photos as 2 lines above
                    $sphinxSiteID . '_' . $this->index->getName() . '_index',
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


    /**
     * @param $mavTitle This will be the lowercase name of a silverstripe relationship name, e.g. flickrtagid (from FlickrTags)
     */
    private function getClassNameForMVATitle($mvaTitle)
    {
        $clazz = $this->index->getClass();

        /** @var DataList $query */
        $singleton = singleton($clazz);

        /** @var DataObjectSchema $schema */
        $schema = $singleton->getSchema();

        foreach($this->index->getHasManyFields() as $field) {
            error_log('FIELD: ' . $field);
            error_log('---- specs ----');

            // @todo Test genuine has many as opposed to many many
            $specs = $schema->manyManyComponent($clazz, 'FlickrTags');
            $this->mvaFields[$mvaTitle] = $specs['childClass'];
        }

    }

    /**
     * @param $tokenFacets
     * @return array tokens in a more human readable form
     */
    private function makeMVAFacetsHumanReadable($token, $tokenFacets)
    {
        $ctr = 0;
        $result = [];

        $classname = $this->mvaFields[$token];

        foreach($tokenFacets as $facet) {
            // @todo make this more efficient
            $facet['Value'] = DataObject::get_by_id($classname, $facet['Value'])->RawValue;
            $result[] = $facet;
        }

        return $result;
    }

    private function makeFacetsHumanReadable($token, &$tokens)
    {
        // @todo
    }
}
