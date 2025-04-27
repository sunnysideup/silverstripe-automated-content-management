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
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\AutomatedContentManagement\Model\Api\InstructionsForInstructions;
use Sunnysideup\AutomatedContentManagement\Model\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\AutomatedContentManagement\Traits\CMSFieldsExtras;

class Instruction extends DataObject
{


    use CMSFieldsExtras;
    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $singular_name = 'Instruction';
    private static $plural_name = 'Instructions';
    private static $description = 'Instructions for the automated content management system.';

    private static array $excluded_models = [
        'SilverStripe\\Versioned\\ChangeSetItem',
        'DNADesign\\Elemental\\Models\\BaseElement',
    ];

    private static array $included_models = [];

    private static $db = [
        'ClassNameToChange' => 'Varchar(255)',
        'FieldToChange' => 'Varchar(255)',
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
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
        'Created.Nice' => 'Created',
        'Title' => 'Title',
    ];

    private static $searchable_fields = [
        'Title',
        'Description',
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
        'RecordType' => 'Varchar(255)',
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
                        'RunTest',
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
                'Tests' => RecordProcess::get()
                    ->filter(
                        [
                            'IsTest' => true
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
                'Queued' => RecordProcess::get()
                    ->filter(
                        [
                            'Started' => false
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
                    'Root.' . $name,
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
                    $fields->dataFieldByName('RejectAll')
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
        } elseif ($this->StartedProcess) {
            switch ($fieldName) {
                case 'Title':
                case 'Description':
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
        return $this->RecordsToProcess()->filter(['Completed' => true])->count();
    }

    public function getPercentageCompleted(): float
    {
        if ($this->getNumberOfRecords() === 0) {
            return 0;
        }
        return round(($this->getProcessedRecords() / $this->getNumberOfRecords()) * 100) / 100;
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

    public function getRecordType(): string
    {
        $obj = $this->getRecordSingleton();
        if ($obj) {
            $db = $obj->config()->get('db');
            return $db[$this->FieldToChange] ?? 'Error: Field does not exist';
        }
        return 'Error: Class does not exist';
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

        if ($this->Cancelled) {
            $this->ReadyToProcess = false;
            foreach ($this->RecordsToProcess() as $recordProcess) {
                $recordProcess->delete();
            }
        }
        if ($this->ReadyToProcess) {
            $this->RunTest = false;
        }
        if ($this->RunTest) {
            $this->ReadyToProcess = false;
        }
    }

    public function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->RunTest) {
            $item = $this->AddRecords(true, DB::get_conn()->random(), 1);
            if ($item) {
                $obj = Injector::inst()->get(ProcessOneRecord::class);
                $obj->recordAnswer($item);
            }
            $this->RunTest = false;
            $this->write();
        } elseif ($this->ReadyToProcess) {
            $this->AddRecords(false);
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
        $ids = $className::get()->columnUnique('ID');
        foreach ($ids as $id) {
            $filter = [
                'RecordID' => $id,
                'InstructionID' => $this->ID,
                'IsTest' => $isTest,
            ];
            $recordProcess = null;
            if ($isTest === false) {
                $recordProcess = RecordProcess::get()->filter($filter)->first();
            }
            if (! $recordProcess) {
                $recordProcess = RecordProcess::create($filter);
            }
            $recordProcess->write();
        }
        if ($limit === 1) {
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
                foreach ($db as $name => $type) {
                    if (! $this->IsValidFieldType($type)) {
                        continue;
                    }
                    $list[$name] = $labels[$name] ?? $name;
                }
            }
            self::$listOfFieldNames = $list;
        }
        return self::$listOfFieldNames;
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
        $type = preg_replace('/\(.*$/', '', $type);
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
