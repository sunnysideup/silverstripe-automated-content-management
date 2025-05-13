<?php

namespace Sunnysideup\AutomatedContentManagement\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

class SiteConfigExtensionForLLM extends Extension
{
    private static $db = [
        'LLMType' => 'Varchar(50)',
        'LLMModel' => 'Varchar(50)',
        'LLMKey' => 'Text',
        'LLMEnabled' => 'Boolean',
    ];

    public function isLLMEnabled(): bool
    {
        // to do - check credentials
        return (bool) $this->owner->LLMEnabled;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.LLM',
            [
                DropdownField::create('LLMType', 'Type of LLM you are using.', ['OpenAI' => 'OpenAI', 'Anthropic' => 'Anthropic (Claude)', 'Google' => 'Google'])
                    ->setEmptyString('Select LLM Type'),
                TextField::create('LLMModel', 'Engine you are using')
                    ->setDescription('e.g. gpt-3.5-turbo, gpt-4, claude-2, just leave blank for default'),
                TextField::create('LLMKey', 'LLM Key - this is the key you get from OpenAI or any other LLM provider.'),
                CheckboxField::create('LLMEnabled', 'Enable LLM (AI) functions for this site - only turn this on while you are using these functions.'),
            ]
        );
    }
}
