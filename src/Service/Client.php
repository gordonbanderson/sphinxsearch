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

        error_log('HOST: ' . $host);
        error_log('PORT: ' . $port);


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

    public function restartServer()
    {
        $restartCommand = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'cmd_restart');
        exec($restartCommand);
    }


    /**
     * Execute reindex command.  @todo Can this be done using SphinxQL?
     */
    public function reindex()
    {
        $reindexCommand = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'cmd_reindex');

        // @todo remove error logs
        error_log('> Running reindexer, command is ' . $reindexCommand);
        error_log(exec('ls -lh /var/lib/sphinxsearch/data'));
        error_log(exec('whoami'));
        error_log(exec('/usr/bin/indexer --config /etc/sphinxsearch/sphinx.conf --verbose --all'));
        error_log('After indexing');
        error_log(exec('ls -lh /var/lib/sphinxsearch/data'));
        error_log(exec('cat /etc/sphinxsearch/sphinx.conf'));
        error_log('---- sphinx tables after indexer run ----');
        error_log(exec("mysql --host=127.0.01 --port=9306 -e 'show tables';"));
        error_log(print_r(exec('service sphinxsearch restart && sleep 20'), 1));
        error_log('--- did that sleep 20 seconds? ---');
    }
}
