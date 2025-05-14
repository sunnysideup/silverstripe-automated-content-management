<?php

namespace Sunnysideup\AutomatedContentManagement\Extensions;

use SilverStripe\Core\Extension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\AutomatedContentManagement\Api\DataObjectUpdateCMSFieldsHelper;

class DataObjectExtensionForLLM extends Extension
{

    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->getOwner();
        // Add your custom fields to the CMS fields here
        if (SiteConfig::current_site_config()->isLLMEnabled()) {
            $obj = Injector::inst()->create(DataObjectUpdateCMSFieldsHelper::class);
            $obj->updateCMSFields($owner, $fields);
        }
    }

    public function getCreateNewLLMInstructionForOneRecordLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link('createinstructionforonerecord' . '/' . $this->owner->ClassName . '/' . $this->owner->ID);
    }

    public function getCreateNewLLMInstructionForOneRecordOneFieldLink(string $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link('createinstructionforonerecordonefield' . '/' . $this->owner->ClassName . '/' . $this->owner->ID . '/' . $fieldName);
    }
}
