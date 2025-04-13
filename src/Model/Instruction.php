<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use Sunnysideup\AutomatedContentManagement\Model\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\AutomatedContentManagement\Traits\CMSFieldsExtras;

class Instruction extends DataObject
{

    use CMSFieldsExtras;
    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'RunTest' => 'Boolean',
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
        'RecordsToProcess',
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
                'ReadyToProcess',
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
            $fields->removeByName(
                [
                    'RunTest',
                ]
            );
        } else {
            $fields->removeByName(
                [
                    'Completed',
                    'Cancelled',
                ]
            );
        }
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
        if ($this->ReadyToProcess) {
            $this->AddRecords(false);
        } elseif ($this->RunTest) {
            $item = $this->AddRecords(true, DB::get_conn()->random(), 1);
            if ($item) {
                $obj = Injector::inst()->get(ProcessOneRecord::class);
                $obj->recordAnswer($item);
            }
            $this->RunTest = false;
            $this->write();
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
}
