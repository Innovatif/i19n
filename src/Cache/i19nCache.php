<?php

namespace Innovatif\i19n\Cache;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Injector\Injector;

class i19nCache
{
    /**
     * Load cache for i19n
     * @return mixed
     */
    public static function get_cache()
    {
        return Injector::inst()->get(CacheInterface::class . '.i19nCache');
    }

    /**
     * Get value by its key
     * @param string $cacheKey
     * @return mixed
     */
    public static function get_value($cacheKey)
    {
        $cache = self::get_cache();
        return $cache->get($cacheKey);
    }

    /**
     * Set value by its key
     * @param string $cacheKey
     * @param string $value
     */
    public static function set_value($cacheKey, $value)
    {
        $cache = self::get_cache();
        $cache->set($cacheKey, $value);
    }

    /**
     * Check if $cacheKey exists in cache
     * @param string $cacheKey
     * @return boolean
     */
    public static function has_value($cacheKey)
    {
        $cache = self::get_cache();
        return $cache->has($cacheKey);
    }

    /**
     * Delete value by its key
     * @param string $cacheKey
     */
    public static function delete_value($cacheKey)
    {
        $cache = self::get_cache();
        $cache->delete($cacheKey);
    }

    /**
     * Generates a cachekey with the given parameters
     *
     * @param $entity
     * @param $locale
     * @return string
     */
    public static function get_cache_key($entity, $locale)
    {
        return md5(serialize($entity)) . '#' . md5(serialize($locale));
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public static function clear()
    {
        $cache = self::get_cache();
        return $cache->clear();
    }
}
