<?php

namespace Innovatif\i19n\GridField\Button;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionProvider;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridField_HTMLProvider;
use SilverStripe\Forms\GridField\GridField_URLHandler;
use SilverStripe\Forms\GridField\GridFieldFilterHeader;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\i18n\Messages\YamlWriter;
use SilverStripe\ORM\DataObject;

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 */
class GridFieldExportYMLButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler
{
    use Injectable;

    /**
     * @var array Map of a property name on the exported objects, with values being the column title in the CSV file.
     * Note that titles are only used when {@link $csvHasHeader} is set to TRUE.
     */
    protected $exportColumns;

    /**
     * Fragment to write the button to
     */
    protected $targetFragment;

    public function __construct($targetFragment = "after", $exportColumns = null)
    {
        $this->targetFragment = $targetFragment;
        $this->exportColumns = $exportColumns;
    }

    public function getHTMLFragments($gridField)
    {
        $button = new GridField_FormAction(
            $gridField,
            'ymlexport',
            _t(__CLASS__ . '.DO_EXPORT', __CLASS__ . '.DO_EXPORT'),
            'ymlexport',
            null
        );
        $button->addExtraClass('btn btn-secondary no-ajax font-icon-down-circled action_export');
        $button->setForm($gridField->getForm());
        return [
            $this->targetFragment => $button->Field(),
        ];
    }

    public function getActions($gridField)
    {
        return ['ymlexport'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'ymlexport') {
            return $this->handleYMLExport($gridField);
        }
        return null;
    }

    /**
     * Handle the export, for both the action button and the URL
     *
     * @param GridField $gridField
     * @param HTTPRequest $request
     *
     * @return HTTPResponse
     */
    public function handleYMLExport($gridField, $request = null)
    {
        return $this->generateExportFileData($gridField);
    }

    /**
     * Generate export fields for CSV.
     *
     * @param GridField $gridField
     *
     * @return string
     */
    public function generateExportFileData($gridField)
    {
        if( !$gridField->getList()->count() )
        {
            return false;
        }
        $gridField->getConfig()->removeComponentsByType(GridFieldPaginator::class);
        $items = $gridField->getManipulatedList();

        // @todo should GridFieldComponents change behaviour based on whether others are available in the config?
        foreach ($gridField->getConfig()->getComponents() as $component) {
            if ($component instanceof GridFieldFilterHeader) {
                $items = $component->getManipulatedData($gridField, $items);
            }
        }


        $list_translations = [];

        /** @var DataObject $item */
        foreach ($items->limit(null) as $item) {
            if (!$item->hasMethod('canView') || $item->canView()) {
                $locale = $item->Locale;

                if (!isset($list_translations[$locale])) {
                    $list_translations[$locale] = [];
                }

                $list_translations[$locale][$item->Entity] = $item->Value;
            }

            if ($item->hasMethod('destroy')) {
                $item->destroy();
            }
        }



        $writer = new YamlWriter();
        $now = date("d-m-Y-H-i");

        if (count($list_translations) == 0) {
            return false;
        } elseif (count($list_translations) == 1) {
            if (!function_exists('array_key_first')) {
                $locale = array_keys($list_translations)[0];
            } else {
                $locale = array_key_first($list_translations);
            }

            $messages = $list_translations[$locale];

            $fileData = $writer->getYaml($messages, $locale);


            $fileName = "$locale-$now.yml";

            return HTTPRequest::send_file($fileData, $fileName, 'application/x-yaml');
        } else {
            // 3 put it in zip
            $zip = new \ZipArchive();

            // create temporary file
            $temp_file = tempnam(sys_get_temp_dir(), 'i19n');

            $zip->open($temp_file);

            foreach ($list_translations as $locale => $messages) {
                $content = $writer->getYaml($messages, $locale);

                $zip->addFromString("$locale-$now.yml", $content);
            }
            $zip->close();

            $fileData = file_get_contents($temp_file);

            // remove tmp file
            unlink($temp_file);


            $fileName = "i19n_export-$now.zip";

            return HTTPRequest::send_file($fileData, $fileName, 'application/zip');
        }

    }

    public function getURLHandlers($gridField)
    {
        return [
            'ymlexport' => 'handleYMLExport',
        ];
    }
}
