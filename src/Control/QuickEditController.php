<?php

namespace Sunnysideup\AutomatedContentManagement\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;

class QuickEditController extends Controller
{
    private static $url_segment = 'llm-quick-edit';
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
        'acceptresultandupdate' => 'LLMEdit',
        'rejectresult' => 'LLMEdit',
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





    public function preview($request)
    {
        $this->deconstructParams(true);
        if ($this->recordProcess) {
            return $this->recordProcess->renderWith(self::class . '_preview');
        } else {
            return $this->httpError(404, 'Could not find preview results.');
        }
        // Logic for saving the edited item
    }

    public function acceptresult($request)
    {
        $this->deconstructParams(true);
        if ($this->recordProcess) {
            $this->recordProcess->AcceptResult();
            return $this->redirect($this->recordProcess->CMSEditLink());
        } else {
            return $this->httpError(404, 'Could not find results.');
        }
    }

    public function acceptresultandupdate($request)
    {
        $this->deconstructParams(true);
        if ($this->recordProcess) {
            $this->recordProcess->AcceptResult();
            $this->recordProcess->UpdateRecord();
            $record = $this->recordProcess->getRecord();
            $link = $record?->CMSEditLink();
            if ($link) {
                return $this->redirect($link);
            }
            return $this->redirect($this->recordProcess->CMSEditLink());
        } else {
            return $this->httpError(404, 'Could not find preview results.');
        }
    }

    public function rejectresult($request)
    {
        $this->deconstructParams(true);
        if ($this->recordProcess) {
            $this->recordProcess->DeclineResult();
            return $this->redirect($this->recordProcess->CMSEditLink());
        } else {
            return $this->httpError(404, 'Could not find results.');
        }
    }

    protected function deconstructParams(?bool $getRecordProcess = false)
    {
        $request = $this->getRequest();
        //get request params
        $instructionIDOrClassName = rawurldecode((string) $request->param('ID'));
        $recordID = (int) $request->param('OtherID');
        $this->fieldName = rawurldecode((string) $request->param('FieldName'));
        //process params
        $instructionID = 0;

        // create new instruction
        if (! intval($instructionIDOrClassName)) {
            if ($instructionIDOrClassName && class_exists($instructionIDOrClassName)) {
                $this->instruction = new Instruction();
                $this->instruction->ClassNameToChange = $instructionIDOrClassName;
                //to do -check if the field name is valid
                if ($this->fieldName) {
                    $this->instruction->FieldToChange = $this->fieldName;
                }
                if ($this->instruction->HasValidClassName()) {
                    $instructionID = $this->instruction->write();
                }
            }
        } else {
            $instructionID = (int) $instructionIDOrClassName;
        }
        $instructionID = (int) $instructionID;
        if ($instructionID) {
            $this->instruction = Instruction::get()->byID($instructionID);

            if ($this->instruction && $this->instruction->HasValidClassName()) {
                if ($this->fieldName) {
                    if ($this->instruction->FieldToChange !== $this->fieldName) {
                        $this->instruction = null;
                    } elseif (! $this->instruction->HasValidFieldName()) {
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

    /**
     *
     * no idea why we need this, but it is here
     * @param string $url
     * @param int $code
     * @return never
     */
    public function redirect(string $url, int $code = 302): HTTPResponse
    {
        if (! strpos($url, 'http')) {
            $url = Director::absoluteURL($url);
        }
        die('
            <script>
                window.location.href = "' . $url . '";
            </script>');
        return RequestHandler::redirect($url, $code);
    }
}
