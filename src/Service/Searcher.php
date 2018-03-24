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
        $connection = $this->client->getConnection();
        $query = SphinxQL::create($connection)->select('id')

            // @todo hardwired
            ->from('SiteTree_index', 'SiteTree_rt')
            ->match('title', $q); // @todo try ? for wildcard

        $result = $query->execute();

        error_log('RESULTS: ' . $result->count());

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
                'SiteTree_index',
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

            echo print_r($snippets[0],1);

            $formattedResult = new ArrayData([
                'Record' => $dataobject
            ]);

            $formattedResults->push($formattedResult);

            error_log(print_r($snippets, 1));
        }

        return new ArrayData([
            'Records' => $formattedResults
        ]);
    }
}
