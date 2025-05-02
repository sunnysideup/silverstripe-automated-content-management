<?php

namespace Sunnysideup\AutomatedContentManagement\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;

trait MakeFieldsRoadOnly
{

    /**
     * make sure you have a makeFieldsReadonlyInner method in your class
     * that returns true or false depending on whether the field should be readonly
     * @param mixed $fields
     * @return void
     */
    protected function makeFieldsReadonly($fields)
    {
        foreach ($fields->dataFields() as $field) {
            $fieldName = $field->getName();
            if ($this->makeFieldsReadonlyInner($fieldName)) {
                $myField = $fields->dataFieldByName($fieldName);
                if ($myField) {
                    $fields->replaceField(
                        $fieldName,
                        $myField
                            ->performDisabledTransformation()
                            ->setReadonly(true)
                    );
                }
            }
        }
    }
}
