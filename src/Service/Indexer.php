<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 21:14 น.
 */

namespace Suilven\SphinxSearch\Service;


use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\DataList;
use SilverStripe\View\ArrayData;
use Suilven\FreeTextSearch\Index;
use Suilven\FreeTextSearch\Indexes;

class Indexer
{
    /**
     * @var null|Indexes indexes in current context
     */
    private $indexes = null;

    /**
     * Indexer constructor.
     * @param Indexes $indexes indexes in context
     */
    public function __construct($indexes)
    {
        $this->indexes = $indexes;
    }

    /**
     * Generate config
     *
     * @todo Generic names, just want to get a first cut working :)
     *
     * @return array of filename => sphinx config
     */
    public function generateConfig()
    {
        $allConfigs = [];
        /** @var Index $index */
        foreach($this->indexes as $index)
        {
            $className = $index->getClazz();
            $name = $index->getName();
            $fields = []; // ['ID', 'CreatedAt', 'LastEdited'];

            // @todo different field types
            foreach($index->getFields() as $field)
            {
                $fields[] = $field;
            }

            /** @var DataList $query */
            $queryObject = singleton($className)::get()->setQueriedColumns(['Title', 'Content']);

            // this needs massages for sphinx
            $sql = $queryObject->sql();
            $sql = str_replace('"', '`', $sql);

            // need to move ID to first param
            $sql = str_replace('`SiteTree`.`ID`,', '', $sql);
            $sql = str_replace('SELECT DISTINCT', 'SELECT DISTINCT `SiteTree`.`ID`, ', $sql);

            $sqlArray = explode(PHP_EOL, $sql);
            $sql = implode(' \\' . "\n", $sqlArray);

            $params = new ArrayData([
               'IndexName' => $name,
               'SQL' => 'SQL_QUERY_HERE',
                'DB_HOST' => Environment::getEnv('SS_DATABASE_SERVER'),
                'DB_USER' => Environment::getEnv('SS_DATABASE_USERNAME'),
                'DB_PASSWD' => Environment::getEnv('SS_DATABASE_PASSWORD'),
                'DB_NAME' => Environment::getEnv('SS_DATABASE_NAME'),

            ]);

            $configuraton = $params->renderWith('IndexClassConfig');



            $configuration2 = str_replace('SQL_QUERY_HERE', $sql, $configuraton);

            // @todo generic naming
            $allConfigs[$className] = "{$configuration2}";
        }
        return $allConfigs;
    }

    public function saveConfig()
    {
        $sphinxConfigurations = $this->generateConfig();
        $sphinxSavePath = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'config_dir');
        error_log('Sphinx path: ' . $sphinxSavePath);

        error_log('==== saving config ====');
        foreach(array_keys($sphinxConfigurations) as $filename) {
            $saveTo = $sphinxSavePath . '/' .$filename . '.conf';
            error_log('CONFIG: ' . $sphinxConfigurations[$filename]);
            file_put_contents($saveTo,$sphinxConfigurations[$filename]);
        }
    }
}
