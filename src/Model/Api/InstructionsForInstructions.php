<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model\Api;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBString;

class InstructionsForInstructions
{
    protected $record;

    public function __construct($record)
    {
        if (!$record instanceof DataObject) {
            throw new \InvalidArgumentException('Record must be an instance of DataObject. Provided: ' . get_class($record));
        }
        $this->record = $record;
    }

    public function getInstructions(): string
    {
        $fieldLists  = $this->getListOfFields();
        $sectionsHtml = '';

        foreach ($fieldLists as $header => $items) {
            // hack for better hierarchy
            if (stripos($header, 'Main') === 0) {
                $level = '2';
            } else {
                $level = '3';
            }
            $sectionsHtml .= '
                <h' . $level . '>' . $header . '</h' . $level . '>
                <ul>';
            foreach ($items as $label => $placeholder) {
                $sectionsHtml .= '
                    <li>' . $label . ': <strong>$' . $placeholder . '</strong></li>';
            }
            $sectionsHtml .= '
                </ul>';
        }

        return
            '<h2>Examples of variable usage in your instructions</h2>'
            . '<p>'
            . 'e.g. if you have a record with a Title, Description Field and you are trying to get a summary for each record then your instruction could be something like:'
            . '<br ><em>Consider the Title: $Title and the Description: "$Description" and summarise it in less than five words.</em>'
            . '<br /><br />You can also use <em><% if $Foo %>something only shown if the variable Foo is available <% end_if %></em> to check for the existence of a variable (Foo in this case). For example: '
            . '<br /><em>Consider the Title: $Title <% if $Description %>and the Description: $Description<% end_if %> value(s) for this record and summarise it in less than five words.</em>.'
            . '</p>'
            . '<p>To use values from the record at hand, use the following field variables:</p>'
            . $sectionsHtml;
    }

    protected function getListOfFields(): array
    {
        $allFields = $this->listOfDbFields($this->record);

        foreach (array_keys($this->record->config()->get('has_one') ?: []) as $relation) {
            $related = $this->record->$relation();
            if ($related instanceof DataObjectInterface) {
                $allFields += $this->listOfDbFields($related, $relation);
            }
        }

        return $allFields;
    }

    protected function listOfDbFields(DataObject $record, ?string $prefix = ''): array
    {
        $prefixString = $prefix ? $prefix . '.' : '';
        $fieldLabels  = $record->fieldLabels();
        $prefixName   = $prefix
            ? ($fieldLabels[$prefix] ?? $prefix)
            : $record->i18n_singular_name();

        $mainKey  = 'Main fields for <strong>' . $prefixName . '</strong>';
        $otherKey = 'Other fields <strong>' . $prefixName . '</strong>';

        $mainFields  = [];
        $otherFields = [];

        foreach ($record->config()->get('db') as $name => $type) {
            $label       = $fieldLabels[$name] ?? $name;
            $placeholder = $prefixString . $name;

            if ($record->dbObject($name) instanceof DBString) {
                $mainFields[$label]  = $placeholder;
            } else {
                $otherFields[$label] = $placeholder;
            }
        }

        return [
            $mainKey  => $mainFields,
            $otherKey => $otherFields,
        ];
    }
}
