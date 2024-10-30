<?php

namespace Innovatif\i19n\Library;

use Innovatif\i19n\Cache\i19nCache;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Manifest\Module;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Core\Path;
use SilverStripe\i18n\i18n;
use SilverStripe\i18n\Messages\Symfony\FlushInvalidatedResource;
use SilverStripe\i18n\TextCollection\i18nTextCollector;

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
     * List all modules and themes
     *
     * @var array
     */
    private static $modulesAndThemes;

    /**
     * Load available locales
     * @return array|string[]
     */
    public static function ListLocales(): array
    {
        $all_locales = [];

        $fluent_ClassName = \TractorCow\Fluent\Model\Locale::class;

        // check if Fluent exists
        if (class_exists($fluent_ClassName)) {
            $all_locales = $fluent_ClassName::get()->sort([
                "IsGlobalDefault" => "DESC", "Locale" => "ASC"
            ])->map('Locale', 'Title')->toArray();
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

    public static function getModulesAndThemes(): array
    {
        if (!self::$modulesAndThemes) {
            $modules = ModuleLoader::inst()->getManifest()->getModules();
            // load themes as modules
            $themes = [];
            if (is_dir(THEMES_PATH)) {
                $themes = array_diff(scandir(THEMES_PATH), ['..', '.']);
            }
            if (!empty($themes)) {
                foreach ($themes as $theme) {
                    if (is_dir(Path::join(THEMES_PATH, $theme))) {
                        $modules['themes:' . $theme] = new Module(Path::join(THEMES_PATH, $theme), BASE_PATH);
                    }
                }
            }
            self::$modulesAndThemes = $modules;
        }
        return self::$modulesAndThemes;
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
