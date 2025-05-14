<?php

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\AutomatedContentManagement\Control\QuickEditController;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;

class DataObjectUpdateCMSFieldsHelper
{
    use Injectable;
    use Configurable;


    public static function my_link_builder(mixed ...$args): string
    {
        foreach ($args as $key => $arg) {
            $args[$key] = rawurlencode($arg);
        }
        return self::my_link(Controller::join_links(...$args));
    }

    public static function my_link(?string $action = null): string
    {
        $link = Controller::join_links(
            Config::inst()->get(QuickEditController::class, 'url_segment'),
            $action
        );
        return $link;
    }

    protected static $record_count_cache = [];
    protected static $fields_completed = [];

    public function updateCMSFields($owner, FieldList $fields)
    {
        if ($owner instanceof Instruction || $owner instanceof RecordProcess) {
            return;
        }
        $acceptableClasses = Injector::inst()->get(ClassAndFieldInfo::class)->getListOfClasses(
            array_replace(
                Config::inst()->get(Instruction::class, 'class_and_field_inclusion_exclusion_schema'),
                ['grouped' => false]
            )
        );
        if (! isset($acceptableClasses[$owner->ClassName])) {
            return;
        }
        // Add your custom fields to the CMS fields here
        if ($owner->ID && $owner->exists() && $owner->canEdit()) {
            if (! isset(self::$record_count_cache[$owner->ClassName])) {
                $className = $owner->ClassName;
                self::$record_count_cache[$owner->ClassName] = $className::get()->count();
            }
            if (!isset(self::$fields_completed[$owner->ClassName])) {
                self::$fields_completed[$owner->ClassName] = [];
            }
            $this->addLinksToInstructions($owner, $fields);
            $this->addGenericLinksToRecord($owner, $fields);
        }
    }


    public function addLinksToInstructions($owner, FieldList $fields)
    {
        $acceptableFields = Injector::inst()->get(ClassAndFieldInfo::class)->getListOfFieldNames(
            $owner->ClassName,
            ['db'],
            array_replace(
                Config::inst()->get(Instruction::class, 'class_and_field_inclusion_exclusion_schema'),
                ['grouped' => false]
            ),
        );
        foreach (array_keys($acceptableFields) as $acceptableFieldName) {
            if (isset(self::$fields_completed[$owner->ClassName][$acceptableFieldName])) {
                continue;
            }
            $field = $fields->dataFieldByName($acceptableFieldName);
            if (! $field) {
                continue;
            }
            self::$fields_completed[$owner->ClassName][$acceptableFieldName] = true;
            $this->addLinksToInstructionsToOneField($owner, $field);
        }
    }

    public function addLinksToInstructionsToOneField($owner, $field)
    {
        $hasDescField = $field->hasMethod('setDescription');
        $hasRightTitlteField = $field->hasMethod('setDescription');
        if (! $hasDescField && !$hasRightTitlteField) {
            return;
        }
        if ($field->hasExtraClass('llm-field')) {
            return;
        }
        if ($field->hasExtraClass('llm-field-skip')) {
            return;
        }
        if ($hasDescField) {
            $getMethod = 'getDescription';
            $setMethod = 'setDescription';
        } else if ($hasRightTitlteField) {
            $getMethod = 'getRightTitle';
            $setMethod = 'setRightTitle';
        }
        $description = $field->$getMethod();
        if ($description instanceof DBField) {
            $description = $description->getValue();
        }
        $fieldName = $field->getName();
        if ($fieldName) {
            $description .= $this->createDescriptionForOneRecordAndField(
                $owner,
                $field->getName()
            );
            $field->$setMethod($description);
        }

        // update field
        $field->addExtraClass('llm-field');
    }

    public function createDescriptionForOneRecordAndField($owner, ?string $fieldName = null)
    {
        $desc = '<div class="llm-field-explanation">';
        $desc .= '<h2>LLM (AI) instructions</h2>';

        $title = '<span class="font-icon-menu-settings"></span>';
        $action = '/admin/settings#Root_LLM';
        $desc .= '<div class="edit-settings-llm-instructions"><a href="' . $action . '">' . $title . '</a></div>';

        $title = '<span class="font-icon-cancel"></span>';
        $action = self::my_link('turnllmfunctionsonoroff/off');
        $desc .= '<div class="turn-off-llm-instructions"><a href="' . $action . '">' . $title . '</a></div>';


        $toUpdateName = $fieldName ? 'Field' : 'Record';
        if ($fieldName) {
            $link = $owner->getCreateNewLLMInstructionForOneRecordOneFieldLink($fieldName);
        } else {
            $link = $owner->getCreateNewLLMInstructionForOneRecordLink();
        }
        $desc .= '
        <div>
            <a href="' . $link . '">Create LLM (AI) instructions to update this ' . $toUpdateName . '</a>
        </div>';
        if (self::$record_count_cache[$owner->ClassName] > 1) {
            if ($fieldName) {
                $link = $this->getCreateNewLLMInstructionForClassOneFieldLink($owner->ClassName, $fieldName);
                $toUpdateNameClass = 'Field on all Records of this Type';
            } else {
                $link = $this->getCreateNewLLMInstructionForClassLink($owner->ClassName);
                $toUpdateNameClass = 'All Records of this Type';
            }
            $desc .= '<div><a href="' . $link . '">Create LLM (AI) instructions to update this ' . $toUpdateNameClass . '</a></div>';
        }
        if ($fieldName) {
            $allInstructions = Instruction::get()
                ->filter([
                    'ClassNameToChange' => $owner->ClassName,
                    'FieldToChange' => $fieldName,
                ]);
        } else {
            $allInstructions = Instruction::get()
                ->filter([
                    'ClassName' => $owner->ClassName,
                ]);
        }

        $existingLLMInstructionsForRunning = $allInstructions
            ->filter([
                'StartedProcess' => 0,
                'Cancelled' => 0,
            ]);
        if ($existingLLMInstructionsForRunning && $existingLLMInstructionsForRunning->exists()) {
            $desc .= '
                <h2>Use existing instruction to update this ' . $toUpdateName . '</h2>';
            foreach ($existingLLMInstructionsForRunning as $instruction) {
                if ($fieldName) {
                    $link = $instruction->getSelectExistingLLMInstructionForOneRecordOneFieldLink($owner, $fieldName);
                } else {
                    $link = $instruction->getSelectExistingLLMInstructionForOneRecordLink($owner);
                }
                $desc .= '
                    <div>
                        <a href="' . $link . '">' . $instruction->getTitle() . '</a>
                    </div>';
            }
        }
        $recordsProcessed = RecordProcess::get()
            ->filter([
                'InstructionID' => $allInstructions->columnUnique('ID') + [-1 => -1],
                'Completed' => 1,
            ]);
        if ($recordsProcessed && $recordsProcessed->exists()) {
            $desc .= '
                <h2>Review processed LLM (AI) instructions to accept / decline</h2>';
            foreach ($recordsProcessed as $recordProcessed) {
                $desc .= '
                    <div>
                        ' . $recordProcessed->getTitle() . ':
                        <a href="' . $recordProcessed->getResultPreviewLink() . '">view result (and accept / decline)</a>,
                        <a href="' . $recordProcessed->CMSEditLink() . '">review details</a>
                    </div>';
            }
        }
        $desc .= '</div>';

        // update field
        return $desc;
    }

    public function addGenericLinksToRecord($owner, FieldList $fields)
    {
        $tabName = 'Root.LLM';
        if (isset(self::$fields_completed[$owner->ClassName][$tabName])) {
            return;
        }
        self::$fields_completed[$owner->ClassName][$tabName] = true;
        $fields->addFieldToTab(
            $tabName,
            LiteralField::create('LLMInstructions', $this->createDescriptionForOneRecordAndField($owner))
        );
    }

    public function getCreateNewLLMInstructionForClassLink(string $className): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforclass', $className);
    }

    public function getCreateNewLLMInstructionForClassOneFieldLink(string $className, string $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforclassonefield', $className, $fieldName);
    }
}
