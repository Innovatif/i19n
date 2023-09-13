<?php

namespace Innovatif\i19n\Task;

use Innovatif\i19n\i19n;
use SilverStripe\Dev\BuildTask;

class i19nClearUntranslatedTask extends BuildTask
{
    protected $title = "i19n: clear untranslated";
    protected $description = "Remove all untranslated translations (where Value is the same as Entity)";

    private static $segment = 'i19nClearUntranslatedTask';

    public function run($request)
    {
        $list_all = i19n::get()->where('Entity = Value');
        $list_all->removeAll();
    }
}
