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
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\ArrayData;
use Suilven\FreeTextSearch\Index;
use Suilven\FreeTextSearch\Indexes;

class Indexer
{
    protected $databaseName;

    protected $databaseHost;

    const MYSQL='mysql';
    const POSTGRESQL='postgresql';

    /**
     * @var null|Indexes indexes in current context
     */
    private $indexes = null;

    /**
     * database type, default to mysql
     */
    private $databaseType = self::MYSQL;

    /**
     * Indexer constructor.
     * @param Indexes $indexes indexes in context
     */
    public function __construct($indexes)
    {
        $this->indexes = $indexes;
    }

    /**
     * @param mixed $databaseName
     */
    public function setDatabaseName($databaseName)
    {
        $this->databaseName = $databaseName;
    }

    /**
     * @param mixed $databaseHost
     */
    public function setDatabaseHost($databaseHost)
    {
        $this->databaseHost = $databaseHost;
    }

    /**
     * Generate config
     *
     * @return array of filename => sphinx config
     */
    public function generateConfig()
    {
        $allConfigs = [];

        // MySQLPDODatabase , PostgreSQLDatabase
        $database = Environment::getEnv('SS_DATABASE_CLASS');

        // @todo Check on these values for other connectors
        $isMySQL = $database == 'MySQLPDODatabase';
        $isPostgresSQL = $database == 'PostgreSQLDatabase';

        if (!$isMySQL && !$isPostgresSQL) {
            user_error('The database used must be one of MySQL or Postgres');
        }

        // default is mysql
        if ($isPostgresSQL) {
            $this->databaseType = self::POSTGRESQL;
        }


        $sphinxSiteID = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'site_id');


        /** @var Index $index */
        foreach ($this->indexes as $index) {
            $name = $index->getName();
            $configuraton = $this->generateConfigForIndex($index);

            $allConfigs[$sphinxSiteID . '_' . $name] = "{$configuraton}";
        }
        return $allConfigs;
    }


    /**
     * Create a valid sphinx.conf file and save it.  Note that the commandline or web server user must have write
     * access to the path defined in _config.
     */
    public function saveConfig()
    {
        // This is based on the Docker version of the manticore config, with additional indexes for each site
        // in spearate files under /path/to/config/sites , and then included
        $prefix = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'prefix.conf');
        $common = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'common.conf');
        $indexer = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'indexer.conf');
        $searchd = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'sphinxconfig' . DIRECTORY_SEPARATOR . 'searchd.conf');

        // specific to silverstripe data
        $sphinxConfigurations = $this->generateConfig();


        error_log(print_r($sphinxConfigurations, 1));

        $sphinxSavePath = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'config_file');
        $sphinxSiteID = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'site_id');
        $sphinxSiteIDTMP = $sphinxSiteID . '.tmp';

        $config = $prefix . $common . $indexer . $searchd;

        // error_log($sphinxSavePath);

        // exec('find /etc/');
        echo '---------------';
        #exec('ls -lh /etc/sphinxsearch', $output);
        exec('whoami', $output);
        print_r($output);
        echo '';


        $siteConfig = '';

        print_r(array_keys($sphinxConfigurations));
        foreach (array_keys($sphinxConfigurations) as $filename) {
            error_log('FN:' . $filename);
            $siteConfig .= $sphinxConfigurations[$filename];
        }

        $sitesDir = dirname($sphinxSavePath) . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR;
        $siteConfigPath = $sitesDir .
            $sphinxSiteID . '.conf';
        echo $sitesDir;

        if (!file_exists($sitesDir)) {
            mkdir($sitesDir);
        }


        file_put_contents($siteConfigPath, $siteConfig);

        //file_put_contents($sphinxSiteIDTMP, $config);


        $filelist = glob($sitesDir . "*.conf");
        print_r($filelist);

        $fullconfig = '';
        $fullconfig .= $config;

        foreach ($filelist as $file) {
            echo $file;
            $contents = file_get_contents($file);
            $fullconfig = $fullconfig . "\n\n\n\n\n" . $contents;

            echo '-----';
            error_log($contents);

        }

        error_log($fullconfig);

        // @todo Fix permissions on docker config path for manticore

        file_put_contents($sphinxSavePath, $fullconfig);

        error_log('---- saved config ----');
        error_log($sphinxSavePath);

    }

    /**
     * @param Index $index
     * @param $sphinxSiteID
     * @param $database
     * @return array
     */
    public function generateConfigForIndex(Index $index)
    {
        $sphinxSiteID = Config::inst()->get('Suilven\SphinxSearch\Service\Client', 'site_id');
         $fields = $this->getIndexFields($index);

        // these are stored in the db but not part of free text search
        $attributes = new ArrayList();

        list($clazzName, $tableName, $specs) = $this->getSpecs($index);

        // need to override sort, set it to null
        Config::modify()->set($clazzName, 'default_sort', null);


        /** @var $DataList $queryObject */
        $queryObject = Versioned::get_by_stage($clazzName, Versioned::LIVE);

        // this is how to do it with a DataList, it clones and returns a new DataList
        $queryObject = $queryObject->setQueriedColumns($fields);

        // this needs massages for sphinx
        $sql = $queryObject->sql();

        /**
         * Example SQL produced by the above
         * SELECT DISTINCT "SiteTree_Live"."ClassName", "SiteTree_Live"."LastEdited", "SiteTree_Live"."Created", "SiteTree_Live"."Title", "SiteTree_Live"."MenuTitle", "SiteTree_Live"."Content", "SiteTree_Live"."Sort", "SiteTree_Live"."ParentID", "SiteTree_Live"."ID",
        CASE WHEN "SiteTree_Live"."ClassName" IS NOT NULL THEN "SiteTree_Live"."ClassName"
        ELSE  E'SilverStripe\\CMS\\Model\\SiteTree' END AS "RecordClassName"
        FROM "SiteTree_Live"

         */


        $classNameInHierarchy = $clazzName;
        $joinClasses = [];

        // need to know for the stage, dataobjects assumed flat (@todo This is most probably an incorrect assertion)
        $isSiteTree = false;
        while ($classNameInHierarchy != 'SilverStripe\ORM\DataObject') {
            if ($classNameInHierarchy != 'SilverStripe\CMS\Model\SiteTree') {
                $joinClasses[] = '\'' . str_replace('\\', '\\\\', $classNameInHierarchy) . '\'';
            } else {
                $isSiteTree = true;
            }

            $classNameInHierarchy = get_parent_class($classNameInHierarchy);
        }

        // replacement double quotes with backticks for MySQL
        if ($this->databaseType == self::MYSQL) {
            $sql = str_replace('"', '`', $sql);
            // need to move ID to first param
            if ($isSiteTree) {
                $sql = str_replace("`{$tableName}_Live`.`ID`, ", '', $sql);
                $sql = str_replace('SELECT DISTINCT', "SELECT DISTINCT `{$tableName}_Live`.`ID`, ", $sql);
            } else {
                $sql = str_replace("`{$tableName}`.`ID`, ", '', $sql);
                $sql = str_replace('SELECT DISTINCT', "SELECT DISTINCT `{$tableName}`.`ID`, ", $sql);
            }
        }

        if ($this->databaseType == self::POSTGRESQL) {
            /** @var string $quote Single double quote character to get aroun escaping issues */
            $quote = '"';

            // need to move ID to first param
            if ($isSiteTree) {
                $selectorForId = $quote . 'SiteTree_Live' . $quote . '.' . $quote . 'ID' . $quote;

                $pattern = '/' . $selectorForId . '/';
                // we wish only to replace the first one as this search term can also appear in the join clause
                $sql = preg_replace($pattern, '', $sql, 1);

                $prefix = 'SELECT DISTINCT ' . $quote . $tableName . '_Live' . $quote . '.' . $quote . 'ID' . $quote . ',';
                $sql = preg_replace('/SELECT DISTINCT/', '', $sql);
                $sql = $prefix . $sql;

                // remove potential double commas due to moving of the ID field in the query
                $sql = str_replace(', ,', ',', $sql);

            } else {
                // move ID clause to the front for non SiteTree, PostgreSQL
                $selectorForId = $quote . $tableName . $quote . '.' . $quote . 'ID' . $quote;
                $sql = preg_replace('/' . $selectorForId . '/', '', $sql);


                $replace = 'SELECT DISTINCT ' . $quote . $tableName . $quote . '.' . $quote . 'ID' . $quote . ',';
                $sql = preg_replace('/SELECT DISTINCT/', $replace, $sql);

                $sql = preg_replace('/, ,/', ',', $sql);
            }
        }

        // query is correct up to here for the sitetree case
        error_log('======== CHECK COMMENTS ======');
        error_log('TRACE 3 [id should be at front, postgres and mysql]:' . $sql);


        error_log(print_r($joinClasses, 1));

        $nq = max(1, sizeof($joinClasses) - 1);

        error_log('NQ: ' . $nq);

        // the -1 here is to avoid indexing the base class, be it DataObject or Page
        $commas = str_repeat('?, ', $nq);

        // this removes trailing ', '
        $commas = substr($commas, 0, -2);

        error_log('T1: ' . $commas);
        $columns = implode(', ', $joinClasses);
        error_log('COMMAS: ' . $commas);
        error_log('T2: ' . $commas);
        error_log('COLUMNS = ' . $columns);


        if ($this->databaseType == self::MYSQL) {
            $sql = str_replace(
                'WHERE (`SiteTree_Live`.`ClassName` IN (' . $commas . '))',
                "WHERE (`SiteTree_Live`.`ClassName` IN ({$columns}))",
                $sql);
        } elseif ($this->databaseType == self::POSTGRESQL) {
            error_log("+++++++++++++++++++++++++++\n\n\n\n" . 'SQL BEFORE ADDING CLASSES:' . $sql);
            $commas = str_replace('?', '\?', $commas);
            $selectorForId = '/WHERE ("SiteTree_Live"."ClassName" IN (' . $commas . '))/';
            //$search = '/WHERE ("SiteTree_Live"\."ClassName" IN (\?, \?))/';
            $replacement = 'WHERE ("SiteTree_Live"."ClassName" IN ( ' . $columns . '))';

            // testing
            $selectorForId = '/WHERE \("SiteTree_Live"."ClassName" IN \(\?\)\)/';
            \
                error_log('>> SELECTOR: ' . $selectorForId);
            error_log('>> REPLACEMENT: ' . $replacement);
            error_log('>> SQL: ' . $sql);
            $sql = preg_replace($selectorForId, $replacement, $sql);
        }

        error_log('TRACE 4 [addition of classnames]:' . $sql);


        $sqlArray = explode(PHP_EOL, $sql);
        $sql = implode(' \\' . "\n", $sqlArray);

        // loop through fields adding attribute or altering sql as needbe
        $allFields = $fields;
        $allFields[] = 'LastEdited';
        $allFields[] = 'Created';

        // make modifications to query and or attributes but only if required.  Text and HTML fields can be left as is
        foreach ($allFields as $field) {
            if (isset($specs[$field])) {
                $fieldType = $specs[$field];
                error_log('FIELDTYPE: ' . $field . ' ==> ' . $fieldType);

                // this ought to be a separate method, but the $sql tweaking makes this trickier
                switch ($fieldType) {
                    case 'DBDatetime':
                        $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS `$field`", $sql);
                        $attributes->push(['Name' => $field, 'Type' => 'sql_attr_timestamp']);
                        break;
                    case 'Datetime':
                        $sql = str_replace("`$tableName`.`$field`", "UNIX_TIMESTAMP(`$tableName`.`$field`) AS `$field`", $sql);
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


                $facetHeadings = $index->getTokens();
                // strings and ints may need tokenized, others as above.  See http://sphinxsearch.com/wiki/doku.php?id=fields_and_attributes
                if (in_array($field, $facetHeadings)) {
                    $fieldType = $specs[$field];

                    // remove string length from varchar
                    if (substr($fieldType, 0, 7) === "Varchar") {
                        $fieldTYpe = 'Varchar';
                    }

                    // NOTE, cannot filter on string attributes, see http://sphinxsearch.com/wiki/doku.php?id=fields_and_attributes
                    // OH, it seems to work :)
                    switch ($fieldType) {
                        case 'Int':
                            $attributes->push(['Name' => $field, 'Type' => 'sql_attr_uint']);
                            break;
                        case 'Varchar':
                            $attributes->push(['Name' => $field, 'Type' => 'sql_field_string']);
                            break;
                        case 'HTMLText':
                            $attributes->push(['Name' => $field, 'Type' => 'sql_field_string']);
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
                user_error("The field {$field} does not exist for class {$clazzName}");
            }
        }

        $this->processHasManyFields($index, $tableName, $attributes);


        $params = new ArrayData([
            'IndexName' => $sphinxSiteID . '_' . $index->getName(),
            'SQL' => 'SQL_QUERY_HERE',
            'DB_HOST' => !empty($this->databaseHost) ? $this->databaseHost : Environment::getEnv('SS_DATABASE_SERVER'),
            'DB_USER' => Environment::getEnv('SS_DATABASE_USERNAME'),
            'DB_PASSWD' => Environment::getEnv('SS_DATABASE_PASSWORD'),
            'DB_NAME' => !empty($this->databaseName) ? $this->databaseName : Environment::getEnv('SS_DATABASE_NAME'),
            'Attributes' => $attributes,
        ]);


        $configuration = null;

        if ($this->databaseType == self::MYSQL) {
            // @todo PG needed .RAW for the name on multi attribute
            $configuraton = $params->renderWith('MySQLIndexClassConfig');
        } elseif ($this->databaseType == self::POSTGRESQL) {
            $configuraton = $params->renderWith('PostgreSQLIndexClassConfig');
        }

        // this avoids issues with escaping and quotation marks
        $configuration = str_replace('SQL_QUERY_HERE', $sql, $configuraton);

        return $configuration;
    }


    private function processHasManyFields($index, $relationshipName, &$attributes)
    {

        $hasManyFields = $index->getHasManyFields();
       // $tableName = $singleton->config()->get('table_name');

        $clazz = $index->getClass();

        error_log("\n\n---- Index for " . $clazz . '----');

        /** @var DataList $query */
        $singleton = singleton($clazz);

        /** @var DataObjectSchema $schema */
        $schema = $singleton->getSchema();



        if (!empty($hasManyFields)) {
            error_log(print_r($hasManyFields, 1));
            foreach($hasManyFields as $field) {
                error_log('FIELD: ' . $field);
                error_log('---- specs ----');

                // @todo Test genuine has many as opposed to many many
                $specs = $schema->manyManyComponent($clazz, 'FlickrTags');

                //sql_attr_multi = bigint FlickrTagID from query; SELECT "FlickrPhotoID", "FlickrTagID" FROM "FlickrPhoto_FlickrTags";

                $joinTable = $specs['join'];
                $quote = '"';

                // @todo Ranged queries, once I understand them
                $multiQuery = "bigint {$specs['childField']} from query; SELECT ";
                $multiQuery .= $quote;
                $multiQuery .= $specs['parentField'];
                $multiQuery .= '","';
                $multiQuery .= $specs['childField'];
                $multiQuery .= '" FROM "';
                $multiQuery .= $joinTable;
                $multiQuery .= '"';


                error_log($multiQuery) ;
                $attributes->push(['Name' => $multiQuery, 'Type' => 'sql_attr_multi']);
            }
            ;

            print_r($attributes);
        }

    }

    /**
     * @param Index $index
     * @return array
     */
    public function getIndexFields(Index $index)
    {
// ---- collate fields ----
        $fields = [];

        // ---- model fields ----
        foreach ($index->getFields() as $field) {
            $fields[] = $field;
        }

        // ---- has one, a special case of a model field ----
        // has one fields are essentially an attribute, e.g. has_one:Photographer would map to a field called PhotographerID
        foreach ($index->getHasOneFields() as $field) {
            $fields[] .= $field . 'ID';
        }

        // ---- facet headings ----
        // These are the facet headings from the config, camel case, e.g. Aperture, ShutterSpeed
        $facetHeadings = $index->getTokens();

        // add facet headings to fields
        foreach ($facetHeadings as $token) {
            $fields[] = $token;
        }
        return $fields;
    }

    /**
     * @param Index $index
     * @return array
     */
    public function getSpecs(Index $index): array
    {
        $clazzName = $index->getClass();

        error_log("\n\n---- Index for " . $clazzName . '----');

        /** @var DataList $query */
        $singleton = singleton($clazzName);
        $tableName = $singleton->config()->get('table_name');
        $schema = $singleton->getSchema();
        $specs = $schema->fieldSpecs($clazzName, DataObjectSchema::DB_ONLY);
        return array($clazzName, $tableName, $specs);
    }
}
