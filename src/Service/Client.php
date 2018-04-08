<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 19:58 น.
 */

namespace Suilven\SphinxSearch\Service;


use SilverStripe\Core\Config\Config;

class Client
{
    /**
     * Get a connection to sphinx using the values configured in YML files for port and host
     *
     * @return Foolz\SphinxQL\Drivers\Pdo\Connection connection object to Sphinx
     */
    public function getConnection()
    {
        $host = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'host');
        $port = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'port');
        $conn = new \Foolz\SphinxQL\Drivers\Pdo\Connection();
        $conn->setParams(array('host' => $host, 'port' => $port));
        return $conn;
    }

    // see https://stackoverflow.com/questions/6275042/how-to-escape-special-characters-in-sphinxql-fulltext-search
    function escapeSphinxQL ( $string )
    {
        $from = array ( '\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=', "'", "\x00", "\n", "\r", "\x1a" );
        $to   = array ( '\\\\', '\\\(','\\\)','\\\|','\\\-','\\\!','\\\@','\\\~','\\\"', '\\\&', '\\\/', '\\\^', '\\\$', '\\\=', "\\'", "\\x00", "\\n", "\\r", "\\x1a" );
        return str_replace ( $from, $to, $string );
    }
}
