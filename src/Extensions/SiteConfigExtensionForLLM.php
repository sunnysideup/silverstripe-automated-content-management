<?php

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;

class SiteConfigExtensionForLLM extends Extension
{
    private static $db = [
        'LLMEnabled' => 'Boolean',
        'LLMKey' => 'Text',
        'LLMType' => 'Varchar(255)',
        'LLMEngine' => 'Varchar(255)',
    ];

    public function isLLMEnabled()
    {
        return $this->owner->LLMEnabled;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.LLM',
            [

                DropdownField::create('LLMType', 'Type of LLM you are using.', ['OpenAI' => 'OpenAI', 'Anthropic' => 'Anthropic (Claude)', 'Google' => 'Google'])
                    ->setEmptyString('Select LLM Type'),
                TextField::create('LLMEngine', 'Engine you are using.'),
                TextField::create('LLMKey', 'LLM Key - this is the key you get from OpenAI or any other LLM provider.'),
                CheckboxField::create('LLMEnabled', 'Enable LLM (AI) functions for this site - only turn this on while you are using these functions.'),
            ]
        );
    }
}
