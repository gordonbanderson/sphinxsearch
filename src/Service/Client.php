<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 19:58 น.
 */

namespace Suilven\SphinxSearch\Service;


class Client
{
    /**
     * Get a connection to sphinx using the values configured in YML files for port and host
     *
     * @return Foolz\SphinxQL\Drivers\Pdo\Connection connection object to Sphinx
     */
    public function getConnection()
    {
        $host = $this->config()->get('host');
        $port = $this->config()->get('port');
        $conn = new \Foolz\SphinxQL\Drivers\Pdo\Connection();
        $conn->setParams(array('host' => $host, 'port' => $port));
        return $conn;
    }
}
