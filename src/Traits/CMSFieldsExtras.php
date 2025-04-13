<?php

namespace Sunnysideup\AutomatedContentManagement\Traits;

use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\FieldType\DBField;

trait CMSFieldsExtras
{
    protected function addCastingFieldsNow($fields)
    {

        $fieldsToAdd =
            [
                'Created' => 'Datetime',
                'LastEdited' => 'Datetime',
            ]
            +
            $this->config()->get('casting');

        foreach ($fieldsToAdd as $name => $type) {
            $methodName = 'get' . $name;
            $v = $this->$methodName();
            if (!($v instanceof DBField)) {
                $v = DBField::create_field($type, $v);
            }
            $niceValue = $v->Nice();
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    ReadonlyField::create($name . 'NICE', $this->fieldLabel($name), $niceValue),
                ]
            );
        }
    }
}
