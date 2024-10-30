<?php

namespace Innovatif\i19n\GridField;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

/**
 * Added another condition - to allow Closure as searchable column
 * @author klemend
 *
 */
class GridFieldEditableDataColumns extends GridFieldDataColumns
{
    use Injectable;

    /**
     * Added another condition - to allow Closure as searchable column
     * {@inheritDoc}
     * @see \SilverStripe\Forms\GridField\GridFieldDataColumns::getColumnMetadata()
     */
    public function getColumnMetadata($gridField, $column)
    {
        $columns = $this->getDisplayFields($gridField);

        $title = null;
        if (is_string($columns[$column])) {
            $title = $columns[$column];
        } elseif (is_array($columns[$column]) && isset($columns[$column]['title'])) {
            $title = $columns[$column]['title'];
        } elseif ($columns[$column] instanceof \Closure) {
            $title = $column;
        }

        return [
            'title' => $title,
        ];
    }
}
