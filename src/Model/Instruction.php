<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use PhpParser\Node\Stmt\ElseIf_;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\CompositeValidator;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\AutomatedContentManagement\Model\Api\ConnectorBaseClass;
use Sunnysideup\AutomatedContentManagement\Model\Api\Converters;
use Sunnysideup\AutomatedContentManagement\Model\Api\InstructionsForInstructions;
use Sunnysideup\AutomatedContentManagement\Model\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\AutomatedContentManagement\Traits\CMSFieldsExtras;

class Instruction extends DataObject
{


    use CMSFieldsExtras;
    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $singular_name = 'LLM Instruction';
    private static $plural_name = 'LLM Instructions';
    private static string $error_prepend = 'HAS_ERROR: ';
    private static string $non_error_prepend = 'OK';
    private static array $excluded_models = [
        'SilverStripe\\Versioned\\ChangeSetItem',
        'DNADesign\\Elemental\\Models\\BaseElement',
    ];

    private static array $included_models = [];

    private static array $excluded_fields = [];

    private static array $included_fields = [];

    private static array $excluded_field_types = [];

    private static array $included_field_types = [];

    private static $excluded_class_field_combos = [
        // 'SilverStripe\\Versioned\\ChangeSetItem' => [
        //     'ClassName',
        // ],
    ];

    private static $included_class_field_combos = [
        // 'SilverStripe\\Versioned\\ChangeSetItem' => [
        //     'ClassName',
        // ],
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
        'FieldToChange' => '* Field to change',
        'Title' => '* Title (internal use only, required)',
        'Description' => '* Instructions for the LLM (required)',
        'RunTest' => 'Run test now',
        'ReadyToProcess' => 'Start process now',
        'Cancelled' => 'Cancel any further processing',
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
                $this->getSelectClassNameField(true)
            );
        } elseif (!$this->HasValidFieldName()) {
            return FieldList::create(
                $this->getSelectClassNameField(false, true),
                $this->getSelectFieldNameField(true)
            );
        } else {
            $fields = parent::getCMSFields();
            $fields->addFieldToTab(
                'Root',
                Tab::create('Details'),
                'RecordsToProcess'
            );
            $fields->addFieldToTab(
                'Root.Details',
                $fields->dataFieldByName('ByID')
            );
            $this->addCastingFieldsNow($fields);

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

            $fields->dataFieldByName('ReadyToProcess')
                ->setDescription(
                    'This will allow start the process of getting data from the large lange model (like ChatGPT). <br />' .
                        'Please note that the process may not start immediately. <br />' .
                        'You can only check this box once all the required data-entry has been completed.'
                );
            $fields->dataFieldByName('RunTest')
                ->setDescription(
                    'Checking this option will allow you to run the results for just one (random) record without applying any of the suggested changes.'
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
                    'Root.RecordsByStatus.' . $name,
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
                $recordsToProcessTab->setTitle('Records');
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

    protected function HasValidClassName(): bool
    {
        $className = $this->ClassNameToChange;
        if ($className && class_exists($className)) {
            return true;
        }
        return false;
    }

    protected function HasValidFieldName(): bool
    {
        $fieldName = $this->FieldToChange;
        $obj = $this->getRecordSingleton();
        if (! $obj) {
            return false;
        }
        $db = $obj->config()->get('db');
        if (isset($db[$fieldName])) {
            return true;
        }
        return false;
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
        $className = $this->ClassNameToChange;
        $fieldName = $this->FieldToChange;
        if ($className && $fieldName) {
            return $className::get()->count();
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
            return Converters::standardised_field_type($type);
        }
        return 'Error: Class does not exist';
    }

    public function getLLMProvidedBy()
    {
        return ConnectorBaseClass::inst()->getClientNameNice();
    }

    public function getLLMModelUsed()
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
            $className = $this->ClassNameToChange;
            return $className::get()->first();
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
            For HTMLText, please make sure all text is wrapped in any of the following tags: p, ul, ol, li, or h2 - h6 and make sure
            that all HTML is valid.'
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
        } elseif ($this->ReadyToProcess) {
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
        $className = $this->ClassNameToChange;
        $list = $className::get();
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
            ];
            if ($isTest) {
                $keyFields['Skip'] = false;
            } else {
                $keyFields['RecordID'] = $id;
            }
            $recordProcess = RecordProcess::get()->filter($keyFields)->first();
            if ($isTest) {
                $recordProcess->delete();
                $recordProcess = null;
            }
            if (! $recordProcess) {
                if ($isTest) {
                    // now we can add the record
                    $keyFields['RecordID'] = $id;
                }
                $recordProcess = RecordProcess::create($keyFields);
            }
            $recordProcess->write();
        }
        if ($isTest) {
            return $recordProcess;
        }
        return null;
    }

    protected static array $listOfClasses = [];

    protected function getListOfClasses(): array
    {
        if (empty(self::$listOfClasses)) {
            $otherList = [];
            $pageList = [];
            $classes = ClassInfo::subclassesFor(DataObject::class, false);
            $excludedModels = $this->config()->get('excluded_models');
            $includedModels = $this->config()->get('included_models');
            foreach ($classes as $class) {
                if (in_array($class, $excludedModels)) {
                    continue;
                }
                if (!empty($includedModels) && !in_array($class, $includedModels)) {
                    continue;
                }

                if (! $this->IsValidClassName($class)) {
                    continue;
                }
                // get the name
                $obj = Injector::inst()->get($class);
                $count = $class::get()->filter(['ClassName' => $class])->count();
                if ($count === 0) {
                    continue;
                }
                if ($obj->hasMethod('CMSEditLink')) {
                    $name = $obj->i18n_singular_name();
                    $desc = $obj->Config()->get('description');
                    if ($desc) {
                        $name .= ' - ' . $desc;
                    }
                    $name = trim($name);
                    // add name to list
                    foreach ([$otherList, $pageList] as $list) {
                        if (in_array($name, $list, true)) {
                            $name .= ' (disambiguation class name: ' . $class . ')';
                        }
                    }
                    $name .= ' (' . $count . ' records)';
                    if ($obj instanceof SiteTree) {
                        $pageList[$class] = $name;
                    } else {
                        $otherList[$class] = $name;
                    }
                }
            }
            asort($pageList);
            asort($otherList);
            self::$listOfClasses = $pageList + $otherList;
        }
        return self::$listOfClasses;
    }

    protected function getSelectClassNameField(?bool $withInstructions = true, ?bool $onlyShowSelectedvalue = false): OptionsetField
    {
        $field = OptionsetField::create(
            'ClassNameToChange',
            $this->fieldLabel('ClassNameToChange'),
            $this->getListOfClasses()
        );
        if ($withInstructions) {
            $field->setDescription(
                '
                    Please select the record type you want to change.
                    This will be used to create a list of records to process.
                    Once selected, please save the record to continue.
                '
            );
        }
        if ($onlyShowSelectedvalue) {
            $source = $field->getSource();
            $field->setSource([
                $this->ClassNameToChange => $source[$this->ClassNameToChange] ?? 'ERROR! Class not found',
            ]);
        }

        return $field;
    }


    protected static array $listOfFieldNames = [];
    protected function getListOfFieldNames(): array
    {
        if (empty(self::$listOfFieldNames)) {
            $list = [];
            $record = $this->getRecordSingleton();
            if ($record) {
                $labels = $record->fieldLabels();
                $db = $record->config()->get('db');
                $className = get_class($record);

                // Get configuration variables using config system
                $excludedFields = $this->config()->get('excluded_fields');
                $includedFields = $this->config()->get('included_fields');
                $excludedFieldTypes = $this->config()->get('excluded_field_types');
                $includedFieldTypes = $this->config()->get('included_field_types');
                $excludedClassFieldCombos = $this->config()->get('excluded_class_field_combos');
                $includedClassFieldCombos = $this->config()->get('included_class_field_combos');

                foreach ($db as $name => $typeName) {
                    if (! $this->IsValidFieldType((string) $typeName)) {
                        continue;
                    }
                    $type = $record->dbObject($typeName);
                    // Skip if field is in excluded_fields
                    if (in_array($name, $excludedFields)) {
                        continue;
                    }

                    // Skip if field is in excluded_class_field_combos for this class
                    if (
                        isset($excludedClassFieldCombos[$className]) &&
                        in_array($name, $excludedClassFieldCombos[$className])
                    ) {
                        continue;
                    }

                    // Skip if field type is in excluded_field_types
                    if ($this->isExcludedFieldType($type, $excludedFieldTypes)) {
                        continue;
                    }

                    // Skip if not explicitly included when included_fields is not empty
                    if (!empty($includedFields) && !in_array($name, $includedFields)) {
                        continue;
                    }

                    // Skip if not explicitly included when included_class_field_combos for this class is not empty
                    if (
                        isset($includedClassFieldCombos[$className]) &&
                        !empty($includedClassFieldCombos[$className]) &&
                        !in_array($name, $includedClassFieldCombos[$className])
                    ) {
                        continue;
                    }

                    // Skip if field type is not explicitly included when included_field_types is not empty
                    if (!empty($includedFieldTypes) && !$this->isIncludedFieldType($type, $includedFieldTypes)) {
                        continue;
                    }

                    // all good in the hood
                    $list[$name] = $labels[$name] ?? $name;
                }
            }
            self::$listOfFieldNames = $list;
        }
        return self::$listOfFieldNames;
    }

    /**
     * Check if a field type is in the excluded_field_types list
     *
     * @param DBField $type The field type to check
     * @param array $excludedFieldTypes List of excluded field types
     * @return bool
     */
    protected function isExcludedFieldType($type, array $excludedFieldTypes): bool
    {
        foreach ($excludedFieldTypes as $excludedType) {
            if ($type instanceof $excludedType) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a field type is in the included_field_types list
     *
     * @param DBField $type The field type to check
     * @param array $includedFieldTypes List of included field types
     * @return bool
     */
    protected function isIncludedFieldType($type, array $includedFieldTypes): bool
    {
        if (empty($includedFieldTypes)) {
            return true; // If no included types specified, all non-excluded types are included
        }

        foreach ($includedFieldTypes as $includedType) {
            if ($type instanceof $includedType) {
                return true;
            }
        }
        return false;
    }

    protected function getSelectFieldNameField(?bool $withInstructions = true, ?bool $onlyShowSelectedvalue = false): OptionsetField
    {
        $field = OptionsetField::create(
            'FieldToChange',
            $this->fieldLabel('FieldToChange'),
            $this->getListOfFieldNames()
        );
        if ($withInstructions) {
            $field->setDescription(
                '
                    Please select the field you want to change.
                    Once selected, please save the record to continue.
                '
            );
        }
        if ($onlyShowSelectedvalue) {
            $field->setSource([
                $this->FieldToChange => $field->getSource()[$this->FieldToChange],
            ]);
        }
        return $field;
    }

    protected function IsValidClassName(string $className)
    {
        if ($className && class_exists($className)) {
            return true;
        }
        return false;
    }
    protected function IsValidFieldType(string $type): bool
    {
        //It removes everything from the first (  to the end
        $type = Converters::standardised_field_type($type);
        switch ($type) {
            case 'Varchar':
            case 'Text':
            case 'HTMLText':
            case 'HTMLVarchar':
            case 'Boolean':
            case 'Int':
            case 'Float':
            case 'Decimal':
            case 'Datetime':
            case 'Date':
            case 'Time':
                return true;
            default:
                return false;
        }
    }
}
