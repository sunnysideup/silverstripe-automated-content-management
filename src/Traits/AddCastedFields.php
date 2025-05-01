<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Traits;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\FieldType\DBField;

class AddCastedFields
{

    protected $originatingObject = null;


    /**
     *
     *
     * @param mixed $fields
     * @param string $tabName - e.g. Root.About
     * @param mixed $otherFieldsToAdd
     *              provide similar to casting in terms of the array format
     * @param mixed $fieldsToSkip
     *              provide a simple list of field names
     * @return void
     */
    public function addCastingFieldsNow($originatingObject, FieldList $fields, ?string $tabName = 'Root.About', ?array $otherFieldsToAdd = [], ?array $fieldsToSkip = [])
    {
        $this->originatingObject = $originatingObject;
        $otherFieldsToAdd = [
            'Created' => 'Datetime',
            'LastEdited' => 'Datetime',
        ] + $otherFieldsToAdd;

        $fieldsToSkip = [
            'CSSClasses',
            'Title',
        ] + $fieldsToSkip;
        $fieldsToSkip = array_flip($fieldsToSkip);

        $otherFieldsToAdd = array_diff_key($otherFieldsToAdd, $fieldsToSkip);
        foreach ($otherFieldsToAdd as $name => $type) {
            $this->addCastingField($fields, $tabName, $name, $type);
        }
        $castedFields = $this->originatingObject->config()->get('casting');
        $castedFields = array_diff_key($castedFields, $fieldsToSkip, $otherFieldsToAdd);
        foreach ($castedFields as $name => $type) {
            $this->addCastingField($fields, $tabName, $name, $type);
        }
    }


    protected function addCastingField(FieldList $fields, string $tabName, string $name, string $type)
    {
        $methodName = 'get' . $name;
        if ($this->originatingObject->hasMethod($methodName)) {
            $v = $this->originatingObject->$methodName();
        } elseif ($this->originatingObject->hasMethod($name)) {
            $v = $this->originatingObject->$name();
        } else {
            $v = $this->originatingObject->dbObject($name);
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
            $tabName,
            [
                $className::create($name . 'NICE', $this->originatingObject->fieldLabel($name), $niceValue),
            ]
        );
    }
}
