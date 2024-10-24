<?php

namespace Innovatif\i19n\GridField\Button;

use Innovatif\i19n\Cache\i19nClearCache;
use Innovatif\i19n\Library\i19nLibrary;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;

/**
 * Clear i18n cache button
 * @author klemend
 *
 */
class GridFieldClearCacheButton implements GridField_ActionProvider, GridField_HTMLProvider
{
    use Injectable;

    private $targetFragment;

    public function __construct($targetFragment = "after")
    {
        $this->targetFragment = $targetFragment;
    }
    public function getHTMLFragments($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'clearcache',
            _t(self::class . '.BUTTON_TEXT', self::class . '.BUTTON_TEXT'),
            'clearcache',
            null
        );

        $button->addExtraClass('btn btn-primary font-icon-sync');
        $button->setForm($gridField->getForm());

        return [
            $this->targetFragment => $button->Field()
        ];
    }

    public function getActions($gridField)
    {
        return ['clearcache'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'clearcache') {
            // TODO: display message
            if (Config::inst()->get(i19nLibrary::class, 'enable_clear_cache_task')) {
                $clearCache = i19nClearCache::create();
                $clearCache->write();

                i19nClearCache::get()->exclude(['ID' => $clearCache->ID])->removeAll();
            }

            i19nLibrary::ClearCache();
        }
    }
}
