<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\SSViewer;
use SilverStripe\View\SSViewer_FromString;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Traits\CMSFieldsExtras;

class RecordProcess extends DataObject
{


    use CMSFieldsExtras;

    private static $table_name = 'AutomatedContentManagementRecordProcess';

    private static $db = [
        'RecordID' => 'Int',
        'Before' => 'Text',
        'After' => 'Text',
        'Skip' => 'Boolean',
        'Started' => 'Boolean',
        'Completed' => 'Boolean',
        'Accepted' => 'Boolean',
        'Rejected' => 'Boolean',
        'OriginalUpdated' => 'Boolean',
        'IsTest' => 'Boolean',
    ];

    private static $has_one = [
        'Instruction' => Instruction::class,
    ];

    private static $summary_fields = [
        'Created.Nice' => 'Created',
        'Instruction.Title' => 'Action',
        'RecordTitle' => 'Record',
        'Accepted.Nice' => 'Change Accepted',
    ];

    private static $searchable_fields = [
        'RecordID' => 'Int',
        'Before' => 'Text',
        'After' => 'Text',
        'IsTest' => 'Boolean',
        'Skip' => 'Boolean',
        'Started' => 'Boolean',
        'Completed' => 'Boolean',
        'Accepted' => 'Boolean',
        'Rejected' => 'Boolean',
        'OriginalUpdated' => 'Boolean',
    ];

    private static $casting = [
        'CanProcess' => 'Boolean',
        'CanNotProcessAnymore' => 'Boolean',
        'RecordTitle' => 'Varchar',
        'HydratedInstructions' => 'Text',
        'BeforeHumanValue' => 'Text',
        'AfterHumanValue' => 'Text',
    ];

    private static $field_labels = [
        'Before' => 'Before value',
        'After' => 'After value',
        'Skip' => 'Skip conversion for this record',
        'Started' => 'Conversion started',
        'Completed' => 'Conversion completed',
        'Accepted' => 'Accept change',
        'Rejected' => 'Reject change',
        'OriginalUpdated' => 'Original Record updated with new value',
        'IsTest' => 'Is test only',
    ];

    private static $default_sort = 'ID';

    public function getCanProcess(): bool
    {
        if ($this->Skip) {
            return false;
        }
        $instruction = $this->Instruction();
        if ($instruction->ReadyToProcess || $instruction->RunTest) {
            if (!$this->getCanNotProcessAnymore()) {
                return true;
            }
        }
        return false;
    }

    public function getCanNotProcessAnymore(): bool
    {
        $instruction = $this->Instruction();
        return $instruction->Cancelled || $this->Completed || $this->Skip;
    }

    public function getRecordTitle()
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->getTitle() . ' (' . $record->ID . ')';
        }
        return 'Record not found';
    }

    public function getHydratedInstructions(): ?string
    {
        $description = $this->Instruction()->Description;
        $record = $this->getRecord();
        if ($record) {
            $template = SSViewer_FromString::create($description);
            //FUTURE: SSViewer::create()->renderString($description);
            $return = $template->process($record);
            if ($return instanceof DBField) {
                return $return->forTemplate();
            }
            return $return;
        }
        return null;
    }

    /**
     *
     * @return DataObject|null
     */
    public function getRecord()
    {
        $className = $this->Instruction()->ClassNameToChange;
        $recordID = $this->RecordID;
        if ($className && $recordID) {
            return $className::get()->byID($recordID);
        }
        return null;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $this->addCastingFieldsNow($fields);
        $fields->removeByName('RecordID');
        $fields->addFieldsToTab(
            'Root.Main',
            [
                $fields->dataFieldByName('InstructionID'),
                $fields->fieldByName('Root.Details.RecordTitleNICE'),
            ],
            'Before'
        );
        $this->makeFieldsReadonly($fields);
        return $fields;
    }


    protected function makeFieldsReadonlyInner(string $fieldName): bool
    {
        // always readonly
        switch ($fieldName) {
            case 'InstructionID':
            case 'Before':
            case 'After':
            case 'Started':
            case 'Completed':
            case 'OriginalUpdated':
            case 'IsTest':
                return true;
            default:
                break;
        }
        if ($this->getCanNotProcessAnymore() !== true) {
            switch ($fieldName) {
                case 'Accepted':
                case 'Rejected':
                    return true;
                default:
                    break;
            }
        } else {
            switch ($fieldName) {
                case 'Skip':
                    return true;
                default:
                    break;
            }
        }
        return false;
    }




    public function getBeforeHumanValue(): string
    {
        return $this->getHumanValue($this->Before);
    }
    public function getAfterHumanValue(): string
    {
        return $this->getHumanValue($this->After);
    }

    public function getHumanValue(mixed $value): string
    {
        $type = $this->getRecordType();
        switch ($type) {
            case 'Varchar':
            case 'Text':
                return (string) $value;
            case 'Int':
            case 'Float':
                return (string) $value;
            case 'Boolean':
                return $value ? 'Yes' : 'No';
            case 'Datetime':
                return date('Y-m-d H:i:s', strtotime($value));
            default:
                return (string) $value;
        }
    }


    public function getBeforeDatabaseValue(): mixed
    {
        return $this->getDatabaseValue((string) $this->Before);
    }

    public function getAfterDatabaseValue(): mixed
    {
        return $this->getDatabaseValue((string) $this->After);
    }

    public function getDatabaseValue(string $value)
    {
        $type = $this->getRecordType();
        switch ($type) {
            case 'Varchar':
            case 'Text':
                return (string) $value;
            case 'Int':
                return (int) $value;
            case 'Float':
                return (float) $value;
            case 'Boolean':
                if ($value === 'true' || $value === '1' || $value === 'yes' || $value === 'on' || $value === 'True' || $value === true) {
                    return true;
                }
                return false;
            case 'Datetime':
                return date('Y-m-d H:i:s', strtotime($value));
            default:
                return (string) $value;
        }
    }

    public function getRecordType(): ?string
    {
        return $this->Instruction()?->getRecordType();
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        if ($this->Accepted || $this->Rejected) {
            return false;
        }
        return parent::canEdit($member);
    }

    public function canDelete($member = null)
    {
        return false;
    }
}
