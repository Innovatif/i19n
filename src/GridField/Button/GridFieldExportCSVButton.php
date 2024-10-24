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

/**
 * Adds an "Export list" button to the bottom of a {@link GridField}.
 */
class GridFieldExportCSVButton implements GridField_HTMLProvider, GridField_ActionProvider, GridField_URLHandler
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
            'csvexport',
            _t(self::class . '.DO_EXPORT', self::class . '.DO_EXPORT'),
            'csvexport',
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
        return ['csvexport'];
    }

    public function handleAction(GridField $gridField, $actionName, $arguments, $data)
    {
        if ($actionName == 'csvexport') {
            return $this->handleCSVExport($gridField);
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
    public function handleCSVExport($gridField, $request = null)
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
        $gridField->getConfig()->removeComponentsByType(GridFieldPaginator::class);
        $items = $gridField->getManipulatedList();

        // @todo should GridFieldComponents change behaviour based on whether others are available in the config?
        foreach ($gridField->getConfig()->getComponents() as $component) {
            if ($component instanceof GridFieldFilterHeader) {
                $items = $component->getManipulatedData($gridField, $items);
            }
        }

        $streamData = $this->GroupedByEntity($items);
        // $streamData = $this->NotGrouped($items);

        return HTTPRequest::send_file($streamData, 'i18n-' . date("d-m-Y-H-i") . '.csv', 'text/csv');
    }

    public function GroupedByEntity($items)
    {
        $stream = fopen('php://temp', 'w+');
        fputs($stream, "\xEF\xBB\xBF");

        $locales = $items->column('Locale');
        $locales = array_unique($locales);

        $entities = $items->column('Entity');
        $entities = array_unique($entities);

        $map = [];
        foreach ($items as $item) {
            if (!$item->hasMethod('canView') || $item->canView()) {
                $map[$item->Entity][$item->Locale] = $item->Value;
            }

            if ($item->hasMethod('destroy')) {
                $item->destroy();
            }
        }

        fputcsv($stream, array_merge([''], $locales), ';');
        foreach ($entities as $entity) {
            $row = [$entity];
            foreach ($locales as $locale) {
                if (isset($map[$entity][$locale])) {
                    $row[] = $map[$entity][$locale];
                } else {
                    $row[] = '';
                }
            }
            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $streamData = stream_get_contents($stream);
        fclose($stream);

        return $streamData;
    }

    public function NotGrouped($items)
    {
        $stream = fopen('php://temp', 'w+');
        fputs($stream, "\xEF\xBB\xBF");

        foreach ($items as $item) {
            if (!$item->hasMethod('canView') || $item->canView()) {
                fputcsv($stream, [$item->Locale, $item->Entity, $item->Value], ';');
            }

            if ($item->hasMethod('destroy')) {
                $item->destroy();
            }
        }

        rewind($stream);
        $streamData = stream_get_contents($stream);
        fclose($stream);

        return $streamData;
    }

    public function getURLHandlers($gridField)
    {
        return [
            'csvexport' => 'handleCSVExport',
        ];
    }
}
