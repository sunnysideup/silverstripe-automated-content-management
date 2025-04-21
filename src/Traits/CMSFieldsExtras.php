<?php

namespace Sunnysideup\AutomatedContentManagement\Traits;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;

trait CMSFieldsExtras
{
    protected function addCastingFieldsNow($fields)
    {

        $fieldsToAdd = [
            'Created' => 'Datetime',
            'LastEdited' => 'Datetime',
        ];

        $fieldsToSkip = [
            'CSSClasses' => 'Varchar',
            'Title' => 'Varchar',
        ];

        foreach ($fieldsToAdd as $name => $type) {
            $this->addCastingFieldsNowInner($name, $type, $fields);
        }
        $castedFields = $this->config()->get('casting');
        if (is_array($fieldsToAdd)) {
            $castedFields = array_diff_key($castedFields, $fieldsToSkip, $fieldsToAdd);
        } else {
            $castedFields = [];
        }
        foreach ($castedFields as $name => $type) {
            $this->addCastingFieldsNowInner($name, $type, $fields);
        }
    }


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

    protected function addCastingFieldsNowInner($name, $type, $fields)
    {
        $methodName = 'get' . $name;
        if ($this->hasMethod($methodName)) {
            $v = $this->$methodName();
        } elseif ($this->hasMethod($name)) {
            $v = $this->$name();
        } else {
            $v = $this->dbObject($name);
        }
        if (!($v instanceof DBField)) {
            $v = DBField::create_field($type, $v);
        }
        if ($v->hasMethod('Nice')) {
            $niceValue = $v->Nice();
        } else {
            $niceValue = $v->forTemplate();
        }
        $fields->addFieldsToTab(
            'Root.Details',
            [
                ReadonlyField::create($name . 'NICE', $this->fieldLabel($name), $niceValue),
            ]
        );
    }
}
