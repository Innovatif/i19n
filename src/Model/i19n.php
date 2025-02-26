<?php

namespace Innovatif\i19n\Model;

use Innovatif\i19n\Cache\i19nCache;
use Innovatif\i19n\Library\i19nLibrary;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Filters\PartialMatchFilter;
use SilverStripe\ORM\GroupedList;
use SilverStripe\ORM\Search\SearchContext;
use SilverStripe\Security\Permission;

class i19n extends DataObject
{

    private static $db = [
        'Entity' => 'Varchar(255)',
        'Value' => 'Text',
        'Locale' => 'Varchar(10)',
        'ModulePath' => 'Text',
    ];

    private static $summary_fields = [
        'Entity',
        'Value',
        'IsBackend',
        'Locale',
        'ModulePath',
        'Created',
    ];

    private static $default_sort = 'Entity ASC';

    private static $table_name = 'i19n';


    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $modules = array_combine(array_keys(i19nLibrary::getModulesAndThemes()), array_keys(i19nLibrary::getModulesAndThemes()));
        $fields->replaceField('ModulePath', DropdownField::create('ModulePath')->setTitle($this->fieldLabel('ModulePath'))->setSource($modules));

        $fields->dataFieldByName('Locale')->setReadonly(true);

        if (!Permission::check('ADMIN')) {
            $fields->dataFieldByName('Entity')->setReadonly(true);
        }

        return $fields;
    }

    /**
     * Add additional field labels
     */
    public function fieldLabels($includerelations = true): array
    {
        $labels = parent::fieldLabels($includerelations);

        $labels['IsBackend'] = _t(static::class . '.IS_BACKEND', static::class . '.IS_BACKEND');

        return $labels;
    }

    public function getDefaultSearchContext(): SearchContext
    {
        $filters = [
            'Entity' => PartialMatchFilter::create('Entity'),
            'Locale' => PartialMatchFilter::create('Locale'),
            'Value' => PartialMatchFilter::create('Value'),
            'ModulePath' => PartialMatchFilter::create('ModulePath'),
        ];

        $fields = $this->scaffoldSearchFields([
            'restrictFields' => array_keys($filters)
        ]);

        $locsource = GroupedList::create(i19n::get())->GroupedBy('Locale')->map('Locale', 'Locale');
        $fields->dataFieldByName('Locale')
            ->setSource($locsource)
            ->setEmptyString(_t(i19n::class . '.Select', i19n::class . '.Select'));

        return SearchContext::create(
            i19n::class,
            $fields,
            $filters
        );
    }

    /**
     * Simple method to determine if current translation is used in is backend or frontend.
     */
    public function getIsBackend(): string
    {
        foreach (['db', 'has_one', 'has_many', 'many_many'] as $rel) {
            if (str_contains($this->Entity, '.' . $rel . '_')) {
                return _t(i19n::class . '.IS_BACKEND_YES', i19n::class . '.IS_BACKEND_YES');
            }
        }

        foreach (['.SINGULARNAME', '.PLURALNAME', '.PLURALS', '.DESCRIPTION', '.MENUTITLE'] as $e) {
            if (str_ends_with($this->Entity, $e)) {
                return _t(i19n::class . '.IS_BACKEND_YES', i19n::class . '.IS_BACKEND_YES');
            }
        }

        return _t(i19n::class . '.IS_BACKEND_NO', i19n::class . '.IS_BACKEND_NO');
    }

    /**
     * Provide title for CMS breadcrumbs
     */
    public function getTitle(): string
    {
        return $this->Entity;
    }

    /**
     * Reset cache value
     */
    public function onAfterWrite(): void
    {
        parent::onAfterWrite();

        $cacheKey = i19nCache::get_cache_key($this->Entity, $this->Locale);

        if (i19nCache::has_value($cacheKey)) {
            i19nCache::delete_value($cacheKey);
        }

        i19nCache::set_value($cacheKey, $this->Value);
    }

    /**
     * Delete cache value
     */
    public function onBeforeDelete(): void
    {
        parent::onBeforeDelete();

        $cacheKey = i19nCache::get_cache_key($this->Entity, $this->Locale);

        if (i19nCache::has_value($cacheKey)) {
            i19nCache::delete_value($cacheKey);
        }
    }
}
