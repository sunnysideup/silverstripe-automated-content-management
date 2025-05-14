<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\AddCastedVariables\AddCastedVariablesHelper;
use Sunnysideup\AutomatedContentManagement\Admin\AdminInstructions;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;
use Sunnysideup\AutomatedContentManagement\Api\DataObjectUpdateCMSFieldsHelper;
use Sunnysideup\AutomatedContentManagement\Api\InstructionsForInstructions;
use Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\AutomatedContentManagement\Traits\MakeFieldsRoadOnly;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;
use Sunnysideup\OptionsetFieldGrouped\Forms\OptionsetGroupedField;
use Sunnysideup\Selections\Model\Selection;

class Instruction extends DataObject
{

    use MakeFieldsRoadOnly;

    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $singular_name = 'Automated Update Instruction for an LLM (AI)';
    private static $plural_name = 'Automated Update Instructions for an LLM (AI)';
    private static string $error_prepend = 'HAS_ERROR: ';
    private static string $non_error_prepend = 'OK';

    private static array $class_and_field_inclusion_exclusion_schema = [
        'included_field_types' => [
            'Varchar',
            'Text',
            'HTMLText',
            'HTMLVarchar',
            'Boolean',
            'Int',
            'Float',
            'Decimal',
            'Datetime',
            'Date',
            'Time',
        ],
        'grouped' => true,
    ];

    private static $defaults = [
        'NumberOfRecordsToProcessPerBatch' => 100,
    ];

    private static $db = [
        'ClassNameToChange' => 'Varchar(255)',
        'FieldToChange' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'FindErrorsOnly' => 'Boolean',
        'Description' => 'Text',
        'AlwaysAddedInstruction' => 'Text',
        'RecordIdsToAddToSelection' => 'Text',
        'NumberOfRecordsToProcessPerBatch' => 'Int',
        'RunTest' => 'Boolean',
        'ReadyToProcess' => 'Boolean',
        'StartedProcess' => 'Boolean',
        'Completed' => 'Boolean',
        'Cancelled' => 'Boolean',
        'AcceptAll' => 'Boolean',
        'RejectAll' => 'Boolean',
    ];

    private static $has_one = [
        'By' => Member::class,
        'Selection' => Selection::class,
    ];

    private static $has_many = [
        'RecordsToProcess' => RecordProcess::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Created',
        'Title' => 'Title',
        'StartedProcess.Nice' => 'Started',
        'Completed.Nice' => 'Completed',
    ];

    private static $searchable_fields = [
        'Title',
        'FindErrorsOnly',
        'Description',
        'StartedProcess',
        'Completed',
    ];

    private static $field_labels = [
        'ClassNameToChange' => '* Record Type you would like to update',
        'ClassNameToChangeNice' => 'Record Type',
        'FieldToChange' => '* Field to change',
        'FieldToChangeNice' => 'Field to change',
        'Title' => '* Title (internal use only, required)',
        'Description' => '* Instructions for the LLM (required)',
        'RunTest' => 'Run test now',
        'ReadyToProcess' => 'Start process now',
        'Cancelled' => 'Cancel any further processing',
        'FindErrorsOnly' => 'Find errors only - LLM will not update the record, but instead tell you if there are any errors.',
        'RecordsToProcess' => 'Process Log',
    ];

    private static $casting = [
        'IsReadyForProcessing' => 'Boolean',
        'IsReadyForReview' => 'Boolean',
        'ReviewCompleted' => 'Boolean',
        'NumberOfRecords' => 'Int',
        'ProcessedRecords' => 'Int',
        'PercentageCompleted' => 'Percentage',
        'ClassNameToChangeNice' => 'Varchar',
        'FieldToChangeNice' => 'Varchar',
        'RecordType' => 'Varchar',
        'LLMProvidedBy' => 'Varchar',
        'LLMModelUsed' => 'Varchar',
    ];

    private static $cascade_delete = [
        'RecordsToProcess',
    ];


    private static $default_sort = 'ID DESC';

    // public function getCMSCompositeValidator(): CompositeValidator
    // {
    //     if (!$this->HasValidClassName()) {
    //         return RequiredFields::create(
    //             [
    //                 'ClassNameToChange',
    //             ]
    //         );
    //     } elseif (!$this->HasValidFieldName()) {
    //         return RequiredFields::create(
    //             [
    //                 'ClassNameToChange',
    //                 'FieldToChange',
    //             ]
    //         );
    //     } else {
    //         return RequiredFields::create(
    //             [
    //                 'ClassNameToChange',
    //                 'FieldToChange',
    //                 'Title',
    //                 'Description',
    //             ]
    //         );
    //     }
    // }

    public function getCMSFields()
    {

        if (!$this->HasValidClassName()) {
            return FieldList::create(
                $this->getSelectClassNameField()
            );
        } elseif (!$this->HasValidFieldName()) {
            return FieldList::create(
                $this->getSelectClassNameField(),
                $this->getSelectFieldNameField(),
            );
        } else {
            $fields = parent::getCMSFields();
            $fields->removeByName('RecordIdsToAddToSelection');

            $fields->insertBefore(
                'RecordsToProcess',
                Tab::create(
                    'TargetRecords',
                )
            );


            $fields->dataFieldByName('ReadyToProcess')
                ->setDescription(
                    'This will allow the system to start the process of getting data from the large lange model (like ChatGPT). <br />' .
                        'Please note that the process may not start immediately as it runs in the background. <br />' .
                        'Please enter all the required fields (*) first before you can check this box.<br />'
                );
            $fields->dataFieldByName('RunTest')
                ->setDescription(
                    'Checking this option will allow you to run the results for just one randomly selected record. <br />
                    The suggested changes will not be applied. <br />
                    You will need to enter all the required fields first before you can check this box.<br />
                    After you check the box, click save and then check the results in the Records tab. <br />
                    '
                );
            $grids = [
                'Test Only' => RecordProcess::get()
                    ->filter(
                        [
                            'IsTest' => true
                        ]
                    ),

                'Queued' => RecordProcess::get()
                    ->filter(
                        [
                            'Started' => false
                        ]
                    ),
                'Review' => RecordProcess::get()
                    ->filter(
                        [
                            'IsTest' => false,
                            'Completed' => true,
                            'Accepted' => false,
                            'Rejected' => false,

                        ]
                    ),
                'Accepted' => RecordProcess::get()
                    ->filter(
                        [
                            'Accepted' => true
                        ]
                    ),
                'Rejected' => RecordProcess::get()
                    ->filter(
                        [
                            'Rejected' => true
                        ]
                    ),
            ];
            foreach ($grids as $name => $list) {
                $list = $list->filter(['InstructionID' => $this->ID]);
                $fields->addFieldToTab(
                    'Root.ProcessLogByStatus.' . $name,
                    new GridField(
                        'RecordsToProcess' . $name,
                        $name,
                        $list,
                        GridFieldConfig_RecordEditor::create()
                            ->removeComponentsByType(GridFieldAddNewButton::class)
                            ->removeComponentsByType(GridFieldDeleteAction::class)
                    )
                );
            }
            $recordsToProcessTab = $fields->fieldByName('Root.RecordsToProcess');
            if ($recordsToProcessTab) {
                // $recordsToProcessTab->setTitle('Process Details');
            }

            $fields->addFieldsToTab(
                'Root.RecordsToProcess',
                [
                    $fields->dataFieldByName('AcceptAll')
                        ->setDescription(
                            'This will allow you to accept all the changes for all the records in the list.'
                        ),
                    $fields->dataFieldByName('RejectAll')
                        ->setDescription(
                            'This will allow you to accept all the changes for all the records in the list.'
                        ),
                ],
                'RecordsToProcess'

            );
            $fields->dataFieldByName('RecordsToProcess')
                ->setDescription(
                    'This is a list of all the records that are to be processed. <br />' .
                        'You can click on the record to see the details and make changes.'
                )
                ->getConfig()->removeComponentsByType(GridFieldAddNewButton::class)
                ->removeComponentsByType(GridFieldDeleteAction::class)
                ->removeComponentsByType(GridFieldAddExistingAutocompleter::class);

            Injector::inst()->get(AddCastedVariablesHelper::class)->AddCastingFields(
                $this,
                $fields,
            );

            $fields->replaceField(
                'ClassNameToChange',
                $this->getSelectClassNameField()
            );
            $fields->replaceField(
                'FieldToChange',
                $this->getSelectFieldNameField()
            );

            $fields->addFieldToTab(
                'Root.ⓘ',
                $fields->dataFieldByName('ByID')
                    ->setTitle(
                        'User who created this instruction',
                    )
            );

            if (! $this->StartedProcess) {
                $exampleRecord = $this->getRecordExample();
                if ($exampleRecord) {
                    $instructionsCreator = Injector::inst()->create(
                        InstructionsForInstructions::class,
                        $exampleRecord,
                    );
                    $fields->addFieldsToTab(
                        'Root.Main',
                        [
                            ToggleCompositeField::create(
                                'InstructionDetailsHolder',
                                'Variables Available for Instructions',
                                [
                                    LiteralField::create(
                                        'InstructionDetails',
                                        $instructionsCreator->getInstructions()
                                    ),
                                ]
                            )->setHeadingLevel(4)
                        ],
                        'AlwaysAddedInstruction',
                    );
                }
            }
            if ($this->HasRecordIdsToAddToSelection()) {
                $fields->removeByName('SelectionID');
            } else {
                $obj = Injector::inst()->get(Selection::class);
                $source = Selection::get()
                    ->filter(['ModelClassName' => $this->ClassNameToChange]);
                $dropdownField = DropdownField::create(
                    'SelectionID',
                    'Selection for this record type (optional)',
                    $source->map('ID', 'Title')
                )
                    ->setDescription(
                        'You can <a href="' . $obj->CMSAddLink() . '">create a new selection</a>  or chose an existing one for your selected record type.'
                    )
                    ->setEmptyString('-- use all records --');
                $fields->addFieldsToTab(
                    'Root.TargetRecords',
                    [
                        $dropdownField,
                    ],
                );
            }
            $fields->addFieldsToTab(
                'Root.TargetRecords',
                [
                    GridField::create(
                        'TargetRecordSelection',
                        'Target Records',
                        $this->getRecordList(),
                        GridFieldConfig_RecordViewer::create()
                    )
                ],
            );
            $this->makeFieldsReadonly($fields);
            return $fields;
        }
    }


    protected function makeFieldsReadonlyInner(string $fieldName): bool
    {
        // everyting readonly
        if ($this->getReviewCompleted()) {
            return true;
        }
        // everyting readonly
        if ($this->Cancelled) {
            return true;
        }
        // always readonly
        switch ($fieldName) {
            case 'ClassNameToChange':
            case 'FieldToChange':
            case 'Created':
            case 'LastEdited':
            case 'StartedProcess':
            case 'RecordIdsToAddToSelection':
            case 'Completed':
            case 'ByID':
                return true;
            default:
                break;
        }
        if ($this->getIsReadyForProcessing() !== true) {
            switch ($fieldName) {
                case 'ReadyToProcess':
                case 'RunTest':
                    return true;
                default:
                    break;
            }
        }
        if ($this->StartedProcess) {
            switch ($fieldName) {
                case 'Title':
                case 'Description':
                case 'AlwaysAddedInstruction':
                case 'NumberOfRecordsToProcessPerBatch':
                case 'FindErrorsOnly':
                    return true;
                default:
                    break;
            }
        }
        if ($this->getIsReadyForReview() !== true) {
            switch ($fieldName) {
                case 'AcceptAll':
                case 'RejectAll':
                    return true;
                default:
                    break;
            }
        }
        return false;
    }

    public function HasValidClassName(): bool
    {
        $className = $this->ClassNameToChange;
        if ($className && class_exists($className)) {
            return true;
        }
        return false;
    }

    public function HasValidFieldName(): bool
    {
        $fieldName = $this->FieldToChange;
        $obj = $this->getRecordSingleton();
        if (! $obj) {
            return false;
        }
        $db = $obj->config()->get('db');
        return isset($db[$fieldName]);
    }

    public function getIsReadyForProcessing(): bool
    {
        if ($this->Completed) {
            return false;
        }
        if (! $this->HasValidClassName()) {
            return false;
        }
        if (!$this->getRecordType()) {
            return false;
        }
        if (! $this->Title) {
            return false;
        }
        if (! $this->Description) {
            return false;
        }
        // cam still process...
        // if ($this->StartedProcess) {
        //     return false;
        // }
        return true;
    }



    public function getIsReadyForReview(): bool
    {
        return (bool) $this->Completed;
    }

    public function getReviewCompleted(): bool
    {
        if ($this->Cancelled) {
            return true;
        }
        $allReviewsDone = $this->RecordsToProcess()
            ->filter(['Accepted' => false, 'Rejected' => false])
            ->count() === 0;
        return ($this->Completed && $allReviewsDone) ? true : false;
    }

    public function getNumberOfRecords(): int
    {
        if ($this->HasValidClassName()) {
            return $this->getRecordList()?->count() ?? 0;
        }
        return 0;
    }

    public function getProcessedRecords(): int
    {
        return $this->RecordsToProcess()->filter(['Completed' => true, 'IsTest' => false])->count();
    }

    public function getPercentageCompleted(): float
    {
        if ($this->getNumberOfRecords() === 0) {
            return 0;
        }
        return round(($this->getProcessedRecords() / $this->getNumberOfRecords()) * 100) / 100;
    }

    public function getClassNameToChangeNice(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            return $obj->i18n_singular_name();
        }
        return 'ERROR: Class not found';
    }

    public function getClassNameToChangePluralNice(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            return $obj->i18n_plural_name();
        }
        return 'ERROR: Class not found';
    }

    public function getFieldToChangeNice(): string
    {
        $fieldName = $this->FieldToChange;
        if ($fieldName) {
            $obj = $this->getRecordSingleton();
            if ($obj) {
                return $obj->fieldLabel($fieldName);
            }
        }
        return 'ERROR: field not found';
    }

    public function getRecordType(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            $db = $obj->config()->get('db');
            $type = $db[$this->FieldToChange] ?? 'Error: Field does not exist';
            return ClassAndFieldInfo::standard_short_field_type_name($type);
        }
        return 'Error: Class does not exist';
    }

    public function getLLMProvidedBy(): string
    {
        return ConnectorBaseClass::inst()->getClientNameNice();
    }

    public function getLLMModelUsed(): string
    {
        return ConnectorBaseClass::inst()->getModelNice();
    }

    public function getRecordSingleton()
    {
        if ($this->HasValidClassName()) {
            return Injector::inst()->get($this->ClassNameToChange);
        }
        return null;
    }

    public function getRecordExample()
    {
        if ($this->HasValidClassName()) {
            return $this->getRecordList()?->orderBy(DB::get_conn()->random())->first() ?: null;
        }
    }



    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->ByID) {
            $this->ByID = Security::getCurrentUser()?->ID;
        }
        if (! $this->Completed && $this->StartedProcess) {
            if ($this->getNumberOfRecords() === $this->getProcessedRecords()) {
                $this->Completed = true;
            }
        }

        if ($this->ReadyToProcess) {
            $this->RunTest = false;
        }
        if ($this->RunTest) {
            $this->ReadyToProcess = false;
        }
        if (! $this->Title && $this->HasValidClassName() && $this->HasValidFieldName()) {
            $this->Title = 'Update ' . $this->getFieldToChangeNice() . ' fields in ' . $this->getClassNameToChangePluralNice() . ' records';
        }
        if ($this->HasValidClassName() && $this->HasValidFieldName()) {
            if (!$this->AlwaysAddedInstruction || $this->isChanged('FindErrorsOnly')) {
                if ($this->FindErrorsOnly) {
                    $this->AlwaysAddedInstruction = $this->getFindErrorsOnlyInstruction();
                } else {
                    $this->AlwaysAddedInstruction = $this->getUpdateInstruction();
                }
            }
        }
        if ($this->NumberOfRecordsToProcessPerBatch > 1000 || $this->NumberOfRecordsToProcessPerBatch < 1) {
            $this->NumberOfRecordsToProcessPerBatch = $this->Config()->get('defaults')['NumberOfRecordsToProcessPerBatch'] ?? 100;
        }
    }

    protected function getFindErrorsOnlyInstruction(): string
    {
        return $this->cleanWhitespace(
            '
            If you find an error, then please prepend any answer with ' . $this->Config()->get('error_prepend') . '.
            If no error is found as per the instructions above then just return ' . $this->Config()->get('non_error_prepend')
        );
    }

    protected function getUpdateInstruction(): string
    {
        return $this->cleanWhitespace(
            'Please return the answer as a value suitable for insertion into a
            ' . $this->getRecordType() . ' field type in a Silverstripe CMS Database.
            For example, if the field is a Varchar field, then please return a string.
            For HTMLText, please make sure all text is wrapped in any of the following tags: p, ul, ol, li, or h2 - h6
            also make sure that all HTML is valid.
            Never wrap returns in things like ```html.'
        );
    }

    protected function cleanWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->RunTest) {
            $item = $this->AddRecords(true);
            if ($item) {
                $obj = Injector::inst()->get(ProcessOneRecord::class);
                $obj->recordAnswer($item);
            }
            $this->RunTest = false;
            $this->write();
        } elseif ($this->ReadyToProcess && ! $this->Completed) {
            $this->AddRecords(false);
        }
        if ($this->RejectAll) {
            $this->RecordsToProcess()->filter(['Rejected' => false])->each(function ($item) {
                $item->RejectAll = true;
                $item->write();
            });
        }
        if ($this->AcceptAll) {
            $this->RecordsToProcess()->filter(['Accepted' => false])->each(function ($item) {
                $item->AcceptAll = true;
                $item->write();
            });
        }
    }


    public function canEdit($member = null)
    {
        if ($this->Cancelled || $this->getReviewCompleted()) {
            return false;
        }
        return parent::canEdit($member);
    }



    public function canDelete($member = null)
    {
        if ($this->StartedProcess) {
            return false;
        }
        return parent::canDelete($member);
    }

    protected function AddRecords(?bool $isTest = false, array|string|null $filter = null, ?int $limit = null): ?RecordProcess
    {
        if ($this->HasValidClassName()) {
            $list = $this->getRecordList();
            if ($filter) {
                if (is_array($filter)) {
                    $list = $list->filter($filter);
                } else {
                    $list = $list->where($filter);
                }
            }
            if ($limit) {
                $list = $list->limit($limit);
            }
            if ($isTest) {
                $list = $list->orderBy(DB::get_conn()->random())->limit(1);
            }
            $ids = $list->columnUnique('ID');
            if (empty($ids)) {
                return null;
            }
            foreach ($ids as $id) {
                $keyFields = [
                    'InstructionID' => $this->ID,
                    'IsTest' => $isTest,
                    'RecordID' => $id,
                ];
                if ($isTest) {
                    $keyFields['Skip'] = false;
                }
                $recordProcess = RecordProcess::get()->filter($keyFields)->first();
                if (! $recordProcess || $isTest) {
                    $recordProcess = RecordProcess::create($keyFields);
                }
                $recordProcess->write();
            }
            if ($isTest) {
                return $recordProcess;
            }
        }
        return null;
    }


    protected function getSelectClassNameField(): OptionsetGroupedField|ReadonlyField
    {
        if ($this->HasValidClassName()) {
            $field = ReadonlyField::create(
                'ClassNameToChangeNice',
                $this->fieldLabel('ClassNameToChange'),
                $this->getClassNameToChangeNice()
            );
        } else {
            $field = OptionsetGroupedField::create(
                'ClassNameToChange',
                $this->fieldLabel('ClassNameToChange'),
                Injector::inst()->get(ClassAndFieldInfo::class)->getListOfClasses(
                    array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => true]),

                )
            )->setDescription(
                '
                    Please select the record type you want to change.
                    This will be used to create a list of records to process.
                    Once selected, please save the record to continue.
                '
            );
        }
        return $field;
    }



    protected function getSelectFieldNameField(): OptionsetGroupedField|ReadonlyField
    {
        if ($this->HasValidFieldName()) {
            $field = ReadonlyField::create(
                'FieldToChangeNice',
                $this->fieldLabel('FieldToChange'),
                $this->getFieldToChangeNice()
            );
        } else {
            $field = OptionsetGroupedField::create(
                'FieldToChange',
                $this->fieldLabel('FieldToChange'),
                Injector::inst()->get(ClassAndFieldInfo::class)->getListOfFieldNames(
                    $this->ClassNameToChange,
                    ['db'],
                    array_replace($this->Config()->get('class_and_field_inclusion_exclusion_schema'), ['grouped' => true]),
                )
            )->setDescription(
                '
                    Please select the field you would like to change.
                '
            );
        }
        return $field;
    }

    public function getRecordList(): ?DataList
    {
        $className = $this->ClassNameToChange;
        if ($this->HasRecordIdsToAddToSelection()) {
            $ids = explode(',', (string) $this->RecordIdsToAddToSelection);
            return $className::get()->filter(['ID' => $ids]);
        }
        if ($this->SelectionID) {
            $selection = $this->Selection();
            if ($selection && $selection->exists()) {
                $list = $selection->getSelectionDataList();
                if ($list) {
                    return $list;
                }
            }
        }
        if ($this->HasValidClassName()) {
            return $className::get();
        }
        return null;
    }

    protected function HasRecordIdsToAddToSelection(): bool
    {
        return (bool) (trim((string) $this->RecordIdsToAddToSelection) === '' ? false : true);
    }

    public function AddRecordsToInstruction(int|array $recordId)
    {
        if (! is_array($recordId)) {
            $recordId = [(int) $recordId];
        }
        $existingList = $this->getRecordList();
        $allPresent = false;
        foreach ($recordId as $id) {
            if (!$existingList->byID($id)) {
                $allPresent = false;
                break;
            }
        }
        if ($allPresent) {
            return;
        }
        $ids = explode(',', (string) $this->RecordIdsToAddToSelection);
        $ids = array_merge($ids, $recordId);
        $ids = array_unique($ids);
        $ids = array_filter($ids);
        $this->RecordIdsToAddToSelection = trim(trim(implode(',', $ids)), ',');
        $this->write();
    }


    public function getSelectExistingLLMInstructionForOneRecordLink($record): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('selectexistinginstructionforonerecord', $this->ID, $record->ID);
    }

    public function getSelectExistingLLMInstructionForOneRecordOneFieldLink($record, string $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('selectexistinginstructionforonerecordonefield', $this->ID, $record->ID, $fieldName);
    }

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(AdminInstructions::class)->getCMSEditLinkForManagedDataObject($this);
    }
}
