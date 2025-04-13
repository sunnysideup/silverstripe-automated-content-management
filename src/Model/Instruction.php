<?php

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\AutomatedContentManagement\Traits\CMSFieldsExtras;

class Instruction extends DataObject
{

    use CMSFieldsExtras;
    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'ReadyToProcess' => 'Boolean',
        'ClassNameToChange' => 'Varchar(255)',
        'FieldToChange' => 'Varchar(255)',
        'Completed' => 'Boolean',
        'Cancelled' => 'Boolean',
    ];

    private static $has_one = [
        'By' => Member::class,
    ];
    private static $has_many = [
        'RecordProcess' => RecordProcess::class,
    ];

    private static $summary_fields = [
        'Created.Nice' => 'Created',
        'Title' => 'Title',
    ];

    private static $searchable_fields = [
        'Title',
        'Description',
    ];

    private static $field_labels = [
        'Title' => 'Title',
        'Description' => 'Description',
        'ClassNameToChange' => 'Record Type to change',
        'FieldToChange' => 'Field to change',
    ];


    private static $casting = [
        'NumberOfRecords' => 'Int',
        'ProcessedRecords' => 'Int',
        'PercentageCompleted' => 'Percentage',
        'RecordType' => 'Varchar(255)',
        'IsValidForProcessing' => 'Boolean',
    ];

    private static $cascade_delete = [
        'RecordProcess',
    ];


    private static $default_sort = 'ID DESC';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('ByID');
        $this->addCastingFieldsNow($fields);
        if ($this->ReadyToProcess) {
            $readonlyFields = [
                'Title',
                'Description',
                'ClassNameToChange',
                'FieldToChange',
            ];
            foreach ($readonlyFields as $fieldName) {
                $field = $fields->dataFieldByName($fieldName);
                if ($field) {
                    $fields->replaceField(
                        $fieldName,
                        ReadonlyField::create($fieldName, $this->fieldLabel($fieldName), $this->$fieldName)
                    );
                }
            }
        } else {
            $fields->removeByName(
                [
                    'Completed',
                    'Cancelled',
                ]
            );
        }
        return $fields;
    }

    public function getNumberOfRecords(): int
    {
        $className = $this->ClassNameToChange;
        $fieldName = $this->FieldToChange;
        if ($className && $fieldName) {
            return $className::get()->count();
        }
        return 0;
    }

    public function getProcessedRecords(): int
    {
        $className = $this->ClassNameToChange;
        $fieldName = $this->FieldToChange;
        if ($className && $fieldName) {
            return $className::get()->count();
        }
        return 0;
    }

    public function getPercentageCompleted(): float
    {
        return round($this->getProcessedRecords() / $this->getNumberOfRecords() * 100) / 100;
    }

    public function IsValidForProcessing()
    {
        if ($this->Completed) {
            return false;
        }
        $className = $this->ClassNameToChange;
        if (!class_exists($className)) {
            return false;
        }
        if (!$this->getRecordType) {
            return false;
        }
    }

    public function getRecordType(): string
    {
        $className = $this->ClassNameToChange;
        if (class_exists($className)) {
            $obj = Injector::inst()->get($className);
            $db = $obj->db();
            return $db[$this->FieldToChange] ?? 'Error: Field does not exist';
        }
        return 'Error: Class does not exist';
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->ByID) {
            $this->ByID = Security::setCurrentUser()?->ID;
        }
        if (! $this->Completed) {
            if ($this->getNumberOfRecords() === $this->getProcessedRecords()) {
                $this->Completed = true;
            }
        }
        if ($this->ReadyToProcess) {
            $className = $this->ClassNameToChange;
            $ids = $className::get()->columnUnique('ID');
            foreach ($ids as $id) {
                $filter = [
                    'RecordID' => $id,
                    'InstructionID' => $this->ID,
                ];
                $recordProcess = RecordProcess::get()->filter($filter)->first();
                if (! $recordProcess) {
                    $recordProcess = RecordProcess::create($filter);
                }
                $recordProcess->write();
            }
        }
        if ($this->Cancelled) {
            $this->ReadyToProcess = false;
            foreach ($this->RecordProcess() as $recordProcess) {
                $recordProcess->delete();
            }
        }
    }

    public function canEdit($member = null)
    {
        if ($this->Completed) {
            return false;
        }
        return parent::canEdit($member);
    }


    public function canDelete($member = null)
    {
        if ($this->Completed) {
            return false;
        }
        return parent::canDelete($member);
    }
}
