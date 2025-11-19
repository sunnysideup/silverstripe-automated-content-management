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
use Sunnysideup\AutomatedContentManagement\Model\Instruction;

class InstructionsForInstructions
{

    use Injectable;
    use Configurable;
    protected $record;

    public function __construct($record = null)
    {
        if ($record !== null && !$record instanceof DataObject) {
            throw new \InvalidArgumentException('Record must be an instance of DataObject. Provided: ' . get_class($record));
        }
        $this->record = $record;
    }

    public function getExampleInstruction(Instruction $instruction, ?bool $withHtml = true, ?bool $includeErrorCheck = false): string
    {
        $fieldName = $instruction->FieldToChange;
        $nameOfRecord = $this->record->i18n_singular_name();
        if ($fieldName) {
            $fieldNameNice = $this->record->fieldLabel($fieldName);
            $databaseFieldName = $fieldName . 'ID';
            $fieldToChangeRelationType = $instruction->getFieldToChangeRelationType();
            $v = '<div style="font-style: italic; ">
             ';
            switch ($fieldToChangeRelationType) {
                case 'has_one':
                case 'belongs_to':
                    $v .= '
                    I am trying to update a ' . $nameOfRecord . ' record.
                    <br />
                    <br />
                    I would like to improve the "' . $fieldNameNice . '" field in this record.
                    <br />
                    This is a relation field. Each ' . $nameOfRecord . ' record has one ' . $fieldNameNice . '.
                    <br />
                    <br />
                    The database field is ' . $databaseFieldName . '.
                    The current ID and Title of this fields is:
                    <br />
                    <br />
                    <strong>$' . $databaseFieldName . ' : $' . $fieldName . '.Title</strong>
                    <br />
                    <br />
                    You can chose from the following list:
                    <br />
                    $RelatedList
                    ';
                    break;
                case 'db':
                    $v .= '
                    I am trying to update a ' . $nameOfRecord . ' record.
                    <br />I would like to improve the "' . $fieldNameNice . '" field in this record.
                    The current value of the ' . $fieldNameNice . ' is:
                    <br />
                    <br />
                    <strong>$' . $fieldName . '</strong>
                    ';
                    break;
                default:
            }
            $v .= '
                    <br />
                    <br />
                    Can you please improve it  ... here you put how you would like to improve it (e.g. make it shorter, chose a different value, etc... etc...).
                    <br />
                    <br />
                    N.B. Words starting with a dollar sign will be replaced with actual values for the record.
                    For a full list of available fields please see below.
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
        if ($withHtml !== true) {
            $v = $this->stripHTML($v);
        }
        return $v;
    }

    public function getInstructionVarList(Instruction $instruction): string
    {
        $fieldLists  = $this->getListOfFields();
        $sectionsHtml = '';
        if ($instruction->getFieldToChangeIsRelationField) {
            $sectionsHtml .= '<p>
                It is recommended that you include a list of items related to this relational field you are updating,
                for this, you can add the following placeholder: <strong>$RelatedList</strong>.
                This will add a list of the related items with their identifier (ID) and their Title (or Name) field (or another field you can select, once added).
                As this list is used to find the related items,
                it is important that you include it and that you ensure that there is a way for the LLM to match
                the items based on their Title (or another field you select).
                Note that this list is limited to 1,000 records.
            </p>';
        }

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
            . '<h2>Using variables in your instructions</h2>'
            . '<p>'
            . 'Example: to get a summary (for each record) based on the title using its Title and Description fields, you could write:'
            . '<br><strong>'
            . 'Summarise the record using Title: $Title and Description: "$Description" in under five words.'
            . '</strong>'
            . '</p>'
            . '<p>Available field variables for this record:</p>'
            . $sectionsHtml
            . '<h2>Advanced Usage</h2>'
            . '<p>'
            . 'You can check if a variable exists before using it:'
            . '<br><strong>'
            . 'Summarise Title: $Title <% if $Description %>and Description: $Description<% end_if %> in under five words.'
            . '</strong>'
            . '<br><strong>'
            . 'Summarise Title: $Title, Description: <% if $Description %>$Description<% else %>No description available<% end_if %> in under five words.'
            . '</strong>'
            . '</p>'
            . '<p>'
            . 'You can loop through lists like this:'
            . '<br><strong>'
            . '<% loop $Foo %>$Title <% end_loop %>'
            . '</strong>'
            . '<br>Where Foo is a has_many or many_many relationship on the record.'
            . '</p>'
            . '</div>';
    }

    public function getFindErrorsOnlyInstruction(Instruction $instruction): string
    {
        return $this->cleanWhitespace(
            '
            If you find an error, then please prepend any answer with ' . $instruction->Config()->get('error_prepend') . '.
            If no error is found as per the instructions above then just return ' . $instruction->Config()->get('non_error_prepend')
        );
    }

    public function getGenericUpdateInstruction(Instruction $instruction): string
    {
        $recordTypeSpecificInstruction = '';
        $recordType = $instruction->getRecordType();
        switch ($recordType) {
            case 'Varchar':
            case 'Text':
                $recordTypeSpecificInstruction = 'Please return plain text only, no html.';
                break;
            case 'HTMLText':
            case 'HTMLVarchar':
                $recordTypeSpecificInstruction = 'For HTML, please make sure all text is wrapped in any of the following html tags: p, ul, ol, li, or h2 - h6 only.';
                break;
            default:
                // no specific instruction

        }
        $stringSeparator = Instruction::config()->get('list_separator');
        $type = $instruction->getFieldToChangeRelationType();
        if ($instruction->getFieldToChangeIsRelationField()) {
            if ($type === 'has_one' || $type === 'belongs_to') {
                $recordTypeSpecificInstruction .= '
                    If you have IDs available to chose from then please provide the ID directly.
                    If there is no IDs to chose from then please provide a string I will try to match it to the related record.
                    To provide an empty value just return 0.
                ';
            } else {
                $recordTypeSpecificInstruction .= '
                    To provide an empty list just return 0.
                    If you have IDs available then please provide the IDs as a comma separated list.
                    If you do not have any IDs available then provide a list of strings and I will try to match them to the related records.
                    This string based items should be separated with three pipes like this: ' . $stringSeparator . '.
                ';
            }
        }
        return $this->cleanWhitespace(
            'Please return the answer as a value suitable for insertion into a Silverstripe CMS Database

            `' . $type . '` field of type `' . $instruction->getRecordType() . '`.

            ' . $recordTypeSpecificInstruction . '

            Only return the answer, no introduction, explanation or additional questions.'
        );
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
    protected function stripHTML(string $v): string
    {
        $v = preg_replace('/<br\s*\/?>/i', "\n", $v);
        $v = strip_tags($v);
        $v = html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = preg_replace('/[ \t]+/', ' ', $v);
        $v = preg_replace('/\n{2,}/', "\n", $v);
        $v = preg_replace('/ +\n/', "\n", $v);
        $v = preg_replace('/\n +/', "\n", $v);
        $v = trim($v);
        return $v;
    }
    protected function cleanWhitespace(string $text): string
    {
        // Keep \n, collapse all other whitespace
        $text = preg_replace('/[ \t\r\f\v]+/', ' ', $text);

        return trim($text);
    }
}
