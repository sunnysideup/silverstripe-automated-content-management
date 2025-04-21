<?php

namespace Sunnysideup\AutomatedContentManagement\Model\Api;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;

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
        $listItems = [];
        $listItems = array_merge($listItems, $this->listOfDbFields($this->record));
        $hasOne = array_keys($this->record->config()->get('has_one'));
        foreach ($hasOne as $name) {
            $hasOneRecord = $this->record->$name();
            if ($hasOneRecord && $hasOneRecord instanceof DataObjectInterface) {
                $listItems = array_merge(
                    $listItems,
                    $this->listOfDbFields(
                        $hasOneRecord,
                        $name
                    )
                );
            }
        }
        return
            '
        To use values from the record at hand, use the following field variables:<br />
        <ul>
            <li>' . implode('</li><li>', $listItems) . '</li>
        </ul>
        e.g. if you have a record with a Title, Description Field and you are trying to get a summary for each record then your instruction could be something like:<br />
        <br />
        <em>Consider the Title: "$Title" and the Description: "$Description" and summarise it in less than five words.</em>
        <br />
        ';
    }

    protected function listOfDbFields($record, ?string $preFix = ''): array
    {
        $prefix = $preFix ? $preFix . '.' : '';
        $fieldLabels = $record->fieldLabels();
        $dbFields = array_keys($record->config()->get('db'));
        $listItems = [];
        foreach ($dbFields as $name) {
            $listItems[] = '<strong>$' . $prefix . $name . '</strong> (' . ($fieldLabels[$name] ?? $name) . ')';
        }
        return $listItems;
    }
}
