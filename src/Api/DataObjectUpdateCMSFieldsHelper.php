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


    public static function my_link(?string $action = null): string
    {
        $link = Controller::join_links(
            Config::inst()->get(QuickEditController::class, 'url_segment'),
            $action
        );
        return $link;
    }


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
            $field = $fields->dataFieldByName($acceptableFieldName);
            if (! $field) {
                continue;
            }
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
        $desc = '<div class="llm-field-explanation" style="
            padding: 1rem;
            padding-bottom: 0;
            background-color:#ffc10755;
            border: 5px dashed #ffc107;
            margin-bottom: 1rem;
            border-radius: 1rem;
        ">';
        $toUpdateName = $fieldName ? 'Field' : 'Record';
        if ($fieldName) {
            $link = $owner->getCreateNewLLMInstructionForOneRecordOneFieldLink($fieldName);
        } else {
            $link = $owner->getCreateNewLLMInstructionForOneRecordLink();
        }
        $desc .= '
        <p>
            <a href="' . $link . '">Create LLM (AI) instructions to update this ' . $toUpdateName . '</a>
        </p>';

        if ($fieldName) {
            $link = $this->getCreateNewLLMInstructionForClassOneFieldLink($owner->ClassName, $fieldName);
            $toUpdateNameClass = 'Field on all Records of this Type';
        } else {
            $link = $this->getCreateNewLLMInstructionForClassLink($owner->ClassName);
            $toUpdateNameClass = 'All Records of this Type';
        }
        $desc .= '
        <p>
            <a href="' . $link . '">Create LLM (AI) instructions to update this ' . $toUpdateNameClass . '</a>
        </p>';
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
                <h5>Use existing instruction to update this ' . $toUpdateName . '</h5>';
            foreach ($existingLLMInstructionsForRunning as $instruction) {
                if ($fieldName) {
                    $link = $instruction->getSelectExistingLLMInstructionForOneRecordOneFieldLink($owner, $fieldName);
                } else {
                    $link = $instruction->getSelectExistingLLMInstructionForOneRecordLink($owner);
                }
                $desc .= '
                    <p>
                        <a href="' . $link . '">' . $instruction->getTitle() . '</a>
                    </p>';
            }
        }
        $recordsProcessed = RecordProcess::get()
            ->filter([
                'InstructionID' => $allInstructions->columnUnique('ID') + [-1 => -1],
                'Completed' => 1,
            ]);
        if ($recordsProcessed && $recordsProcessed->exists()) {
            $desc .= '
                <h5>Review processed LLM (AI) instructions to accept / decline</h5>';
            foreach ($recordsProcessed as $recordProcessed) {
                $desc .= '
                    <p>
                        ' . $recordProcessed->getTitle() . ':
                        <a href="' . $recordProcessed->getResultPreviewLink() . '">view result (and accept / decline)</a>,
                        <a href="' . $recordProcessed->CMSEditLink() . '">review details</a>
                    </p>';
            }
        }
        $desc .= '</div>';

        // update field
        return $desc;
    }

    public function addGenericLinksToRecord($owner, FieldList $fields)
    {
        $isEnabled = SiteConfig::current_site_config()->isLLMEnabled();
        if ($isEnabled) {
            $title = 'Turn off LLM (AI) options in the CMS';
            $action = self::my_link('turnllmfunctionsonoroff/off');
        } else {
            $title = 'Turn on LLM (AI) options in the CMS';
            $action = self::my_link('turnllmfunctionsonoroff/on');
        }
        $html = '<a href="' . $action . '">' . $title . '</a>';
        if ($isEnabled) {
            $html .= $this->createDescriptionForOneRecordAndField($owner);
        }
        $fields->addFieldToTab(
            'Root.LLM',
            LiteralField::create('LLMInstructions', $html)
        );
    }

    public function getCreateNewLLMInstructionForClassLink(string $className): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link('createinstructionforclass' . '/' . $className);
    }

    public function getCreateNewLLMInstructionForClassOneFieldLink(string $className, string $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link('createinstructionforclassonefield' . '/' . $className . '/0/' . $fieldName);
    }
}
