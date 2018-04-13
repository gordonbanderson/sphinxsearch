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
use Model\Photo;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

use Foolz\SphinxQL\SphinxQL;
use Foolz\SphinxQL\Connection;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Suilven\FreeTextSearch\Indexes;
use Suilven\SphinxSearch\Service\Client;
use Suilven\SphinxSearch\Service\Indexer;
use Suilven\SphinxSearch\Service\Searcher;

class SphinxTest extends SapphireTest
{
    protected static $fixture_file = 'fixtures.yml';

    const INDEX_NAME = 'photographs';


    protected static $extra_dataobjects = [
        Photo::class
    ];


    /**
     * Recreate the sphinx index clean each time
     */
    public function setUp()
    {
        parent::setUp();

        $indexes = [
            [
                // @todo, this does not look right
                'index' => [
                    'name' => self::INDEX_NAME,
                    'class' => 'Model\Photo',
                    'fields' => [
                        'Title',
                        'Description'
                    ]
                ]
            ]
        ];

        Config::nest();

        Config::inst()->update('Suilven\FreeTextSearch\Indexes', 'indexes', $indexes);



        $database = DB::get_conn()->getSelectedDatabase();
        $databaseHost = DB::get_conn()->getDatabaseServer();

        error_log('TEMP DB: ' . print_r($database, 1));
        error_log('TEMP DB HOST: ' . print_r($databaseHost, 1));


        error_log('SHOW TABLES, circle test, ss_tmp');
        error_log(exec("mysql --host=127.0.0.1 -pubuntu circle_test -e 'show tables;';"));
        error_log(exec("mysql --host=127.0.0.1 -pubuntu {$database} -e 'show tables;';"));

        error_log('SHOW MODELS, circle test, ss_tmp');
        error_log(exec("mysql --host=127.0.0.1 -pubuntu {$database} -e 'select * from Model_Photo;';"));
        error_log(exec("mysql --host=127.0.0.1 -pubuntu {$database} -e 'select count(*) from Model_Photo;';"));
        error_log(exec("mysql --host=127.0.0.1 -pubuntu circle_test -e 'select * from Model_Photo;';"));
        error_log(exec("mysql --host=127.0.0.1 -pubuntu circle_test -e 'select count(*) from Model_Photo;';"));
        //error_log(exec("mysql --host={$databaseHost} -pubuntu       {$database} -e 'show tables';"));
        error_log(exec('cat /var/www/.env'));


        error_log('---- data from silverstripe perspective ----');
        foreach(Photo::get() as $photo) {
            error_log('FROM DB: ' . $photo->Title);
        }


        // save config
        $indexesService = new Indexes();
        $indexesObj = $indexesService->getIndexes();
        $indexer = new Indexer($indexesObj);
        $indexer->setDatabaseName($database);
        $indexer->saveConfig();

        // perhaps should be Server instead of Client
        $client = new Client();
        $client->reindex();
    }

    public function test_search()
    {
        $searcher = new Searcher();
        $searcher->setIndex(self::INDEX_NAME);
        $results = $searcher->search('Central Bangkok');
        error_log(print_r($results, 1));

    }
}
