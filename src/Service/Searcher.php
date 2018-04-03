<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 25/3/2561
 * Time: 1:35 น.
 */

namespace Suilven\SphinxSearch\Service;


use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;

class Searcher
{
    private $indexNames = null;

    private $client;

    private $pageSize = 10;

    private $page = 1;

    /**
     * @param int $pageSize
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;
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

    public function setIndexes($indexNames)
    {
        $this->indexNames = $indexNames;
    }

    public function search($q)
    {
        $startMs = round(microtime(true) * 1000);
        $connection = $this->client->getConnection();
        $query = SphinxQL::create($connection)->select('id')

            // @todo hardwired
            ->from('sitetree_index', 'sitetree_rt')
            ->match('title', $q); // @todo try ? for wildcard

        $query->limit(($this->page-1) * $this->pageSize, $this->pageSize);
        $result = $query->execute();

        $metaQuery = SphinxQL::create($connection)->query('SHOW META;');
        $metaData = $metaQuery->execute();

        error_log('---- META QUERY ----');

        $searchInfo = [];
        foreach($metaData->getStored() as $info) {
            $varname = $info['Variable_name'];
            $value = $info['Value'];
            $searchInfo[$varname] = $value;
        }

        $formattedResults = new ArrayList();

        foreach($result->fetchAllAssoc() as $assoc) {
            // @todo use array merge to minimize db queries
            // @todo need to get this from the index definition
            $dataobject = DataObject::get_by_id('SilverStripe\CMS\Model\SiteTree', $assoc['id']);

            // Get highlight snippets
            $snippets = Helper::create($connection)->callSnippets(
                // @todo get from index, need all text fields
                $dataobject->Title . ' ' . $dataobject->Content,
                //@todo hardwired
                'sitetree_index',
                $q,
                [
                    'around' => 10,
                    'limit' => 200,
                    'before_match' => '<b>',
                    'after_match' => '</b>',
                    'chunk_separator' => '...',
                    'html_strip_mode' => 'strip',
                ]
            )->execute()->getStored();

            $dataobject->Snippets = $snippets[0]['snippet'];

            $formattedResult = new ArrayData([
                'Record' => $dataobject
            ]);

            $formattedResults->push($formattedResult);
        }

        $elapsed = round(microtime(true) * 1000) - $startMs;

        return [
            'Records' => $formattedResults,
            'PageSize' => $this->pageSize,
            'Page' => $this->page,
            'TotalPages' => 1+round($searchInfo['total_found'] / $this->pageSize),
            'ResultsFound' => $searchInfo['total_found'],
            'Time' => $elapsed/1000.0
        ];
    }
}
