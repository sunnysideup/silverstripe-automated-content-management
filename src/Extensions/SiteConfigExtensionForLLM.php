<?php

namespace Sunnysideup\AutomatedContentManagement\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\TextField;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;

class SiteConfigExtensionForLLM extends Extension
{
    private static $db = [
        'LLMClient' => 'Varchar(50)',
        'LLMModel' => 'Varchar(50)',
        'LLMKey' => 'Text',
        'LLMEnabled' => 'Boolean',
        'LLMEnabledClassNames' => 'Text',
        'LLMEnabledFieldNames' => 'Text',
    ];

    public function IsLLMEnabled(): bool
    {
        // to do - check credentials
        return (bool) $this->owner->LLMEnabled &&
            (!$this->owner->LLMClient || $this->owner->LLMKey);
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.LLM',
            [
                DropdownField::create(
                    'LLMClient',
                    'Type of LLM you are using.',
                    ConnectorBaseClass::inst()->getClientNameList()
                )
                    ->setEmptyString('-- Select LLM Type --'),
                TextField::create('LLMModel', 'Engine you are using')
                    ->setDescription('e.g. gpt-3.5-turbo, gpt-4, claude-2, just leave blank for default.'),
                TextField::create('LLMKey', 'LLM Key ')
                    ->setDescription('e.g. sk-1234<br />
                    You can get your key from your LLM provider.
                    For OpenAI, you can get it from <a href="https://platform.openai.com/account/api-keys" target="_blank">here</a>.
                    For Anthropic, you can get it from <a href="https://console.anthropic.com/keys" target="_blank">here</a>.'),
                CheckboxField::create('LLMEnabled', 'Enable LLM (AI) functions for this site - only turn this on while you are using these functions.'),
                HTMLReadonlyField::create(
                    'TestYourLLM',
                    'Test your LLM',
                    '<a href="' . ConnectorBaseClass::inst()->getTestLink() . '" target="_blank">Test your LLM</a>',
                ),
            ]
        );
    }

    public function onBeforeWrite()
    {
        if (!$this->owner->LLMEnabled) {
            $this->owner->LLMEnabledClassNames = '';
            $this->owner->LLMEnabledFieldNames = '';
        }
    }
}
