<?php

/**
 * Write i18n strings into database
 * @author klemend
 */

namespace Innovatif\i19n\Writer;

use Innovatif\i19n\Model\i19n;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\i18n\Messages\Writer;

class i19NWriter implements Writer
{
    use Injectable;

    public function write($messages, $locale, $path)
    {
        // reverse engineer it
        $module_path = substr($path, strlen(Director::baseFolder() . '/'));

        // always use linux style paths
        $module_path = str_replace(DIRECTORY_SEPARATOR, '/', $module_path);

        foreach ($messages as $entity => $value) {
            $entry = i19n::get()->filter([
                'Locale' => $locale,
                'Entity' => $entity,
            ])->first();

            // don't update existing strings
            if ($entry) {
                continue;
            }

            $entry = i19n::create();
            $entry->Locale = $locale;
            $entry->Entity = $entity;
            $entry->ModulePath = $module_path;
            if (!is_array($value)) {
                $entry->Value = $value;
            }
            $entry->write();
        }
    }
}
