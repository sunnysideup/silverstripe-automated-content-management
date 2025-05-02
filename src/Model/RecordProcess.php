<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\SSViewer_FromString;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Traits\MakeFieldsRoadOnly;

class RecordProcess extends DataObject
{
    use MakeFieldsRoadOnly;

    private static $table_name = 'AutomatedContentManagementRecordProcess';

    private static $db = [
        'RecordID' => 'Int',
        'Before' => 'Text',
        'After' => 'Text',
        'ErrorFound' => 'Boolean',
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
        'Instruction.Title' => 'Action',
        'RecordTitle' => 'Record',
        'IsTest.Nice' => 'Test Only',
        'Started.Nice' => 'Started Processing',
        'Completed.Nice' => 'Completed Processing',
        'Accepted.Nice' => 'Change Accepted',
        'OriginalUpdated.Nice' => 'Originating Record Updated',
    ];

    private static $searchable_fields = [
        'RecordID',
        'Before',
        'After',
        'ErrorFound',
        'IsTest',
        'Skip',
        'Started',
        'Completed',
        'Accepted',
        'Rejected',
        'OriginalUpdated',
    ];

    private static $casting = [
        'FindErrorsOnly' => 'Boolean',
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
        'Instruction' => 'LLM Instruction',
    ];

    private static $default_sort = 'ID';

    public function getFindErrorsOnly(): bool
    {
        return (bool) $this->Instruction()->FindErrorsOnly;
    }

    public function getCanProcess(): bool
    {
        if ($this->Skip) {
            return false;
        }
        $instruction = $this->Instruction();
        if ($instruction->getIsReadyForProcessing()) {
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
            return $record->getTitle(); //. ' (ID: #' . $record->ID . ')';
        }
        return 'Error: record not found'; // (ID: #' . $this->RecordID . ')';
    }

    public function getHydratedInstructions(): string
    {
        $description = $this->Instruction()->Description;
        $record = $this->getRecord();
        $v = '';
        if ($record) {
            $template = SSViewer_FromString::create($description);
            //FUTURE: SSViewer::create()->renderString($description);
            $return = $template->process($record);
            if ($return instanceof DBField) {
                $v = $return->forTemplate();
            } else {
                $v = $return;
            }
        }
        $add = $this->getAlwaysAddedInstruction();
        if ($add) {
            $v .= PHP_EOL . PHP_EOL . $add;
        }
        return $v;
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

    public function getAlwaysAddedInstruction(): string
    {
        $instruction = $this->Instruction();
        if ($instruction->AlwaysAddedInstruction) {
            return $instruction->AlwaysAddedInstruction;
        }
        return '';
    }



    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        Injector::inst()->get(AddCastedVariablesHelper::class)->AddCastingFields(
            $this,
            $fields,
        );
        if ($this->getRecordType() === 'HTMLText') {
            foreach (['Before', 'After'] as $fieldName) {
                $fields->replaceField(
                    $fieldName,
                    HTMLReadonlyField::create($fieldName . 'Nice', $fieldName, $this->dbObject($fieldName)->Raw())
                );
            }
        }
        $fields->removeByName('RecordID');

        $this->makeFieldsReadonly($fields);
        if ($this->getFindErrorsOnly()) {
            $fields->removeByName('Accepted');
            $fields->removeByName('Rejected');
            $fields->removeByName('OriginalUpdated');
        } else {
            $fields->removeByName('ErrorFound');
        }
        if ($this->IsTest) {
            $fields->removeByName('Skip');
            $fields->removeByName('Accepted');
            $fields->removeByName('Rejected');
            $fields->removeByName('OriginalUpdated');
        }
        $record = $this->getRecord();
        if ($record) {
            if ($record->hasMethod('CMSEditLink')) {
                $link = $record->CMSEditLink();
            } elseif ($record->hasMethod('Link')) {
                $link = $record->Link();
            } else {
                $link = null;
            }
            if ($link) {
                $title = '<a href="' . $link . '" target="_blank">' . $this->getRecordTitle() . '</a>';
            } else {
                $title = $this->getRecordTitle();
            }
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    $fields->dataFieldByName('InstructionID'),
                    HTMLReadonlyField::create(
                        'ViewRecord',
                        'Record',
                        $title

                    ),
                ],
                'BeforeNice'
            );
        }
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

    public function getTitle(): string
    {
        return $this->getRecordTitle();
    }


    public function getBeforeHumanValue(): string
    {
        return $this->getHumanValue($this->Before);
    }

    public function getAfterHumanValue(): string
    {
        return $this->getHumanValue($this->After);
    }

    public function getIsErrorAnswer(?string $answer): bool
    {
        if (! $answer) {
            $answer = $this->Answer;
        }
        if ($answer) {
            $prependNonError = Config::inst()->get(Instruction::class, 'non_error_prepend');
            if ($answer === $prependNonError) {
                return false;
            }
            $prependError = Config::inst()->get(Instruction::class, 'error_prepend');
            if (strpos($answer, $prependError) === 0) {
                return true;
            }
        }
        return false;
    }

    public function getHumanValue(mixed $value): string
    {
        $type = $this->getRecordType();
        switch ($type) {
            case 'Int':
            case 'Float':
                return (string) $value;
            case 'Percentage':
                $value = (float) $value;
                return round($value * 100, 2) . '%';
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
