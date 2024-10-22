<?php

namespace Innovatif\i19n\Task;

use Innovatif\i19n\Writer\i19nWriter;
use Innovatif\i19n\TextCollection\i19nTextCollection;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\TextCollection\i18nTextCollector;

class i19nTask extends BuildTask
{
    protected $title = "i19n: Textcollector Task";

    protected $description = "
		Traverses through files in order to collect the 'entity master tables'
		stored in each module.
        
		Parameters:
		- locale: One or more locales to limit collection (comma-separated)
		- module: One or more modules to limit collection (comma-separated)
		- merge: Merge new strings with existing ones already defined in language files (default: FALSE)
	";

    private static $segment = 'i19nTextCollectorTask';

    public function run($request)
    {
        $merge = $this->getIsMerge($request);

        // Get restrictions
        $restrictModules = ($request->getVar('module'))
            ? explode(',', (string) $request->getVar('module'))
            : null;


        $locales = ($request->getVar('locale'))
            ? explode(',', (string) $request->getVar('locale'))
            : i18n::get_locale();

        static::run_translate($locales, $restrictModules, $merge);

        Debug::message(self::class . " completed!", false);
    }

    /**
     * Run translate
     * @param string $list_locales
     * @param boolean $merge
     * @param null|[] $restrictModules
     */
    public static function run_translate($list_locales = null, $restrictModules = null, $merge = true)
    {
        if (!is_array($list_locales)) {
            $list_locales = [$list_locales];
        }

        Environment::increaseTimeLimitTo();

        foreach ($list_locales as $locale) {
            $collector = i18nTextCollector::create($locale);

            // fix for SS < 5 and Fluent >= 5.1 since SS can't handle __TRAIT__
            if (!method_exists($collector, 'collectFromORM')) {
                $collector = i19nTextCollection::create($locale);
            }
            // Custom writer
            $collector->setWriter(i19nWritter::create());

            $collector->run($restrictModules, $merge);
        }
    }

    /**
     * Check if we should merge
     *
     * @param $request
     * @return bool
     */
    protected function getIsMerge($request)
    {
        $merge = $request->getVar('merge');

        // Default to true if not given
        if (!isset($merge)) {
            return true;
        }

        // merge=0 or merge=false will disable merge
        return !in_array($merge, ['0', 'false']);
    }
}
