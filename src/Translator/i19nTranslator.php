<?php

namespace Innovatif\i19n\Translator;

use Innovatif\i19n\Cache\i19nCache;
use Innovatif\i19n\Model\i19n;
use SilverStripe\i18n\Data\Intl\IntlLocales;
use SilverStripe\Security\Permission;

class i19nTranslator
{
    private static $intl_locale;

    /**
     * @param $entity
     * @return mixed|null
     */
    private function geti19nValue($localeOrLang, $entity)
    {
        $i18n = isset($_GET['i18n']);

        $cacheKey = i19nCache::get_cache_key($entity, $localeOrLang);

        //see if entity is in cache
        if (i19nCache::has_value($cacheKey)) {
            $val =  i19nCache::get_value($cacheKey);

            if ($i18n && Permission::check('ADMIN')) {
                return $val . '[' . $entity . ']';
            }

            return $val;
        }

        $i19n = i19n::get()->filter([
            'Locale' => $localeOrLang,
            'Entity' => $entity
        ])->first();

        if (!$i19n) {
            return null;
        }

        i19nCache::set_value($cacheKey, $i19n->Value);

        if ($i18n && Permission::check('ADMIN')) {
            return $i19n->Value . '[' . $entity . ']';
        }

        return $i19n->Value;
    }

    /**
     * Traslate entity to specified $locale.
     * @param string $entity
     * @param array $parameters
     * @param string $locale
     * @return boolean|string Return false if no i19n object is found or it's string if it's found.
     */
    public function trans($entity, array $parameters = [], $locale = null)
    {
        if (self::$intl_locale === null) {
            self::$intl_locale = IntlLocales::create();
        }

        //get lang
        $lang = self::$intl_locale->langFromLocale($locale);
        //see if we got a value for i19n with lang value as "locale"
        $value = $this->geti19nValue($lang, $entity);

        //see if we got a value thats defined with full locale
        if (!$value) {
            $value = $this->geti19nValue($locale, $entity);
        }

        if (!$value) {
            return false;
        }

        return strtr($value, $parameters);
    }
}
