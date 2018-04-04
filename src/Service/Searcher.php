<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 25/3/2561
 * Time: 1:35 น.
 */

namespace Suilven\SphinxSearch\Service;


use Foolz\SphinxQL\Drivers\Pdo\ResultSet;
use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use Foolz\SphinxQL\SphinxQL;
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
        error_log('SIZE: ' . $this->pageSize);
        error_log('PAGE: ' . $this->page);

        $startMs = round(microtime(true) * 1000);
        $connection = $this->client->getConnection();

        // @todo make fields configurable?
        $result = SphinxQL::create($connection)->select('id')
            ->from([$this->index .'_index', $this->index  . '_rt'])
            ->match('?', $q)
            ->facet(Facet::create($connection)
                ->facet(array('shutterspeed'))
                ->orderBy('shutterspeed', 'ASC'))
            ->executeBatch()
            ->getStored();

        echo "\n\n\n\n";

        echo print_r($result, 1);
        die;
        ; // @todo try ? for wildcard


        // facets cause a break on show meta
       //  $facet = Facet::create($connection);
       // $facet->facet('iso');
     //   $query->facet($facet);


/*
        $result = SphinxQL::create(self::$conn)
            ->select()
            ->from('rt')
            ->facet(Facet::create($conn)
                ->facet(array('gid'))
                ->orderBy('gid', 'ASC'))
            ->executeBatch()
            ->getStored();
*/



        //SELECT * FROM flickr_index LIMIT 0,10 FACET lastedited;

      //  $query->limit(($this->page-1) * $this->pageSize, $this->pageSize);

        /** @var ResultSet $result */


        $ids = $result->fetchAllAssoc();
        $result->freeResult();

        echo "\n\n\n\n";

        echo print_r($result, 1);

        $metaQuery = SphinxQL::create($connection)->query('SHOW META;');
        $metaData = $metaQuery->execute();

        error_log('---- META DATA ----');
        echo '****************** >>>>>' . print_r($metaData, 1);


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
                $name = $indexObj->getName();
                if ($name == $this->index) {
                    $clazz = $indexObj->getClass();
                    break;
                }
            }

            $dataobject = DataObject::get_by_id($clazz, $assoc['id']);

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
            'Pagination' => $pagination
        ];
    }
}
