<?php

namespace Innovatif\i19n\Library;

use Innovatif\i19n\Cache\i19nCache;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\Symfony\FlushInvalidatedResource;

/**
 * Support library
 * @author klemend
 *
 */
class i19nLibrary
{
    use Configurable;

    /**
     * Is clear cache task enabled or not
     * @var boolean
     */
    private static $enable_clear_cache_task = false;

    /**
     * Load available locales
     * @return array|string[]
     */
    public static function ListLocales(): array
    {
        $all_locales = [];

        $fluent_ClassName = 'TractorCow\Fluent\Model\Locale';

        // check if Fluent exists
        if (class_exists($fluent_ClassName)) {
            $table_name = Config::inst()->get($fluent_ClassName, 'table_name');
            $list_locales = $fluent_ClassName::get()->sort('"' . $table_name . '"."IsGlobalDefault" DESC, "' . $table_name . '"."Locale" ASC');
            $all_locales = $list_locales->map('Locale', 'Title')->toArray();
        }

        // fallback if no locales are added or fluent doesn't exist - use current locale
        if (!$all_locales || !count($all_locales)) {
            $curr_locale = i18n::get_locale();
            $all_locales = [
                $curr_locale => i18n::getData()->langFromLocale($curr_locale)
            ];
        }

        return $all_locales;
    }

    /**
     * Load supported modules/extensions
     * @return \SilverStripe\Core\Manifest\Module[]
     */
    public static function SupportedModules(): array
    {
        // load modules
        $list_all_modules = ModuleLoader::inst()->getManifest()->getModules();
        $modules = [];
        foreach ($list_all_modules as $module_name => $module_manifest) {
            $modules[$module_name] = $module_name;
        }

        return $modules;
    }

    /**
     * Clear i19n cache
     */
    public static function ClearCache(): void
    {
        i19nCache::clear();
        FlushInvalidatedResource::flush();
    }
}
