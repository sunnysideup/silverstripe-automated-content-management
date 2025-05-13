<?php

namespace Sunnysideup\AutomatedContentManagement\Extensions;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;

class QuickEditController extends Controller
{
    private static $url_segment = 'admin/llm-edits-quick-edit';
    private static $allowed_actions = [
        'turnllmfunctionsonoroff' => 'LLMEdit',
        // create instruction for record
        'createinstructionforonerecord' => 'LLMEdit',
        'createinstructionforonerecordonefield' => 'LLMEdit',
        // for instruction for class
        'createinstructionforclass' => 'LLMEdit',
        'createinstructionforclassonefield' => 'LLMEdit',
        // add record to instruction
        'selectexistinginstructionforonerecord' => 'LLMEdit',
        'selectexistinginstructionforonerecordonefield' => 'LLMEdit',

        // preview results
        'preview' => 'LLMEdit',

        // action result
        'acceptresult' => 'LLMEdit',
        'declineresult' => 'LLMEdit',
    ];

    protected $record = null;
    protected $instruction = null;
    protected $recordProcess = null;
    protected $fieldName = null;

    public function init()
    {
        parent::init();

        // Add any necessary initialization code here
    }

    public function turnllmfunctionsonoroff($request)
    {
        $this->deconstructParams();
        $test  = $request->param('ID');
        if ($test === 'on') {
            $test = 1;
        } elseif ($test === 'off') {
            $test = 0;
        } else {
            $test = (int) $request->param('ID');
        }
        $zeroOrOne = $test === 1 ? true : false;
        $siteConfig = SiteConfig::current_site_config();
        $siteConfig->LLMEnabled = $zeroOrOne;
        $siteConfig->write();
        return $this->redirectBack();
    }


    public function createinstructionforclass($request)
    {
        $this->deconstructParams(false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for this record type.');
    }

    public function createinstructionforclassonefield($request)
    {
        $this->deconstructParams(false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for this record type field.');
    }

    public function createinstructionforonerecord($request)
    {
        $this->deconstructParams(false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for the record.');
    }

    public function createinstructionforonerecordonefield($request)
    {
        $this->deconstructParams(false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for the record field.');
    }


    public function selectexistinginstructionforonerecord($request)
    {
        $this->deconstructParams(false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not add record to instruction.');
    }

    public function selectexistinginstructionforonerecordonefield($request)
    {
        $this->deconstructParams(false);
        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not add record field to instruction.');
    }





    public function previewresult($request)
    {
        // Logic for saving the edited item
    }

    protected function deconstructParams(?bool $getRecordProcess = false)
    {
        $request = $this->getRequest();
        //get request params
        $instructionIDOrClassName = $request->param('ID');
        $recordID = (int) $request->param('OtherID');
        $this->fieldName = $request->param('FieldName');
        //process params
        $instructionID = 0;

        // create new instruction
        if (! intval($instructionIDOrClassName)) {
            if ($instructionIDOrClassName && class_exists($instructionIDOrClassName)) {
                $this->instruction = new Instruction();
                $this->instruction->ClassNameToChange = $instructionIDOrClassName;
                //to do -check if the field name is valid
                if ($this->fieldName) {
                    $this->instruction->FieldNameToChange = $this->fieldName;
                }
                if ($this->instruction->HasValidClassName()) {
                    $instructionID = $this->instruction->write();
                }
            }
        }
        $instructionID = (int) $instructionID;
        if ($instructionID) {
            $this->instruction = Instruction::get()->byID($instructionID);

            if ($this->instruction && $this->instruction->HasValidClassName()) {
                if ($this->fieldName) {
                    if ($this->instruction->FieldNameToChange !== $this->fieldName) {
                        $this->instruction = null;
                    }
                    if (! $this->instruction->HasValidFieldName()) {
                        $this->instruction = null;
                    }
                }
                if ($recordID) {
                    if ($getRecordProcess) {
                        if ($this->instruction) {
                            $this->recordProcess = $this->instruction->RecordsToProcess()->byID($recordID);
                        }
                    } else {
                        $className = $this->instruction->ClassNameToChange;
                        $this->record = $className::get()->byID($recordID);
                        if ($this->record && $this->instruction) {
                            $this->instruction->AddRecordsToInstruction($this->record->ID);
                        }
                    }
                }
            }
        }
    }
}
