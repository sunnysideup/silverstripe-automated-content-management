<?php

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField_Readonly;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\HTML;
use Sunnysideup\AutomatedContentManagement\Control\QuickEditController;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\ClassesAndFieldsInfo\Api\ClassAndFieldInfo;

class DataObjectUpdateCMSFieldsHelper
{
    use Injectable;
    use Configurable;

    private static string $tab_name = 'Root.LLM';

    public static function my_link_builder(mixed ...$args): string
    {
        foreach ($args as $key => $arg) {
            $arg = str_replace('\\', '-', $arg);
            $args[$key] = rawurlencode($arg);
        }
        return self::my_link(Controller::join_links(...$args));
    }

    private static function my_link(?string $action = null): string
    {
        $link = Controller::join_links(
            Config::inst()->get(QuickEditController::class, 'url_segment'),
            $action
        );
        return '/' . ltrim($link, '/');
    }

    protected static $record_count_cache = [];
    protected static $fields_completed = [];

    public function updateCMSFields($owner, FieldList $fields)
    {
        $acceptableClasses = Injector::inst()->get(ClassAndFieldInfo::class)->getListOfClasses(
            array_replace(
                Config::inst()->get(Instruction::class, 'class_and_field_inclusion_exclusion_schema'),
                [
                    'grouped' => false
                ]
            )
        );
        if (! isset($acceptableClasses[$owner->ClassName])) {
            return;
        }
        // Add your custom fields to the CMS fields here
        if ($owner->ID && $owner->exists() && $owner->canEdit()) {
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
        // print_r($acceptableFields);
        foreach (array_keys($acceptableFields) as $acceptableFieldName) {
            // if (isset(self::$fields_completed[$owner->ClassName][$acceptableFieldName])) {
            //     continue;
            // }
            $field = $fields->dataFieldByName($acceptableFieldName);
            if (! $field) {
                continue;
            }
            // echo 'Field found: ' . $acceptableFieldName . "\n";
            $this->addLinksToInstructionsToOneField($owner, $field, $fields);
        }
    }

    public function addLinksToInstructionsToOneField($owner, $field, $fields)
    {
        $hasDescField = $field->hasMethod('setDescription');
        $hasRightTitlteField = $field->hasMethod('setRightTitle');
        if (! $hasDescField && !$hasRightTitlteField) {
            return;
        }
        if ($field->hasExtraClass('llm-field')) {
            return;
        }
        if ($field->hasExtraClass('llm-field-skip')) {
            return;
        }
        if ($field->isReadonly()) {
            return;
        }
        if ($hasDescField) {
            $getMethod = 'getDescription';
            $setMethod = 'setDescription';
        } else if ($hasRightTitlteField) {
            $getMethod = 'getRightTitle';
            $setMethod = 'setRightTitle';
        }
        $fieldName = $field->getName();
        if ($fieldName) {
            self::$fields_completed[$owner->ClassName][$fieldName] = true;
            $description = $field->$getMethod();
            if ($description instanceof DBField) {
                $description = $description->getValue();
            }
            $description .= $this->getDescriptionForOneRecordAndField(
                $owner,
                $field->getName()
            );
            $field->$setMethod($description);
            $field->addExtraClass('llm-field');
        }

        // update field
    }

    public function getDescriptionForOneRecordAndField($owner, ?string $fieldName = null)
    {
        $desc = '';
        if ($this->IsEnabledClassName($owner) && $this->IsEnabledFieldName($fieldName)) {

            $fieldNameNice = $owner->fieldLabel($fieldName);
            $recordNameNice = $owner->i18n_singular_name();
            if ($fieldName) {
                $allInstructions = Instruction::get()
                    ->filter([
                        'ClassNameToChange' => $owner->ClassName,
                        'FieldToChange' => $fieldName,
                    ])
                    ->excludeAny([
                        'Cancelled' => true,
                        'Locked' => true,
                    ]);
            } else {
                $allInstructions = Instruction::get()
                    ->filter([
                        'ClassName' => $owner->ClassName,
                    ])
                    ->excludeAny([
                        'Cancelled' => true,
                        'Locked' => true,
                    ]);
            }

            $toUpdateName = $fieldName ? 'field: ' . $fieldNameNice : 'record: ' . $recordNameNice;

            $desc .= '<div class="llm-field-explanation llm-ajax-holder">';


            $title = '<span class="font-icon-menu-settings"></span>';
            $action = '/admin/settings#Root_LLM';
            $desc .= '<div class="edit-settings-llm-instructions"><a href="' . $action . '" title="Edit LLM Settings">' . $title . '</a></div>';

            $title = '<span class="font-icon-cancel"></span>';
            $action = $this->getBestDisableLink($owner, $fieldName);
            $desc .= '<div class="turn-off-llm-instructions"><a href="' . $action . '" title="Stop LLM Editing for now" onclick="loadContentForLLMFunction(event);">' . $title . '</a></div>';

            $recordProcessIds = [-1 => -1];
            foreach ($allInstructions as $i) {
                $recordProcessIds = array_merge($recordProcessIds, $i->ReviewableRecords()->columnUnique('ID'));
            }
            $reviewableRecords = RecordProcess::get()->filter([
                'ID' => $recordProcessIds,
                'RecordID' => $owner->ID,
            ]);
            if ($reviewableRecords && $reviewableRecords->exists()) {
                $desc .= '
                    <h2>Review processed LLM (AI) instructions to accept / decline</h2>';
                foreach ($reviewableRecords as $reviewableRecord) {
                    $desc .= '
                        <div>
                            <a href="' . $reviewableRecord->CMSEditLink() . '" class="icon-on-right" title="Review log"><span class="font-icon-info-circled"></span></a>
                            ' . $reviewableRecord->getResultPreviewLinkHTML() . ' made by
                            "' . $reviewableRecord->Instruction()->Title . '"
                        </div>';
                }
            }


            $existingLLMInstructionsForRunningIds = [-1 => -1];
            foreach ($allInstructions as $i) {
                if (! $i->getIsReadyForProcessing()) {
                    $existingLLMInstructionsForRunningIds[] = $i->ID;
                }
            }
            $existingLLMInstructionsForRunning = $allInstructions
                ->filter([
                    'ID' => $existingLLMInstructionsForRunningIds,
                ]);
            if ($existingLLMInstructionsForRunning && $existingLLMInstructionsForRunning->exists()) {
                $desc .= '
                    <h2>☑ Use existing instruction to update this ' . $toUpdateName . '</h2>';
                /**
                 * @var Instruction $instruction
                 */
                foreach ($existingLLMInstructionsForRunning as $instruction) {
                    $infoLink = $instruction->CMSEditLink();
                    $instructionLink = $instruction->CMSEditLink();
                    $recordIncludedInSelection = $instruction->getRecordList()->filter(['ID' => $owner->ID])->exists();
                    if ($recordIncludedInSelection) {
                        /**
                         * @var RecordProcess $recordProcess
                         */
                        $recordProcess = RecordProcess::get()
                            ->filter([
                                'InstructionID' => $instruction->ID,
                                'RecordID' => $owner->ID,
                            ])
                            ->first();
                        if ($recordProcess) {
                            $instructionLink = $recordProcess->CMSEditLink();
                            if ($recordProcess->HasOriginalUpdated()) {
                                $action = '<a href="' . $recordProcess->getRecordLinkView() . '">view result</a>';
                            } elseif ($recordProcess->getCanBeReviewed()) {
                                $action = '<a href="' . $recordProcess->Link() . '">review result</a>';
                            } elseif ($recordProcess->getCanProcess()) {
                                $action = '<a href="' . $recordProcess->getRunExistingRecordProcessNowForOneFieldLink() . '">run process now</a>';
                            } else {
                                $action = '<a href="' . $recordProcess->CMSEditLink() . '">review set up</a>';
                            }
                        } else {
                            $action = '<a href="' . $instruction->CMSEditLink() . '">review set up</a>';
                        }
                    } else {
                        $action = 'add this Record';
                        if ($fieldName) {
                            $link = $instruction->getSelectExistingLLMInstructionForOneRecordOneFieldLink($owner, $fieldName);
                        } else {
                            $link = $instruction->getSelectExistingLLMInstructionForOneRecordLink($owner);
                        }
                    }
                    $desc .= '
                        <div>
                            <a href="' . $infoLink . '" class="icon-on-right"><span class="font-icon-info-circled"></span></a>
                            <a href="' . $instructionLink . '">' . $instruction->getTitle() . '</a>: ' . $action . '
                        </div>';
                }
            }


            $desc .= '<h2>Create new LLM (AI) instructions</h2>';
            if ($fieldName) {
                $link = $owner->getCreateNewLLMInstructionForOneRecordOneFieldLink($fieldName);
                $add = 'for this record (' . $owner->getTitle() . ')';
                $desc .= '<div><a href="' . $link . '">+ for this ' . $toUpdateName . ' ' . $add . '</a></div>';
                $count = $this->getRecordCount($owner);
                if ($count > 1) {
                    $link = $this->getCreateNewLLMInstructionForClassOneFieldLink($owner->ClassName, $fieldName);
                    $toUpdateNameClass = 'for this field (' . $fieldNameNice . ') on all records (' . $count . ') of this type (' . $owner->i18n_singular_name() . ')';
                    $desc .= '<div><a href="' . $link . '">++ ' . $toUpdateNameClass . '</a></div>';
                }
                $randomName = 'ta_' . uniqid();
                $link = $this->getCreateNewLLMInstructionForClassOneFieldLinkTestNow($owner->ClassName, $fieldName, $owner);
                $iForI = Injector::inst()->get(InstructionsForInstructions::class, false, [$owner]);
                $desc .= '<h2>Try it now</h2>';
                $desc .= '<p>To try out now, please enter some instructions below</p>';
                $desc .= '<textarea name="' . $randomName . '"  rows="20">' . $iForI->getExampleInstruction($fieldName, false, true) . '</textarea>';
                $desc .= '<div class="llm-ajax-actions">';
                $desc .= '<a href="' . $link . '" data-description="' . $randomName . '" class="btn action btn-outline-primary font-icon-tick" onclick="loadContentForLLMFunction(event)">Request Improvement Ideas</a>';
                // $desc .= '<a href="' . $link . '" class="btn action btn-outline-primary font-icon-tick" onclick="loadContentForLLMFunction(event)">Check for Errors</a>';
                $desc .= '</div>';
            } else {
                $link = $owner->getCreateNewLLMInstructionForOneRecordLink();
                $desc .= '<div><a href="' . $link . '">+ for this record: ' . $owner->getTitle() . '</a></div>';
                $count = $this->getRecordCount($owner);
                if ($count > 1) {
                    $link = $this->getCreateNewLLMInstructionForClassLink($owner->ClassName);
                    $toUpdateNameClass = 'on all records (' . $count . ') of this type (' . $owner->i18n_singular_name() . ')';
                    $desc .= '<div><a href="' . $link . '">++ ' . $toUpdateNameClass . '</a></div>';
                }
            }
            $desc .= '</div>';
        } else {
            // 🤖
            $link = $this->getBestEnableLink($owner, $fieldName);
            $desc .= '<div class="llm-field-action llm-ajax-holder">
                <a href="' . $link . '" onclick="loadContentForLLMFunction(event)" title="edit with LLM (large language model / ai)">✨</a>
            </div>';
        }
        // update field
        return $desc;
    }

    public function addGenericLinksToRecord($owner, FieldList $fields)
    {
        $tabName = $this->Config()->get('tab_name');
        if (isset(self::$fields_completed[$owner->ClassName][$tabName])) {
            return;
        }
        self::$fields_completed[$owner->ClassName][$tabName] = true;
        $fields->addFieldsToTab(
            $tabName,
            [
                HTMLEditorField_Readonly::create(
                    '
                LLMInstructionsForClass',
                    'Edit Record',
                    'To start using your LLM, please click on the stardust button.' .
                        $this->getDescriptionForOneRecordAndField($owner)
                )
            ]
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

    public function getCreateNewLLMInstructionForClassOneFieldLinkTestNow(string $className, string $fieldName, $owner): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforonerecordonefieldtestnow', $className, $owner->ID, $fieldName);
    }

    public function getCreateNewLLMInstructionForClassOneFieldLinkTestNowError(string $className, string $fieldName, $owner): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforonerecordonefieldtestnowerror', $className, $owner->ID, $fieldName);
    }

    public function getBestEnableLink($owner, ?string $fieldName = null): string
    {
        if ($fieldName) {
            return $this->getEnableFieldLink($owner, $fieldName);
        }
        return $this->getEnableClassLink($owner);
    }

    public function getEnableClassLink($owner): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('enable', $owner->ClassName, $owner->ID);
    }

    public function getEnableFieldLink($owner, $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('enable', $owner->ClassName, $owner->ID, $fieldName);
    }

    public function getBestDisableLink($owner, ?string $fieldName = null): string
    {
        if ($fieldName) {
            return $this->getDisableFieldLink($owner, $fieldName);
        }
        return $this->getDisableClassLink($owner);
    }

    public function getDisableClassLink($owner): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('disable', $owner->ClassName, $owner->ID);
    }

    public function getDisableFieldLink($owner, $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('disable', $owner->ClassName, $owner->ID, $fieldName);
    }

    public function IsEnabledClassName($owner): bool
    {
        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->LLMEnabledClassNames) {
            $enabledClasses = explode(',', $siteConfig->LLMEnabledClassNames);
            return in_array($owner->ClassName, $enabledClasses);
        }
        return false;
    }

    public function IsEnabledFieldName(?string $fieldName = null): bool
    {
        if (!$fieldName) {
            return true;
        }
        $siteConfig = SiteConfig::current_site_config();
        if ($siteConfig->LLMEnabledFieldNames) {
            $enabledFields = explode(',', $siteConfig->LLMEnabledFieldNames);
            return in_array($fieldName, $enabledFields);
        }
        return true;
    }

    protected function getRecordCount($owner): int
    {
        if (! isset(self::$record_count_cache[$owner->ClassName])) {
            $className = $owner->ClassName;
            self::$record_count_cache[$owner->ClassName] = $className::get()->count();
        }
        return self::$record_count_cache[$owner->ClassName];
    }
}
