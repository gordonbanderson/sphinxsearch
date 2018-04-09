<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 24/3/2561
 * Time: 21:14 น.
 */

namespace Suilven\SphinxSearch\Service;


use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Versioned\Versioned;
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
     * @return array of filename => sphinx config
     */
    public function generateConfig()
    {
        $allConfigs = [];


        /** @var Index $index */
        foreach($this->indexes as $index)
        {
            $className = $index->getClass();
            error_log("\n\n\n\n\n");

            error_log('>>>> CLASSNAME: ' . $className);

            error_log('INDEX: ' . print_r($index, 1));

            $name = $index->getName();
            $fields = []; // ['ID', 'CreatedAt', 'LastEdited'];


            // these are stored in the db but not part of free text search, a bit like tokens I guess
            $attributes = new ArrayList();

            // @todo different field types
            foreach($index->getFields() as $field)
            {
                $fields[] = $field;
            }

            foreach($index->getTokens() as $token)
            {
                $fields[] = $token;
            }

            $tokens = $index->getTokens();

            error_log('FIELDS AND TOKENS: ' . print_r($fields, 1));

            /** @var DataList $query */
            $singleton = singleton($className);
            $tableName = $singleton->config()->get('table_name');
            $schema = $singleton->getSchema();

            $specs = $schema->fieldSpecs($className, DataObjectSchema::DB_ONLY);


            // need to override sort, set it to null
            Config::modify()->set($className, 'default_sort', null);

            // @todo fix reference here
            /** @var DataObject $queryObject */

            /** @var $DataList $queryObject */
            $queryObject = Versioned::get_by_stage($className, Versioned::LIVE);


            error_log('CLASS:' . get_class($queryObject));


            error_log('QUERIED COLS FOR ' . $className . ': ' . print_r($fields, 1));

            // this is how to do it with a DataList, it clones and returns a new DataList
            $queryObject = $queryObject->setQueriedColumns($fields);


            // this needs massages for sphinx
            $sql = $queryObject->sql();

            error_log('SQL INIT: ' . $sql);


            $classNameInHierarchy = $className;


            $joinClasses = [];

            // need to know for the stage, dataobjects assumed flat
            $isSiteTree = false;
            while($classNameInHierarchy != 'SilverStripe\ORM\DataObject')
            {
                if ($classNameInHierarchy != 'SilverStripe\CMS\Model\SiteTree') {
                    $joinClasses[] = '\'' .  str_replace('\\', '\\\\', $classNameInHierarchy) . '\'';
                } else {
                    $isSiteTree = true;
                }

                error_log('CN:' . $classNameInHierarchy);
                $instance = new $classNameInHierarchy;
                $classNameInHierarchy = get_parent_class($classNameInHierarchy);
            }

            error_log('JOIN CLASSES: ' . print_r($joinClasses, 1));


            // error_log('SQL T1 ' . $sql);

            $sql = str_replace('"', '`', $sql);

            // need to move ID to first param
            if ($isSiteTree) {
                $sql = str_replace("`{$tableName}_Live`.`ID`, ", '', $sql);
                $sql = str_replace('SELECT DISTINCT', "SELECT DISTINCT `{$tableName}_Live`.`ID`, ", $sql);
            } else {
                $sql = str_replace("`{$tableName}`.`ID`, ", '', $sql);
                $sql = str_replace('SELECT DISTINCT', "SELECT DISTINCT `{$tableName}`.`ID`, ", $sql);
            }


            $commas = str_repeat('?, ', sizeof($joinClasses));
            $commas = substr( $commas, 0, -2 );
            $columns = implode(', ', $joinClasses);
            error_log('COMMAS: ' . $commas);
            $sql = str_replace('WHERE (`SiteTree_Live`.`ClassName` IN (' . $commas. '))',
                "WHERE (`SiteTree_Live`.`ClassName` IN ({$columns}))",
                $sql);
           // $sql = str_replace('WHERE (`SiteTree`.`ClassName` IN (?, ?))', "WHERE (`SiteTree`.`ClassName` IN ('{$joinClasses[0]}', '{$joinClasses[1]}'))", $sql);


            error_log('--------------------');
            error_log($sql);
            error_log($commas);
            error_log($columns);
            error_log('/--------------------');



            $sqlArray = explode(PHP_EOL, $sql);
            $sql = implode(' \\' . "\n", $sqlArray);

            // loop through fields adding attribute or altering sql as needbe
            $allFields = $fields;
            $allFields[] = 'LastEdited';
            $allFields[] = 'Created';

            // make modifications to query and or attributes but only if required
            foreach($allFields as $field)
            {
                if (isset($specs[$field])) {
                    $fieldType = $specs[$field];
                    error_log('FT:' . $field . ' --> ' . $fieldType);
                    switch($fieldType) {
                        case 'DBDatetime':
                            $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS `$field`" , $sql);
                            // $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS {$field}" , $sql);
                            $attributes->push(['Name' => $field, 'Type' => 'sql_attr_timestamp']);
                            break;
                        case 'Datetime':
                            $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS `$field`" , $sql);
                            // this breaks order by if field is after: $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS {$field}" , $sql);
                            $attributes->push(['Name' => $field, 'Type' => 'sql_attr_timestamp']);
                            break;
                        case 'Boolean':
                            $attributes->push(['Name' => $field, 'Type' => 'sql_attr_bool']); // @todo informed guess
                            break;
                        case 'ForeignKey':
                            $attributes->push(['Name' => $field, 'Type' => 'sql_attr_uint']);
                            break;
                        default:
                            // do nothing
                            break;
                    }

                    // strings and ints may need tokenized, others as above.  See http://sphinxsearch.com/wiki/doku.php?id=fields_and_attributes
                    if (in_array($field, $tokens)) {
                        $fieldType = $specs[$field];

                        // remove string length from varchar
                        if (substr( $fieldType, 0, 7 ) === "Varchar") {
                            $fieldTYpe = 'Varchar';
                        }

                        // @todo - float
                        // NOTE, cannot filter on string attributes, see http://sphinxsearch.com/wiki/doku.php?id=fields_and_attributes
                        // OH, it seems to work :)
                        switch($fieldType) {
                            case 'Int':
                                $attributes->push(['Name' => $field, 'Type' => 'sql_attr_uint']);
                                break;
                            case 'Varchar':
                                $attributes->push(['Name' => $field, 'Type' => 'sql_attr_string']);
                                break;
                            case 'HTMLText':
                                $attributes->push(['Name' => $field, 'Type' => 'sql_attr_uint']);
                                break;
                            case 'Float':
                                $attributes->push(['Name' => $field, 'Type' => 'sql_attr_float']);
                                break;
                            default:
                                // do nothing
                                break;
                        }
                    }
                } else {
                    user_error("The field {$field} does not exist for class {$className}");
                }

                //
            }

            /**
             * to add
             * 	sql_attr_string		= classname
             */


            $params = new ArrayData([
               'IndexName' => $name,
               'SQL' => 'SQL_QUERY_HERE',
                'DB_HOST' => Environment::getEnv('SS_DATABASE_SERVER'),
                'DB_USER' => Environment::getEnv('SS_DATABASE_USERNAME'),
                'DB_PASSWD' => Environment::getEnv('SS_DATABASE_PASSWORD'),
                'DB_NAME' => Environment::getEnv('SS_DATABASE_NAME'),
                'Attributes' => $attributes,
            ]);

            $configuraton = $params->renderWith('IndexClassConfig');



            $configuration2 = str_replace('SQL_QUERY_HERE', $sql, $configuraton);

            // @todo generic naming
            $allConfigs[$name] = "{$configuration2}";
        }
        return $allConfigs;
    }

    /**
     * Create a valid sphinx.conf file and save it.  Note that the commandline or web server user must have write
     * access to the path defined in _config.
     */
    public function saveConfig()
    {
        // specific to the runnnig of sphinx
        $common = file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'common.conf');
        $indexer = file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'indexer.conf');
        $searchd = file_get_contents( __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'searchd.conf');


        // specific to silverstripe data
        $sphinxConfigurations = $this->generateConfig();
        $sphinxSavePath = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'config_file');

        $config = $common . $indexer . $searchd;

        foreach(array_keys($sphinxConfigurations) as $filename) {
            $config .= $sphinxConfigurations[$filename];
        }

        file_put_contents($sphinxSavePath, $config);

        error_log('CONFIG SAVED: ' . $config);
    }
}
