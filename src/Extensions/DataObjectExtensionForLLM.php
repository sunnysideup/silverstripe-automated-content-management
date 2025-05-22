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

        $this->callProtectedMethod(
            $this->owner,
            'afterUpdateCMSFields',
            [
                function (FieldList $fields) use ($owner) {
                    if (SiteConfig::current_site_config()->IsLLMEnabled()) {
                        $obj = Injector::inst()->create(DataObjectUpdateCMSFieldsHelper::class);
                        $obj->updateCMSFields($owner, $fields);
                    }
                }
            ]
        );
    }

    public function getCreateNewLLMInstructionForOneRecordLink(): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforonerecord', $this->owner->ClassName, $this->owner->ID);
    }

    public function getCreateNewLLMInstructionForOneRecordOneFieldLink(string $fieldName): string
    {
        return DataObjectUpdateCMSFieldsHelper::my_link_builder('createinstructionforonerecordonefield', $this->owner->ClassName, $this->owner->ID, $fieldName);
    }
    private function callProtectedMethod(object $object, string $methodName, array $args = [])
    {
        $refMethod = new \ReflectionMethod($object, $methodName);
        $refMethod->setAccessible(true); // still needed for unrelated classes
        return $refMethod->invokeArgs($object, $args);
    }
}
