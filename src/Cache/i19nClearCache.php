<?php

namespace Innovatif\i19n\Cache;

use SilverStripe\ORM\DataObject;

/**
 * Simple object for storing last ClearCache action request timestamp.
 * Used in i19nClearCacheTask
 * @author aljosab
 */
class i19nClearCache extends DataObject
{

    private static $table_name = 'i19nClearCache';
}
