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
        'IsTest.NiceAndColourfullInvertedColours' => 'Test Only',
        'Completed.NiceAndColourfull' => 'Completed Processing',
        'ResultPreviewLinkHTML' => 'Preview',
        'Accepted.NiceAndColourfull' => 'Change Accepted',
        'OriginalUpdated.NiceAndColourfull' => 'Originating Record Updated',
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
        'FieldToChange' => 'Varchar',
        'FindErrorsOnly' => 'Boolean',
        'CanProcess' => 'Boolean',
        'CanNotProcessAnymore' => 'Boolean',
        'CanBeReviewed' => 'Boolean',
        'IsAcceptedOrRejected' => 'Boolean',
        'CanUpdateOriginalRecord' => 'Boolean',
        'RecordTitle' => 'Varchar',
        'RecordLink' => 'Varchar',
        'RecordClassName' => 'Varchar',
        'RecordIDNice' => 'Varchar',
        'HydratedInstructions' => 'Text',
        'ResultPreviewLinkHTML' => 'HTMLText',
        'BeforeHumanValue' => 'Text',
        'BeforeHTMLValue' => 'HTMLText',
        'AfterHumanValue' => 'Text',
        'AfterHTMLValue' => 'HTMLText',
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

    private static $indexes = [
        'RecordID' => true,
        'InstructionID' => true,
        'IsTest' => true,
        'ErrorFound' => true,
        'Skip' => true,
        'Started' => true,
        'Completed' => true,
        'Accepted' => true,
        'Rejected' => true,
        'OriginalUpdated' => true,
    ];

    private static $default_sort = 'ID DESC';

    public function getFieldToChange(): string
    {
        return (string) $this->Instruction()->FieldToChange;
    }

    public function getFindErrorsOnly(): bool
    {
        return (bool) $this->Instruction()->FindErrorsOnly;
    }



    public function IsObsolete(): bool
    {
        $instruction = $this->Instruction();
        return $instruction->Cancelled || $this->Skip;
    }

    public function getCanProcess(): bool
    {
        if ($this->IsObsolete()) {
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
        return $this->Completed || $this->IsObsolete();
    }


    public function getCanBeReviewed(): bool
    {
        if ($this->IsObsolete()) {
            return false;
        }
        return
            $this->Completed &&
            ! $this->Accepted &&
            ! $this->Rejected &&
            ! $this->OriginalUpdated;
    }


    public function IsReadyForReview(): bool
    {
        return $this->getCanBeReviewed();
    }



    public function getCanUpdateOriginalRecord(): bool
    {
        if ($this->IsObsolete()) {
            return false;
        }
        return
            $this->Accepted &&
            ! $this->getFindErrorsOnly() &&
            ! $this->OriginalUpdated;
    }

    public function getIsAcceptedOrRejected(): bool
    {
        if ($this->IsObsolete()) {
            return false;
        }
        return
            $this->Accepted ||
            $this->Rejected ||
            $this->OriginalUpdated;
    }

    public function HasOriginalUpdated(): bool
    {
        if ($this->IsObsolete()) {
            return false;
        }
        return  $this->OriginalUpdated;
    }

    public function getRecordTitle(): string
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->getTitle(); //. ' (ID: #' . $record->ID . ')';
        }
        return 'Error: record not found'; // (ID: #' . $this->RecordID . ')';
    }


    public function getRecordIDNice(): string|null
    {
        return $this->RecordID ? '#' . $this->RecordID : null;
    }


    public function getRecordClassName(): string|null
    {
        // CAREFULL!!!! Can not call getRecord here otherwise you end up in a an endless loop!
        return $this->Instruction()?->ClassNameToChange ?: null;
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
            $value .= PHP_EOL . PHP_EOL . $add;
        }
        return $value;
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


    public function Link()
    {
        return $this->getResultPreviewLink();
    }

    public function getRecordLink(): string|null
    {
        return $this->getRecordLinkEdit() ?: $this->getRecordLinkView();
    }

    public function getRecordLinkEdit(): string|null
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->hasMethod('CMSEditLink') ? $record->CMSEditLink() : null;
        }
        return $this->getResultPreviewLink();
    }

    public function getRecordLinkView(): string|null
    {
        $record = $this->getRecord();
        if ($record) {
            return $record->hasMethod('Link') ? $record->Link() . '?previewllm=1' : null;
        }
        return $this->getResultPreviewLink();;
    }

    public function getRunExistingRecordProcessNowForOneFieldLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder(
            'runexistingrecordprocessnowforonefield',
            $this->InstructionID,
            $this->ID,
            $this->getFieldToChange()
        );
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
        return DBHTMLText::create_field('HTMLText', $value);
    }

    protected static $recordCache = [];

    /**
     *
     * @return DataObject|null
     */
    public function getRecord()
    {
        if (! $this->RecordID) {
            return null;
        }
        if (isset(self::$recordCache[$this->ID])) {
            return self::$recordCache[$this->ID];
        }
        $list = $this->Instruction()->getRecordList();
        $obj = null;
        if ($list) {
            $obj = $list->byID($this->RecordID);
        }
        if (! $obj) {
            $className = $this->getRecordClassName();
            if ($className && class_exists($className)) {
                $obj = $className::get()->byID($this->RecordID);
            }
        }
        if ($obj && $obj instanceof RecordProcess) {
            return null;
        }
        self::$recordCache[$this->ID] = $obj;
        return $obj;
    }

    public function getAlwaysAddedInstruction(): string
    {
        return (string) $this->Instruction()?->AlwaysAddedInstruction ?: '';
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
        $fields->addFieldsToTab(
            'Root.RunNow',
            [

                HTMLReadonlyField::create(
                    'RunLinkNice',
                    'Run Now',
                    '<a href="' . $this->getRunLink() . '" target="_blank">Run any LLM Processing now - please use with care</a>'
                ),
            ]
        );
        return $fields;
    }

    public function getRunLink(): string
    {
        return '/dev/tasks/acm-process-instructions/?recordprocess=' . $this->ID;
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
        return $this->getRecordTitle() . ' (process: ' . $this->Instruction()->Title . ')';
    }

    public function ShortenedAnswer(int $length = 300): string
    {
        $answer = strip_tags((string) $this->getAfterHumanValue());
        if (strlen((string) $answer) > $length) {
            $answer = substr((string) $answer, 0, $length) . '...';
        }
        return $answer;
    }


    public function getBeforeHumanValue(): string
    {
        return $this->getHumanValue($this->Before);
    }

    public function getBeforeHTMLValue(): DBHTMLText
    {
        return DBHTMLText::create_field('HTMLText', $this->Before);
    }

    public function getAfterHumanValue(): string
    {
        return $this->getHumanValue($this->After);
    }

    public function getAfterHTMLValue(): DBHTMLText
    {
        return DBHTMLText::create_field('HTMLText', $this->After);
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
        if ($this->Skip) {
            $a[] = 'Record Skipped';
        } else {
            if ($this->OriginalUpdated) {
                $a[] = 'Original Record Updated';
            } elseif ($this->Accepted) {
                $a[] = 'Result Accepted';
            } elseif ($this->Rejected) {
                $a[] = 'Result Rejected';
            } elseif ($this->Completed) {
                $a[] = 'Question Answered - ready for review';
            } elseif ($this->Started) {
                $a[] = 'Processing Started';
            } else {
                $a[] = 'Processing not started yet';
            }
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
            case 'Varchar':
            case 'Text':
                return strip_tags((string) $value);
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
                $value = trim((string) $value);
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

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(AdminInstructions::class)->getCMSEditLinkForManagedDataObject($this);
    }

    public function AcceptResult()
    {
        if ($this->getCanBeReviewed()) {
            $this->Accepted = true;
            $this->Rejected = false;
            $this->write();
        }
    }

    public function RejectResult()
    {
        if ($this->getCanBeReviewed()) {
            $this->Accepted = false;
            $this->Rejected = true;
            $this->write();
        }
    }

    public function IsHTML()
    {
        $type = $this->getRecordType();
        return in_array($type, ['HTMLText', 'HTMLVarchar', 'HTML'], true);
    }



    public function UpdateOriginalRecord()
    {
        $obj = Injector::inst()->get(ProcessOneRecord::class);
        $obj->updateOriginalRecord($this);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->isChanged('After')) {
            $this->After = trim((string) $this->After);
        }
    }
}
