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

class Suggester
{

    private $client;

    private $index = 'sitetree';

    /**
     * @param string $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }


    public function __construct()
    {
        $this->client = new Client();
    }


    public function suggest($q)
    {
        $suggestions = [];

        if (!empty($q)) {
            $connection = $this->client->getConnection();
            $e = $this->client->escapeSphinxQL($q);
            $indexName = $this->index . '_index';
            $query = SphinxQL::create($connection)->query("CALL QSUGGEST('$e', '{$indexName}')");
            $result = $query->execute()
                ->getStored();

           // @todo FIX Can we return multiple results and also can we pass in multiple words
            // result returns a string then a couple of numbers, no idea what the numbers are
            $suggestions = $result[0]['suggest'];

        }

        return [$suggestions];

    }
}
