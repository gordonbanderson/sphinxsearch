<?php
/**
 * Created by PhpStorm.
 * User: gordon
 * Date: 11/4/2561
 * Time: 16:22 น.
 */

namespace Suilven\SphinxSearch\Task;


use SilverStripe\Dev\BuildTask;
use Suilven\FreeTextSearch\Indexes;
use Suilven\SphinxSearch\Service\Indexer;

class ReconfigureIndexTask extends BuildTask
{

    protected $title = 'Regenerate Sphinx Configuration';

    protected $description = 'Regenerate sphinx configuration from models';

    private static $segment = 'sphinxconfig';

    protected $enabled = true;


    public function run($request)
    {
        $indexesService = new Indexes();
        $indexesObj = $indexesService->getIndexes();
        $indexer = new Indexer($indexesObj);
        $indexer->saveConfig();
    }
}
