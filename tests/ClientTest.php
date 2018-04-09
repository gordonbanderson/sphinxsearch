<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 20:36 น.
 */

namespace Suilven\SphinxSearch\Tests;


use SilverStripe\Dev\SapphireTest;
use Suilven\FreeTextSearch\Indexes;
use Suilven\SphinxSearch\Service\Client;
use Suilven\SphinxSearch\Service\Indexer;

class ClientTest extends SapphireTest
{
    public function testClient()
    {
        $indexesService = new Indexes();
        $indexes = $indexesService->getIndexes();
        $indexer = new Indexer($indexes);
        $indexer->saveConfig();
    }
}
