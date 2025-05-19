<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\View\SSViewer_FromString;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\AutomatedContentManagement\Admin\AdminInstructions;
use Sunnysideup\AutomatedContentManagement\Api\DataObjectUpdateCMSFieldsHelper;
use Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Traits\MakeFieldsRoadOnly;

class RecordProcess extends DataObject
{
    use MakeFieldsRoadOnly;

    private static $table_name = 'AutomatedContentManagementRecordProcess';

    private static $singular_name = 'Process Log for an LLM (AI) Instruction';

    private static $plural_name = 'Process Logs for an LLM (AI) Instruction';

    private static $db = [
        'RecordID' => 'Int',
        'Before' => 'Text',
        'After' => 'Text',
        'IsTest' => 'Boolean',
        'ErrorFound' => 'Boolean',
        'Skip' => 'Boolean',
        'Started' => 'Boolean',
        'Question' => 'Text',
        'Completed' => 'Boolean',
        'Accepted' => 'Boolean',
        'Rejected' => 'Boolean',
        'OriginalUpdated' => 'Boolean',
        'LLMClient' => 'Varchar(40)',
        'LLMModel' => 'Varchar(40)',
    ];

    private static $has_one = [
        'Instruction' => Instruction::class,
    ];

    private static $summary_fields = [
        'LastEdited.Ago' => 'Last Updated',
        'Instruction.Title' => 'Action',
        'RecordTitle' => 'Record',
        'IsTest.Nice' => 'Test Only',
        'Completed.Nice' => 'Completed Processing',
        'ResultPreviewLinkHTML' => 'Preview',
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
        'CanBeReviewed' => 'Boolean',
        'AcceptedOrRejected' => 'Boolean',
        'CanUpdateOriginalRecord' => 'Boolean',
        'RecordTitle' => 'Varchar',
        'RecordLink' => 'Varchar',
        'HydratedInstructions' => 'Text',
        'ResultPreviewLinkHTML' => 'HTMLText',
        'BeforeHumanValue' => 'Text',
        'AfterHumanValue' => 'Text',
        'BeforeDatabaseValueForInspection' => 'Text',
        'AfterDatabaseValueForInspection' => 'Text',
        'Status' => 'Varchar',
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
        'HydratedInstructions' => 'Question',
    ];

    private static $default_sort = 'ID DESC';

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

    public function getCanBeReviewed(): bool
    {
        $instruction = $this->Instruction();
        return
            ! $instruction->Cancelled &&
            $this->Completed &&
            ! $this->Accepted &&
            ! $this->Rejected &&
            ! $this->Skip;
    }

    public function getCanUpdateOriginalRecord(): bool
    {
        $instruction = $this->Instruction();
        return
            ! $instruction->Cancelled &&
            ! $instruction->FindErrorsOnly &&
            $this->Accepted &&
            ! $this->Skip &&
            ! $this->OriginalUpdated;
    }

    public function getAcceptedOrRejected(): bool
    {
        $instruction = $this->Instruction();
        return
            $instruction->Cancelled ||
            $this->Accepted ||
            $this->Rejected ||
            $this->OriginalUpdated ||
            $this->Skip;
    }

    public function getRecordTitle()
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->getTitle(); //. ' (ID: #' . $record->ID . ')';
        }
        return 'Error: record not found'; // (ID: #' . $this->RecordID . ')';
    }

    public function getRecordLink(): string|null
    {
        $record = $this->getRecord();
        if ($record->hasMethod('CMSEditLink')) {
            $link = $record->CMSEditLink();
        } elseif ($record->hasMethod('Link')) {
            $link = $record->Link();
        } else {
            $link = null;
        }
        return $link;
    }

    public function getHydratedInstructions(): string
    {
        if ($this->Question) {
            return $this->Question;
        }
        $description = $this->Instruction()->Description;
        $record = $this->getRecord();
        $value =  '';
        if ($record) {
            $template = SSViewer_FromString::create($description);
            //FUTURE: SSViewer::create()->renderString($description);
            $return = $template->process($record);
            if ($return instanceof DBField) {
                $value =  $return->forTemplate();
            } else {
                $value =  $return;
            }
        }
        $add = $this->getAlwaysAddedInstruction();
        if ($add) {
            $v .= PHP_EOL . PHP_EOL . $add;
        }
        return $v;
    }


    public function getResultPreviewLinkHTML(): DBHTMLText
    {
        if ($this->getCanProcess()) {
            $value =  'Not processed yet';
        } else if ($this->getCanBeReviewed()) {
            $value =  '<a href="' . $this->getResultPreviewLink() . '" target="_blank">Review Suggestion</a>';
        } else {
            $value =  '<a href="' . $this->getResultPreviewLink() . '" target="_blank">View Review Outcome</a>';
        }
        return DBHTMLText::create_field('HTMLText', $v);
    }

    /**
     *
     * @return DataObject|null
     */
    public function getRecord()
    {
        $list = $this->Instruction()->getRecordList();
        if ($list && $this->RecordID) {
            return $list->byID($this->RecordID);
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
        $fields->removeByName(['Question', 'InstructionID', 'RecordID']);
        Injector::inst()->get(AddCastedVariablesHelper::class)->AddCastingFields(
            $this,
            $fields,
        );
        $beforeFieldName = 'Before';
        if ($this->getRecordType() === 'HTMLText') {
            foreach (['Before', 'After'] as $fieldName) {
                $fields->replaceField(
                    $fieldName,
                    HTMLReadonlyField::create($fieldName . 'Nice', $fieldName, $this->dbObject($fieldName)->Raw())
                );
                $beforeFieldName = 'BeforeNice';
            }
        }

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
            $link = $this->getRecordLink();
            if ($link) {
                $title = '<a href="' . $link . '">' . $this->getRecordTitle() . '</a>';
            } else {
                $title = $this->getRecordTitle();
            }
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    HTMLReadonlyField::create(
                        'InstructionLink',
                        'LLM (AI) Instruction',
                        '<a href="' . $this->Instruction()->CMSEditLink() . '">' . $this->Instruction()->Title . '</a>'

                    ),
                    HTMLReadonlyField::create(
                        'ViewRecord',
                        'Record Targetted',
                        $title

                    ),
                    HTMLReadonlyField::create(
                        'ResultPreviewLinkHTML',
                        'Compare Before / After',
                        $this->getResultPreviewLinkHTML()
                    ),

                ],
                $beforeFieldName
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
            case 'LLMClient':
            case 'LLMModel':
            case 'Question':
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
        return 'Updating ' . $this->getRecordTitle() . ' using ' . $this->Instruction()->Title;
    }


    public function getBeforeHumanValue(): string
    {
        return $this->getHumanValue($this->Before);
    }

    public function getAfterHumanValue(): string
    {
        return $this->getHumanValue($this->After);
    }

    public function getBeforeDatabaseValueForInspection(): string
    {
        return $this->makeDatabaseValueVisible($this->getBeforeDatabaseValue());
    }

    public function getAfterDatabaseValueForInspection(): string
    {
        return $this->makeDatabaseValueVisible($this->getAfterDatabaseValue());
    }

    public function getStatus(): string
    {
        $a = [];
        if ($this->IsTest) {
            $a[] = 'Is a Test Only';
        }
        if ($this->OriginalUpdated) {
            $a[] = 'Updated';
        } elseif ($this->Accepted) {
            $a[] = 'Result Accepted';
        } elseif ($this->Rejected) {
            $a[] = 'Result Rejected';
        } elseif ($this->Completed) {
            $a[] = 'Question Completed';
        } elseif ($this->Started) {
            $a[] = 'Started';
        } else {
            $a[] = 'Processing not started yet';
        }
        return implode(', ', $a);
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
            case 'HTMLText':
            case 'HTMLVarchar':
            case 'HTML':
                break;
            case 'Varchar':
            case 'Text':
                $value =  (string) strip_tags((string) $value);
                break;
            case 'Int':
                $value = strip_tags((string) $value);
                $value =  (int) $value;
                break;
            case 'Float':
            case 'Currency':
                $value = strip_tags((string) $value);
                $value =  (float) $value;
                break;
            case 'Boolean':
                if ($value === 1 || $value === true) {
                    $value = true;
                } else {
                    $value = strtolower(strip_tags((string) $value));
                    if ($value === 'true' || $value === '1' || $value === 'yes' || $value === 'on') {
                        $value =  true;
                    } else {
                        $value =  false;
                    }
                }
                break;
            case 'Date':
                $value = strip_tags((string) $value);
                $value =  date('Y-m-d', strtotime($value));
                break;
            case 'Datetime':
                $value = strip_tags((string) $value);
                $value =  date('Y-m-d H:i:s', strtotime($value));
                break;
            default:
                $value = strip_tags((string) $value);
                $value =  (string) $value;
        }
        return $value;
    }

    public function getRecordType(): ?string
    {
        return $this->Instruction()?->getRecordType();
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_LLMEDITOR', 'any', $member);
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

    protected function makeDatabaseValueVisible(mixed $value): string
    {
        if ($value === null) {
            return '[NULL]';
        }
        if ($value === '') {
            return '[EMPTY]';
        }
        if ($value === false) {
            return '[FALSE]';
        }
        if ($value === true) {
            return '[TRUE]';
        }
        if (is_array($value)) {
            return '[ARRAY]';
        }
        if (is_object($value)) {
            return '[OBJECT]';
        }
        return '<textarea readonly rows="20">' . $value . '</textarea>';
    }

    public function getResultPreviewLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('preview', $this->InstructionID, $this->ID);
    }

    public function getAcceptLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('acceptresult', $this->InstructionID, $this->ID);
    }
    public function getRejectLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('rejectresult', $this->InstructionID, $this->ID);
    }
    public function getAcceptAndUpdateLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('acceptresultandupdate', $this->InstructionID, $this->ID);
    }


    public function CMSEditLink(): string
    {
        return Injector::inst()->get(AdminInstructions::class)->getCMSEditLinkForManagedDataObject($this);
    }

    public function AcceptResult()
    {
        $this->Accepted = true;
        $this->Rejected = false;
        $this->write();
    }

    public function UpdateRecord()
    {
        $obj = Injector::inst()->get(ProcessOneRecord::class);
        $obj->updateOriginalRecord($this);
    }

    public function DeclineResult()
    {
        $this->Accepted = false;
        $this->Rejected = true;
        $this->write();
    }
}
