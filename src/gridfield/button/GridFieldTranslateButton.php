<?php

namespace Innovatif\i19n\GridField\Button;

use Innovatif\i19n\Library\i19nLibrary;
use Innovatif\i19n\Task\i19nTask;
use Innovatif\i19n\i19nWritter;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Manifest\ClassLoader;
use SilverStripe\Core\Manifest\ModuleLoader;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\ListboxField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;
use TractorCow\Fluent\Model\Locale;
use SilverStripe\Forms\OptionsetField;

class GridFieldTranslateButton implements GridField_ActionProvider, GridField_HTMLProvider
{
    use Injectable;
    use Configurable;

    private $targetFragment;

    /**
     * Define which modules are preselected by default when you open translate popup.
     * @var array
     */
    private static $preselected_modules = [];

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
            'Title' => _t(__CLASS__ . '.BUTTON_TITLE', __CLASS__ . '.BUTTON_TITLE'),
            'ExtraClass' => 'translate-up',
        ]);

        return [
            $this->targetFragment => $forTemplate->renderWith('i19nOverlayForm'),
        ];
    }

    protected function FilterFormFields($gridField)
    {
        $all_locales = i19nLibrary::ListLocales();
        $modules = i19nLibrary::SupportedModules();

        /**
         * Offer preselected modules
         * @var array $default_translate_modules
         */
        $default_translate_modules = [];
        $suggested_modules = $this->config()->get('preselected_modules');
        foreach ($suggested_modules as $suggested_module) {
            if (isset($modules[$suggested_module])) {
                array_push($default_translate_modules, $suggested_module);
            }
        }

        $default_locales = [];
        if ($preselected_locales = $this->config()->get('preselected_locales')) {
            $default_locales = $preselected_locales;
        } else if (class_exists('TractorCow\Fluent\Model\Locale')) {
            $default_locales[] = Locale::getDefault()->Locale;
        }

        $list = FieldList::create([
            OptionsetField::create('TranslateSelection')
                ->setSource(['front' => _t(__CLASS__ . '.TRANSLATE_FRONT', __CLASS__ . '.TRANSLATE_FRONT'), 'cms' => _t(__CLASS__ . '.TRANSLATE_CMS', __CLASS__ . '.TRANSLATE_CMS')])
                ->setValue('front')
                ->setTitle(_t(__CLASS__ . '.TRANSLATION_SELECTION', __CLASS__ . '.TRANSLATION_SELECTION')),
            ListboxField::create('TranslateButtonLocale')
                ->setSource($all_locales)
                ->setDefaultItems($default_locales)
                ->setTitle(_t(__CLASS__ . '.SELECT_LOCALES', __CLASS__ . '.SELECT_LOCALES')),
            ListboxField::create('TranslateButtonModule')
                ->setSource($modules)
                ->setDefaultItems($default_translate_modules)
                ->setTitle(_t(__CLASS__ . '.SELECT_MODULES', __CLASS__ . '.SELECT_MODULES'))
        ]);

        return $list;
    }

    protected function OpenPopupButton($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'open_translate_popup',
            _t(__CLASS__ . '.BUTTON_TEXT', __CLASS__ . '.BUTTON_TEXT'),
            'translate',
            null
        );

        $button->addExtraClass('btn btn-primary no-ajax font-icon-explore-addons open-i19n-popup');

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
            'translate',
            _t(__CLASS__ . '.BUTTON_DO_SCAN', __CLASS__ . '.BUTTON_DO_SCAN'),
            'translate',
            null
        );

        $button->addExtraClass('btn btn-primary font-icon-explore-addons');
        $button->setForm($gridField->getForm());


        $close_button = new GridField_FormAction(
            $gridField,
            'closetranslate',
            _t(__CLASS__ . '.BUTTON_DO_CLOSE', __CLASS__ . '.BUTTON_DO_CLOSE'),
            'closetranslate',
            null
        );

        $close_button->addExtraClass('btn btn-outline-danger btn-hide-outline no-ajax font-icon-cancel-circled close-i19n-popup');
        $close_button->setForm($gridField->getForm());

        return ArrayList::create([
            $button,
            $close_button,
        ]);
    }

    public function getActions($gridField)
    {
        return ['translate'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if (($actionName != 'translate')) {
            return null;
        }

        foreach (['TranslateButtonLocale', 'TranslateButtonModule'] as $v) {
            if (!array_key_exists($v, $data) || !count($data[$v])) {
                return null;
            }
        }

        $list_locales = $data['TranslateButtonLocale'];
        $list_modules = $data['TranslateButtonModule'];

        if (!array_key_exists('TranslateSelection', $data) || !$data['TranslateSelection']) {
            return null;
        }

        if ($data['TranslateSelection'] == 'front') {
            i19nTask::run_translate($list_locales, $list_modules);
        } else if ($data['TranslateSelection'] == 'cms') {
            $this->translate_cms_labels($list_locales, $list_modules);
        }

        return null;
    }

    private function isClassPartOfModule($class, $module)
    {
        $classPath = ClassLoader::inst()->getManifest()->getItemPath($class);

        // fix for Windows environment
        if( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
        {
            $classPath = str_replace('/', '\\', $classPath);
        }

        $modulePath = $this->modulePath($module);

        if (strpos($classPath, $modulePath) === 0) {
            return true;
        }

        return false;
    }

    private function modulePath($module)
    {
        $m = ModuleLoader::inst()->getManifest()->getModule($module);
        return $m->getPath();
    }

    private function translate_cms_labels($list_locales, $list_modules)
    {
        $writter = new i19nWritter();

        $classes = [];
        foreach (ClassInfo::subclassesFor(DataObject::class) as $class) {
            foreach ($list_modules as $module) {
                if ($this->isClassPartOfModule($class, $module)) {
                    if (!array_key_exists($module, $classes)) {
                        $classes[$module] = [];
                    }

                    $classes[$module][] = $class;
                    break;
                }
            }
        }

        foreach ($classes as $module => $classArray) {
            foreach ($classArray as $class) {
                $labels = $this->load_cms_labels($class, $module);

                if (count($labels)) {
                    foreach ($list_locales as $locale) {
                        $writter->write($labels, $locale, $this->modulePath($module));
                    }
                }
            }
        }
    }

    /**
     * Load list of all possible field labels for:
     * $db
     * $has_one
     * $has_many
     * $many_many
     *
     * and
     * $summary_fields which include relations
     * @param $class
     * @return string[]
     */
    private function load_cms_labels($class, $module): array
    {
        $res = [];

        $ancestry = ClassInfo::ancestry($class);
        $ancestry = array_reverse($ancestry);

        if ($ancestry) {
            foreach ($ancestry as $ancestorClass) {
                if (($ancestorClass === ViewableData::class) || !$this->isClassPartOfModule($ancestorClass, $module)) {
                    break;
                }

                $types = [
                    'db' => (array) Config::inst()->get($ancestorClass, 'db', Config::UNINHERITED),
                    'has_one' => (array) Config::inst()->get($ancestorClass, 'has_one', Config::UNINHERITED),
                    'has_many' => (array) Config::inst()->get($ancestorClass, 'has_many', Config::UNINHERITED),
                    'many_many' => (array) Config::inst()->get($ancestorClass, 'many_many', Config::UNINHERITED),
                    'belongs_many_many' => (array) Config::inst()->get($ancestorClass, 'belongs_many_many', Config::UNINHERITED),
                ];

                $obj = singleton($ancestorClass);

                foreach ($types as $type => $attrs) {
                    foreach (array_keys($attrs) as $name) {
                        // TODO
                        // fill in field label from object
                        $res["{$ancestorClass}.{$type}_{$name}"] = $obj->fieldLabel($name);
                    }
                }


                $summary = (array) Config::inst()->get($ancestorClass, 'summary_fields', Config::UNINHERITED);
                if (count($summary)) {
                    foreach ($summary as $i => $v) {
                        $check = false;
                        if (is_int($i)) {
                            $check = $v;
                        } else {
                            $check = $i;
                        }

                        if (strrpos($check, '.') !== false) {
                            $name = str_replace('.', '', $check);

                            $res[$name] = "{$ancestorClass}.summary_label_{$name}";
                        }
                    }
                }
            }
        }

        return $res;
    }
}
