<?php

namespace Sunnysideup\AutomatedContentManagement\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FieldList;
use Sunnysideup\AiLlmConnector\Api\ConnectorBaseClass;

class SiteConfigExtensionForLLM extends Extension
{
    private static $db = [
        'LLMEnabled' => 'Boolean',
        'LLMEnabledClassNames' => 'Text',
        'LLMEnabledFieldNames' => 'Text',
    ];

    public function IsLLMEnabled(): bool
    {
        // to do - check credentials
        return ConnectorBaseClass::is_ready() && $this->getOwner()->LLMEnabled;
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.LLM',
            [
                CheckboxField::create('LLMEnabled', 'Enable LLM (AI) functions for this site - only turn this on while you are using these functions.'),
            ]
        );
    }

    public function onBeforeWrite()
    {
        if (!$this->getOwner()->LLMEnabled) {
            $this->getOwner()->LLMEnabledClassNames = '';
            $this->getOwner()->LLMEnabledFieldNames = '';
        }
    }
}
