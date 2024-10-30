<?php

namespace Innovatif\i19n\Task;

use Innovatif\i19n\Cache\i19nCache;
use Innovatif\i19n\Cache\i19nClearCache;
use Innovatif\i19n\Library\i19nLibrary;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\BuildTask;
use SilverStripe\i18n\Messages\Symfony\FlushInvalidatedResource;

class i19nClearCacheTask extends BuildTask
{
    protected $title = "i19n: clear cache";
    protected $description = "Clear i18n cache. Usefull in multiserver environment.";

    private static $segment = 'i19nClearCacheTask';

    public function run($request)
    {
        $lastClearedCacheItem = i19nClearCache::get()->sort('LastEdited', 'DESC')->first();
        if (!$lastClearedCacheItem) {
            return;
        }

        $lastClearedCacheFile = TEMP_FOLDER . '/i19nLastCleared.txt';

        $lastClearedCacheFileValue = null;
        if (file_exists($lastClearedCacheFile)) {
            $lastClearedCacheFileValue = file_get_contents($lastClearedCacheFile);
        }

        if (!$lastClearedCacheFileValue || ($lastClearedCacheFileValue < $lastClearedCacheItem->dbObject('LastEdited')->getTimestamp())) {
            i19nCache::clear();
            FlushInvalidatedResource::flush();

            file_put_contents($lastClearedCacheFile, time());
        }
    }

    public function isEnabled()
    {
        return true;
        return Config::inst()->get(i19nLibrary::class, 'enable_clear_cache_task');
    }
}
