<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\View\SSViewer;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;

class RecordProcess extends DataObject
{
    private static $table_name = 'AutomatedContentManagementRecordProcess';

    private static $db = [
        'RecordID' => 'Int',
        'Before' => 'Text',
        'After' => 'Text',
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
        'Title',
        'Description',
        'Accepted',
    ];

    private static $casting = [
        'CanProcess' => 'Boolean',
        'CanNotProcessAnymore' => 'Boolean',
        'RecordTitle' => 'Varchar',
        'HydratedDescription' => 'Text',
        'BeforeHumanValue' => 'Text',
        'AfterHumanValue' => 'Text',
    ];

    private static $default_sort = 'ID';

    public function getCanProcess(): bool
    {
        $instruction = $this->Instruction();
        if ($instruction->ReadyToProcess || $instruction->RunTest) {
            if (!$this->getCanNotProcessAnymore()) {
                return true;
            }
        }
        return false;
    }

    public function getCanNotProcessAnymore()
    {
        $instruction = $this->Instruction();
        return !$instruction->Completed || $instruction->Cancelled;
    }

    public function getRecordTitle()
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->getTitle() . ' (' . $record->ID . ')';
        }
        return 'Record not found';
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $this->addCastingFieldsNow($fields);
        return $fields;
    }

    public function getHydratedDescription(): ?string
    {
        $description = $this->Instruction()->Description;
        $record = $this->getRecord();
        if ($record) {
            $template = SSViewer::create()->fromString($description);
            //FUTURE: SSViewer::create()->renderString($description);
            return $template->process($record)->RAW();
        }
        return null;
    }

    /**
     *
     * @return DataObject|null
     */
    protected function getRecord()
    {
        $className = $this->Instruction()->ClassNameToChange;
        $recordID = $this->RecordID;
        if ($className && $recordID) {
            return $className::get()->byID($recordID);
        }
        return null;
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


    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }
}
