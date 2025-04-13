<?php

namespace Sunnysideup\AutomatedContentManagement;

class Instruction extends DataObject
{
    private static $table_name = 'AutomatedContentManagementInstruction';

    private static $db = [
        'Title' => 'Varchar(255)',
        'Description' => 'Text',
        'SortOrder' => 'Int',
    ];

    private static $has_one = [];

    private static $summary_fields = [];

    private static $default_sort = 'SortOrder';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        return $fields;
    }
}
