<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 0:51 น.
 */

namespace Suilven\SphinxSearch\Tests;


use Foolz\SphinxQL\Facet;
use Foolz\SphinxQL\Helper;
use SilverStripe\Dev\SapphireTest;

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Connection;
use SilverStripe\ORM\DataObject;

class SphinxTest extends SapphireTest
{
    public function setUp()
    {
        parent::setUp();
    }

    public function testSearch()
    {
        error_log('Test search');
        // create a SphinxQL Connection object to use with SphinxQL
        $conn = new \Foolz\SphinxQL\Drivers\Pdo\Connection();
        $conn->setParams(array('host' => 'sphinx', 'port' => 9306));

       // $query = SphinxQL::create($conn)->select('column_one', 'colume_two')
       //     ->from('index_ancient', 'index_main', 'index_delta')
       //     ->match('comment', 'my opinion is superior to yours')
       //     ->where('banned', '=', 1);

        $searchText = 'the';

        $query = SphinxQL::create($conn)->select('id')
            ->from('myapp_index', 'myapp_rt')
            ->match('comment', $searchText);

        $result = $query->execute();

        error_log('RESULTS: ' . $result->count());
        foreach($result->fetchAllAssoc() as $assoc) {
            error_log(print_r($assoc, 1));
            $comment = DataObject::get_by_id('SilverStripe\Comments\Model\Comment', $assoc['id']);

            //5 AS around, 200 AS limit,
            $snippets = Helper::create($conn)->callSnippets(
                $comment->Comment,
                'myapp_index',
                $searchText,
                [
                    'around' => 10,
                    'limit' => 200,
                    'before_match' => '*',
                    'after_match' => '*',
                    'chunk_separator' => '...',
                    'html_strip_mode' => 'strip',
                ]
            )->execute()->getStored();
            error_log(print_r($snippets, 1));

        }

        /*
         * SELECT *, IN(brand_id,1,2,3,4) AS b FROM facetdemo WHERE MATCH('Product') AND b=1 LIMIT 0,10
FACET brand_name, brand_id BY brand_id ORDER BY brand_id ASC
FACET property ORDER BY COUNT(*) DESC
FACET INTERVAL(price,200,400,600,800) ORDER BY FACET() ASC
FACET categories ORDER BY FACET() ASC;
         */
        error_log('---- facets ----');

        $facet = Facet::create($conn)
            ->facet(array('parentid', 'id'))
            ->getFacet();

        $query = SphinxQL::create($conn)->select('id')
            ->from('myapp_index', 'myapp_rt')
            ->match('comment', $searchText)
            ->facet(
                Facet::create($conn)
                    ->facet(array('parentid'))
            );

        $result = $query->execute();

        error_log('RESULTS: ' . print_r($result->fetchAllAssoc(), 1));

    }
}
