<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBEnum;
use SilverStripe\ORM\FieldType\DBString;

class InstructionsForInstructions
{

    use Injectable;
    use Configurable;
    protected $record;

    public function __construct($record)
    {
        if (!$record instanceof DataObject) {
            throw new \InvalidArgumentException('Record must be an instance of DataObject. Provided: ' . get_class($record));
        }
        $this->record = $record;
    }

    public function getExampleInstruction(?string $fieldName = null, ?bool $withHtml = true, ?bool $includeErrorCheck = false): string
    {
        $nameOfRecord = $this->record->i18n_singular_name();
        if ($fieldName) {
            $fieldNameNice = $this->record->fieldLabel($fieldName);

            $v = '
        <div style="font-style: italic; ">
        I am trying to update a ' . $nameOfRecord . ' record.
        <br />I would like to improve the "' . $fieldNameNice . '" field in this record.
        The current value of the ' . $fieldNameNice . ' is:
        <br />
        <br />
        <strong>$' . $fieldName . '</strong>
        <br />
        (N.B. This variable will (with the dollar sign) be converted to the actual value for the record. More variables are listed below.)
        <br />
        <br />
        Can you please improve it  ... here you put how you would like to improve it (e.g. make it shorter).
        </div>';
        } else {
            $v = '
        <div style="font-style: italic; ">
        I am trying to update a ' . $nameOfRecord . ' record.
        <br />I would like to improve the this record.
        <br />
        <br />
        Can you please improve it  ... here you put how you would like to improve it (e.g. make it shorter).
        </div>';
        }
        if (! $withHtml) {
            $v = preg_replace('/<br\s*\/?>/i', "\n", $v);
            $v = strip_tags($v);
            $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $v = preg_replace('/[ \t]+/', ' ', $v);
            $v = preg_replace('/\n{2,}/', "\n", $v);
            $v = preg_replace('/ +\n/', "\n", $v);
            $v = preg_replace('/\n +/', "\n", $v);
            $v = trim($v);
        }
        return $v;
    }

    public function getInstructions(): string
    {
        $fieldLists  = $this->getListOfFields();
        $sectionsHtml = '';

        foreach ($fieldLists as $header => $items) {
            // hack for better hierarchy
            if (stripos($header, 'Main') === 0) {
                $level = '2';
            } else {
                $level = '3';
            }
            $sectionsHtml .= '
                <h' . $level . '>' . $header . '</h' . $level . '>
                <ul>';
            foreach ($items as $label => $placeholder) {
                $sectionsHtml .= '
                    <li>' . $label . ': <strong>$' . $placeholder . '</strong></li>';
            }
            $sectionsHtml .= '
                </ul>';
        }

        return
            '<div style="padding: 2rem;">'
            .  '<h2>Examples of variable usage in your instructions</h2>'
            . '<p>'
            . 'e.g. if you have a record with a Title, Description Field and you are trying to get a summary for each record then your instruction could be something like:'
            . '<br ><em>Consider the Title: $Title and the Description: "$Description" and summarise it in less than five words.</em>'
            . '<br /><br />You can also use <em><% if $Foo %>something only shown if the variable Foo is available <% end_if %></em> to check for the existence of a variable (Foo in this case). For example: '
            . '<br /><em>Consider the Title: $Title <% if $Description %>and the Description: $Description<% end_if %> value(s) for this record and summarise it in less than five words.</em>.'
            . '</p>'
            . '<p>To use values from the record at hand, use the following field variables:</p>'
            . $sectionsHtml
            . '</div>';
    }

    protected function getListOfFields(): array
    {
        $allFields = $this->listOfDbFields($this->record);

        foreach (array_keys($this->record->config()->get('has_one') ?: []) as $relation) {
            $related = $this->record->$relation();
            if ($related instanceof DataObjectInterface) {
                $allFields += $this->listOfDbFields($related, $relation);
            }
        }

        return $allFields;
    }

    protected function listOfDbFields(DataObject $record, ?string $prefix = ''): array
    {
        $prefixString = $prefix ? $prefix . '.' : '';
        $fieldLabels  = $record->fieldLabels();
        $prefixName   = $prefix
            ? ($fieldLabels[$prefix] ?? $prefix)
            : $record->i18n_singular_name();

        $mainKey  = 'Main fields for <i>' . $prefixName . '</i>';
        $otherKey = 'Other fields for <i>' . $prefixName . '</i>';

        $mainFields  = [];
        $otherFields = [];

        foreach ($record->config()->get('db') as $name => $type) {
            $label       = $fieldLabels[$name] ?? $name;
            $placeholder = $prefixString . $name;
            $obj = $record->dbObject($name);
            $type = $this->typeOfField($obj);
            $label .=  ' (' . $type . ')';
            $placeholder .= $this->qualifierToAdd($obj);
            if ($this->isMainField($obj)) {
                $mainFields[$label]  = $placeholder;
            } else {
                $otherFields[$label] = $placeholder;
            }
        }
        asort($mainFields);
        asort($otherFields);
        return [
            $mainKey  => $mainFields,
            $otherKey => $otherFields,
        ];
    }

    protected function isMainField($obj): bool
    {
        if ($obj instanceof DBEnum) {
            return false;
        }
        if ($obj instanceof DBString) {
            return true;
        }
        return false;
    }

    protected function typeOfField($obj): string
    {
        $string = get_class($obj);
        $string = ClassInfo::shortName(nameOrObject: $string);
        if (str_starts_with($string, 'DB')) {
            $string = substr($string, 2);
        }
        switch ($string) {
            case 'HTMLText':
                return 'HTML';
            case 'HTMLVarchar':
                return 'HTML';
            case 'Varchar':
                return 'Short Text';
            case 'Text':
                return 'Long Text';
            case 'Boolean':
                return 'True/False';
            case 'Enum':
                return 'Predefined List';
            default:
                return $string;
        }
    }

    protected function qualifierToAdd($obj): string
    {
        if ($obj instanceof DBBoolean) {
            return '.Nice';
        }
        return '';
    }
}
