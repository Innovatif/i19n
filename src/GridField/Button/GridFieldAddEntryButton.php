<?php

namespace Innovatif\i19n\GridField\Button;

use Innovatif\i19n\Library\i19nLibrary;
use Innovatif\i19n\Model\i19n;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\View\ArrayData;

class GridFieldAddEntryButton implements GridField_ActionProvider, GridField_HTMLProvider
{
    use Injectable;

    private $targetFragment;

    private static $enable_multilocale_add = true;

    public function __construct($targetFragment = "after")
    {
        $this->targetFragment = $targetFragment;
    }

    public function getHTMLFragments($gridField)
    {
        $forTemplate = ArrayData::create([
            'OpenPopupButton' => $this->OpenPopupButton($gridField),
            'ActionButtons' => $this->ActionButtons($gridField),
            'FormFields' => $this->FilterFormFields($gridField),
            'Title' => _t(self::class . '.TITLE', self::class . '.TITLE'),
            'ExtraClass' => 'addnew-up',
        ]);

        return [
            $this->targetFragment => $forTemplate->renderWith('i19nOverlayForm'),
        ];
    }

    protected function OpenPopupButton($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'open_new_popup',
            _t(self::class . '.BUTTON_DO_ADD', self::class . '.BUTTON_DO_ADD'),
            'translate',
            null
        );

        $button->addExtraClass('btn btn-primary no-ajax font-icon-plus open-new-popup');

        return $button->Field();
    }

    /**
     * Buttons for template wrapped in ArrayList
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    protected function ActionButtons($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'addnew',
            _t(self::class . '.BUTTON_DO_SAVE', self::class . '.BUTTON_DO_SAVE'),
            'addnew',
            null
        );

        $button->addExtraClass('btn btn-primary font-icon-plus');
        $button->setForm($gridField->getForm());


        $close_button = new GridField_FormAction(
            $gridField,
            'closetranslate',
            _t(self::class . '.BUTTON_DO_CLOSE', self::class . '.BUTTON_DO_CLOSE'),
            'closetranslate',
            null
        );

        $close_button->addExtraClass('btn btn-outline-danger btn-hide-outline no-ajax font-icon-cancel-circled close-new-popup');
        $close_button->setForm($gridField->getForm());

        return ArrayList::create([
            $button,
            $close_button,
        ]);
    }


    protected function FilterFormFields($gridField)
    {
        $all_locales = i19nLibrary::ListLocales();
        $modules = array_combine(array_keys(i19nLibrary::getModulesAndThemes()), array_keys(i19nLibrary::getModulesAndThemes()));

        $fields = [
            TextField::create('Entity')->setTitle(singleton(i19n::class)->fieldLabel('Entity')),
        ];

        if ($this->getMultiLocaleAddEnabled()) {
            foreach ($all_locales as $locale => $nice) {
                array_push($fields, TextField::create("Values[$locale]")->setTitle(singleton(i19n::class)->fieldLabel('Value') . " ($nice)"));
            }
        } else {
            array_push($fields, TextField::create('Value')->setTitle(singleton(i19n::class)->fieldLabel('Value')));
            array_push($fields, DropdownField::create('Locale')->setTitle(singleton(i19n::class)->fieldLabel('Locale'))->setSource($all_locales));
        }

        array_push($fields, DropdownField::create('Module')->setTitle(singleton(i19n::class)->fieldLabel('ModulePath'))->setSource($modules));

        $list = FieldList::create($fields);

        return $list;
    }


    public function getActions($gridField)
    {
        return ['addnew'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'addnew') {
            if (!$this->getMultiLocaleAddEnabled()) {
                $list_values = [trim((string) $data['Locale']) => trim((string) $data['Value'])];
            } else {
                $list_values = $data['Values'];
            }

            $entity = trim((string) $data['Entity']);

            $count_updates = 0;
            $count_inserts = 0;

            foreach ($list_values as $locale => $value) {
                $locale = trim((string) $locale);
                $value = trim((string) $value);

                if (!$value || !$locale) {
                    continue;
                }

                $i19n = i19n::get()->filter([
                    'Entity' => $entity,
                    'Locale' => $locale,
                ])->first();
                $is_update = true;

                if (!$i19n) {
                    $i19n = i19n::create();
                    $is_update = false;
                }

                $module_path = trim((string) $data['Module']);

                // always use path for themes
                $module_path = str_replace('themes:', 'themes/', $module_path);

                $i19n->Entity = $entity;
                $i19n->Locale = $locale;
                $i19n->Value = $value;
                $i19n->ModulePath = $module_path;
                $i19n->write();

                if ($is_update) {
                    $count_updates++;
                } else {
                    $count_inserts++;
                }
            }


            $gridField->setMessage(_t(self::class . '.SAVED_ACTION', self::class . '.SAVED_ACTION', ['inserted' => $count_inserts, 'updated' => $count_updates]), ValidationResult::TYPE_GOOD);
        }
        return null;
    }

    public function getMultiLocaleAddEnabled()
    {
        return Config::inst()->get(self::class, 'enable_multilocale_add');
    }
}
