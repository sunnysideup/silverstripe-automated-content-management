<?php

namespace Sunnysideup\AutomatedContentManagement\Traits;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;

trait CMSFieldsExtras
{
    /**
     *
     *
     * @param mixed $fields
     * @param mixed $fieldsToAdd
     *              provide similar to casting in terms of the array format
     * @param mixed $fieldsToSkip
     *              provide a simple list of field names
     * @return void
     */
    protected function addCastingFieldsNow(FieldList $fields, ?array $fieldsToAdd = [], ?array $fieldsToSkip = [])
    {
        $fieldsToAdd = [
            'Created' => 'Datetime',
            'LastEdited' => 'Datetime',
        ] + $fieldsToAdd;

        $fieldsToSkip = [
            'CSSClasses',
            'Title',
        ] + $fieldsToSkip;

        $fieldsToSkip = array_flip($fieldsToSkip);

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
        $className = ReadonlyField::class;
        if ($type === 'HTMLText' || (strpos($niceValue, '<') && strpos($niceValue, '</'))) {
            $className = HTMLReadonlyField::class;
        }
        $fields->addFieldsToTab(
            'Root.Details',
            [
                $className::create($name . 'NICE', $this->fieldLabel($name), $niceValue),
            ]
        );
    }
}
