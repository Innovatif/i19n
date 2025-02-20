<?php

namespace Innovatif\i19n\ModelAdmin;

use Innovatif\i19n\GridField\Button\GridFieldAddEntryButton;
use Innovatif\i19n\GridField\Button\GridFieldClearCacheButton;
use Innovatif\i19n\GridField\Button\GridFieldExportCSVButton;
use Innovatif\i19n\GridField\Button\GridFieldExportYMLButton;
use Innovatif\i19n\GridField\Button\GridFieldTranslateButton;
use Innovatif\i19n\GridField\Button\GridFieldYmlImportButton;
use Innovatif\i19n\GridField\ExtendedGridFieldEditableColumns;
use Innovatif\i19n\GridField\GridFieldEditableDataColumns;
use Innovatif\i19n\Model\i19n;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;
use Terraformers\RichFilterHeader\Form\GridField\RichFilterHeader;
use Terraformers\RichFilterHeader\Form\GridField\RichSortableHeader;
use TractorCow\Fluent\Model\Locale;

class i19nAdmin extends LeftAndMain implements PermissionProvider
{
    use Configurable;

    private static $menu_title = 'i19n Editor';

    private static $menu_icon_class = 'font-icon-language';

    private static $url_segment = 'i19n-editor';

    private static $required_permission_codes = 'CMS_ACCESS_i19nAdmin';

    private static $url_rule = '/$ModelClass/$Action';

    private static $url_handlers = [
        '$ModelClass/$Action' => 'handleAction'
    ];

    private static $allowed_actions = [
        'EditForm',
        'ImportForm',
        'save',
    ];

    /**
     * Define number of records per page for Paginator.
     * @var integer
     */
    private static $records_per_page = false;

    protected $modelClass;

    protected function init()
    {
        parent::init();

        if ($this->getRequest()->param('ModelClass')) {
            $this->modelClass = $this->unsanitiseClassName($this->getRequest()->param('ModelClass'));
        } else {
            $this->modelClass = i19n::class;
        }
    }


    public function getEditForm($id = null, $fields = null)
    {
        $list = $this->getList();

        $listField = GridField::create(
            $this->sanitiseClassName($this->modelClass),
            false,
            $list,
            $fieldConfig = GridFieldConfig_RecordEditor::create($this->config()->get('page_length'))
        );

        $fieldConfig->removeComponentsByType(GridFieldDataColumns::class);
        $fieldConfig->addComponent(GridFieldEditableDataColumns::create(), GridFieldEditButton::class);

        $fieldConfig->removeComponentsByType([
            GridFieldFilterHeader::class,
        ]);

        $filter = RichFilterHeader::create();
        $filter
            ->setFilterConfig(
                [
                    'Entity' => [
                        'title' => 'Entity'
                    ],
                    'Value' => [
                        'title' => 'Value',
                        'filter' => 'PartialMatchFilter'
                    ],
                    'IsBackend' => [
                        'title' => 'IsBackend'
                    ],
                    'Locale' => [
                        'filter' => 'ExactMatchFilter'
                    ],
                    'ModulePath',
                ]
            )
            ->setFilterFields(
                [
                    'IsBackend' => DropdownField::create('', '', [
                        1 => _t(i19n::class . '.IS_BACKEND_NO', i19n::class . '.IS_BACKEND_NO'),
                        2 => _t(i19n::class . '.IS_BACKEND_YES', i19n::class . '.IS_BACKEND_YES')
                    ])->setEmptyString('-'),
                    'Locale' => DropdownField::create('', '',
                        Locale::get()->map('Locale', 'Title')
                    )->setEmptyString('-'),
                ]
            )
            ->setFilterMethods(
                [
                    'Entity' => function (DataList $list, $name, $value) {
                        $v = preg_replace('/([\\\])\1+/', '\\', $value);
                        $v = str_replace('\\', '\\\\', $v);

                        return $list->filter('Entity:PartialMatch', $v);
                    },
                    'IsBackend' => function (DataList $list, $name, $value) {
                        $partial = ['.db_', '.has_one_', '.has_many_', '.many_many_', '.belongs_many_many_', '.PLURALS.'];
                        $endsWith = ['.SINGULARNAME', '.PLURALNAME', '.PLURALS', '.DESCRIPTION', '.MENUTITLE'];

                        if ($value == 2) {
                            $list = $list->filterAny(['Entity:EndsWith' => $endsWith, 'Entity:PartialMatch' => $partial]);
                        } elseif ($value == 1) {
                            $list = $list->excludeAny('Entity:EndsWith', $endsWith);
                            $list = $list->excludeAny('Entity:PartialMatch', $partial);
                        }

                        return $list;
                    }
                ]
            );

        $fieldConfig->addComponent($filter, GridFieldPaginator::class);

        $fieldConfig->removeComponentsByType([
            GridFieldAddNewButton::class,
            GridFieldSortableHeader::class,
        ]);
        $fieldConfig->addComponent(Injector::inst()->get(RichSortableHeader::class));

        if (!Permission::check('ADMIN')) {
            $fieldConfig->removeComponentsByType([
                GridFieldEditButton::class,
            ]);
        }

        $fieldConfig->addComponent(GridFieldAddEntryButton::create('buttons-before-left'));
        $fieldConfig->addComponent(GridFieldTranslateButton::create('buttons-before-left'));
        $fieldConfig->addComponent(GridFieldClearCacheButton::create('buttons-before-left'));
        $fieldConfig->addComponent(GridFieldYmlImportButton::create('buttons-before-left')->setImportForm($this->ImportForm()));
        $fieldConfig->addComponent(GridFieldExportYMLButton::create('buttons-before-left'));
        $fieldConfig->addComponent(GridFieldExportCSVButton::create('buttons-before-left'));

        if ($this->getItemsPerPage()) {
            $fieldConfig->getComponentByType(GridFieldPaginator::class)->setItemsPerPage($this->getItemsPerPage());
        }

        $editable = new ExtendedGridFieldEditableColumns();
        $editable->setDisplayFields([
            'Value' => [
                'title' => _t(i19n::class . '.db_Value', i19n::class . '.db_Value'),
                'callback' => fn($record, $column, $grid) => TextField::create($column)->setTitle('Vrednost'),
            ]
        ]);
        $fieldConfig->addComponent($editable);

        $form = Form::create(
            $this,
            'EditForm',
            FieldList::create($listField),
            FieldList::create([
                FormAction::create('save')
                    ->setTitle(_t(self::class . '.BUTTON_SAVE_RECORDS', self::class . '.BUTTON_SAVE_RECORDS'))
                    ->addExtraClass('btn-primary')
            ])
        )->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form cms-panel-padded center flexbox-area-grow');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $editFormAction = Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'EditForm');
        $form->setFormAction($editFormAction);
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');
        $form->addExtraClass('fill-height');
        $this->extend('updateEditForm', $form);

        $form->addExtraClass('fill-height i19n-admin');
        return $form;
    }

    /**
     * Get number of items per page
     * @return int|false
     */
    public function getItemsPerPage()
    {
        // TODO: Add option to Member?
        return $this->config()->get('records_per_page');
    }

    public function getList()
    {
        $list = $this->modelClass::get();

        $this->extend('updateList', $list);

        return $list;
    }

    public function Link($action = null)
    {
        if (!$action) {
            $action = $this->sanitiseClassName($this->modelClass);
        }
        return parent::Link($action);
    }

    protected function sanitiseClassName($class)
    {
        return str_replace('\\', '-', $class ?? '');
    }

    protected function unsanitiseClassName($class)
    {
        return str_replace('-', '\\', $class);
    }

    public function save(array $data, Form $form): HTTPResponse
    {
        $request = $this->getRequest();

        // save form data into record
        $form->saveInto(DataObject::create(), true);

        $response = $this->getResponse();
        $response->addHeader('X-Status', _t(self::class . '.ACTION_SAVED', self::class . '.ACTION_SAVED'));
        return $response;
    }

    /**
     * Form for YML import
     * @return \SilverStripe\Forms\Form
     */
    public function ImportForm(): Form
    {
        $fields = FieldList::create([
            FileField::create('ymlFile')
                ->setTitle(_t(self::class . '.IMPORT_UPLOAD_YML', self::class . '.IMPORT_UPLOAD_YML'))
                ->setAllowedExtensions(['yml']),
            CheckboxField::create('UpdateExisting')
                ->setTitle(_t(self::class . '.IMPORT_UPDATE_EXISTING', self::class . '.IMPORT_UPDATE_EXISTING'))
                ->setValue(false)
        ]);

        $actions = FieldList::create(
            FormAction::create('importYML', _t(self::class . '.BUTTON_DO_IMPORT', self::class . '.BUTTON_DO_IMPORT'))
                ->addExtraClass('btn btn-outline-secondary font-icon-upload')
        );

        $required = RequiredFields::create([
            'ymlFile'
        ]);

        $form = Form::create(
            $this,
            "ImportForm",
            $fields,
            $actions,
            $required
        );

        $form->setFormAction(
            Controller::join_links($this->Link($this->sanitiseClassName($this->modelClass)), 'ImportForm')
        );

        $this->extend('updateImportForm', $form);

        return $form;
    }

    /**
     * Handle form import action
     * @param $data
     * @param $form
     * @param $request
     * @return boolean|array|\SilverStripe\Control\HTTPResponse
     */
    public function importYML($data, $form, $request)
    {
        if (empty($_FILES['ymlFile']['tmp_name']) ||
            file_get_contents($_FILES['ymlFile']['tmp_name']) == ''
        ) {
            $form->sessionMessage(
                _t(self::class . '.IMPORT_NO_YML_FILE', self::class . '.IMPORT_NO_YML_FILE'),
                ValidationResult::TYPE_ERROR
            );
            $this->redirectBack();
            return false;
        }

        $path = $_FILES['ymlFile']['tmp_name'];

        $parser = new Parser();

        if (!file_exists($path)) {
            return [];
        }
        // Load
        try {
            $yaml = $parser->parse(file_get_contents($path));
        } catch (ParseException $e) {
            $form->sessionMessage(
                $e->getMessage(),
                ValidationResult::TYPE_ERROR
            );
            $this->redirectBack();
            return false;
        }

        if (!is_array($yaml) || count($yaml) < 1) {
            $form->sessionMessage(
                _t(self::class . '.IMPORT_NO_DATA', self::class . '.IMPORT_NO_DATA'),
                ValidationResult::TYPE_ERROR
            );
            $this->redirectBack();
            return false;
        }

        $count_updates = 0;
        $count_inserts = 0;

        $update_existing = isset($data['UpdateExisting']);

        foreach ($yaml as $locale => $list_translations) {
            $db_locale = Locale::get()->filter(['Locale:StartsWith:nocase' => $locale])->sort('"IsGlobalDefault" DESC, "Locale" ASC')->first();

            if (!$db_locale) {
                $locale_parts = explode('_', $locale);

                if (count($locale_parts) == 2) {
                    $locale = reset($locale_parts);
                    $db_locale = Locale::get()->filter(['Locale:StartsWith:nocase' => $locale])->sort('"IsGlobalDefault" DESC, "Locale" ASC')->first();
                }
            }

            if (!$db_locale) {
                $form->sessionMessage(_t(self::class . '.SAVED_ACTION', self::class . '.SAVED_ACTION', ['inserted' => $count_inserts, 'updated' => $count_updates, 'file_name' => $_FILES['ymlFile']['name']]), 'good');
                return $this->redirectBack();
            }

            foreach ($list_translations as $entity => $translation_data) {
                // most common case
                if( is_array($translation_data) )
                {
                    foreach ($translation_data as $sub_entity => $translation_value) {
                        if (!is_array($translation_value)) {
                            $entity_query = sprintf('%s.%s', $entity, $sub_entity);

                            $result = $this->importForm_addValue($db_locale->Locale, $entity_query, $translation_value, $update_existing);

                            if ($result === true) {
                                $count_updates++;
                            } elseif ($result === false) {
                                $count_inserts++;
                            }
                        } else {
                            foreach ($translation_value as $sub_key => $sub_trans_value) {
                                $entity_query = sprintf('%s.%s.%s', $entity, $sub_entity, $sub_key);

                                $result = $this->importForm_addValue($db_locale->Locale, $entity_query, $sub_trans_value, $update_existing);

                                if ($result === true) {
                                    $count_updates++;
                                } elseif ($result === false) {
                                    $count_inserts++;
                                }
                            }
                        }
                    }
                } else {
                    // unusual case
                    // en:
                    //   SomeKey: 'SomeValue'
                    $result = $this->importForm_addValue($db_locale->Locale, $entity, $translation_data, $update_existing);

                    if ($result === true) {
                        $count_updates++;
                    } elseif ($result === false) {
                        $count_inserts++;
                    }
                }
            }
        }

        $form->sessionMessage(_t(self::class . '.SAVED_ACTION', self::class . '.SAVED_ACTION', ['inserted' => $count_inserts, 'updated' => $count_updates, 'file_name' => $_FILES['ymlFile']['name']]), 'good');
        return $this->redirectBack();
    }

    /**
     * Add value from YML file
     * @param string $locale
     * @param string $entity_query
     * @param string $translation_value
     * @param boolean $update_existing Update value if it exists. Only adds new translations if this option is disabled.
     * @return NULL|boolean Return true if update, false if insert or null if no write was performed
     */
    private function importForm_addValue($locale, $entity_query, $translation_value, $update_existing = false): ?bool
    {
        $i19n = i19n::get()->filter([
            'Entity' => $entity_query,
            'Locale' => $locale,
        ])->first();
        $is_update = true;

        if (!$i19n) {
            $i19n = i19n::create();
            $is_update = false;
        } else {
            if (!$update_existing) {
                // skip update
                return null;
            }
        }

        $i19n->Entity = $entity_query;
        $i19n->Locale = $locale;
        $i19n->Value = $translation_value;
        $i19n->write();

        if ($is_update) {
            return true;
        }
        return false;
    }

    public function providePermissions()
    {
        return [
            "CMS_ACCESS_i19nAdmin" => [
                'name' => _t('SilverStripe\\CMS\\Controllers\\CMSMain.ACCESS', "Access to '{title}' section", [
                    'title' => static::menu_title()
                ]),
                'category' => _t('SilverStripe\\Security\\Permission.CMS_ACCESS_CATEGORY', 'CMS Access')
            ]
        ];
    }
}
