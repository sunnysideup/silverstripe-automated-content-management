<?php

namespace Sunnysideup\AutomatedContentManagement\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\SiteConfig\SiteConfig;
use Sunnysideup\AutomatedContentManagement\Api\DataObjectUpdateCMSFieldsHelper;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\Selections\Model\Selection;

class QuickEditController extends Controller
{

    private static $url_segment = 'llm-quick-edit';
    private static $allowed_actions = [
        'createselection' => 'CMS_ACCESS_LLMEDITOR',
        'turnllmfunctionsonoroff' => 'CMS_ACCESS_LLMEDITOR',
        'enable' => 'CMS_ACCESS_LLMEDITOR',
        'disable' => 'CMS_ACCESS_LLMEDITOR',
        // create instruction for record
        'createinstructionforonerecord' => 'CMS_ACCESS_LLMEDITOR',
        'createinstructionforonerecordonefield' => 'CMS_ACCESS_LLMEDITOR',
        // quick edit
        'createinstructionforonerecordonefieldtestnow' => 'CMS_ACCESS_LLMEDITOR',
        'createinstructionforonerecordonefieldtestnowerror' => 'CMS_ACCESS_LLMEDITOR',
        // for instruction for class
        'createinstructionforclass' => 'CMS_ACCESS_LLMEDITOR',
        'createinstructionforclassonefield' => 'CMS_ACCESS_LLMEDITOR',

        // add record to instruction
        'selectexistinginstructionforonerecord' => 'CMS_ACCESS_LLMEDITOR',
        'selectexistinginstructionforonerecordonefield' => 'CMS_ACCESS_LLMEDITOR',

        // preview results
        'preview' => 'CMS_ACCESS_LLMEDITOR',

        // action result
        'acceptresult' => 'ADMIN',
        'acceptresultandupdate' => 'ADMIN',
        'rejectresult' => 'CMS_ACCESS_LLMEDITOR',


    ];

    protected $instruction = null;
    protected ?string $providedClassName = null;
    protected $record = null;
    protected int $recordID = 0;
    protected $recordProcess = null;
    protected ?string $fieldName = null;

    public function init()
    {
        parent::init();
        // Add any necessary initialization code here
    }

    public function Link($action = null): string
    {
        return Controller::join_links(Director::baseURL(), self::config()->get('url_segment'), $action);
    }

    public function createselection($request)
    {
        $this->deconstructParams(false, false);
        if ($this->providedClassName) {
            $selection = Selection::create();
            $selection->ModelClassName = $this->providedClassName;
            $selection->write();
            return $this->redirect($selection->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for this record.');
    }

    public function turnllmfunctionsonoroff($request)
    {
        $this->deconstructParams(false, false);
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

    public function enable($request)
    {
        $this->deconstructParams(false, false);
        if ($this->providedClassName) {
            $this->addSiteConfigArrayField('LLMEnabledClassNames', $this->providedClassName);
        }
        if ($this->fieldName) {
            $this->addSiteConfigArrayField('LLMEnabledFieldNames', $this->fieldName);
        }
        if (Director::is_ajax()) {
            $html = Injector::inst()->get(DataObjectUpdateCMSFieldsHelper::class)
                ->getDescriptionForOneRecordAndField($this->record,  $this->fieldName);
            die($html);
            // // this throws a weird error.
            // $response = HTTPResponse::create();
            // $response->addHeader('Content-Type', 'text/html');
            // $response->setBody(
            //     DBHTMLText::create()->setValue($html)->forTemplate()
            // );
            // return $response;
        }
        return $this->redirectBack();
    }

    public function disable($request)
    {
        $this->deconstructParams(false, false);
        if ($this->providedClassName) {
            $this->removeSiteConfigArrayField('LLMEnabledClassNames', $this->providedClassName);
        }
        if ($this->fieldName) {
            $this->removeSiteConfigArrayField('LLMEnabledFieldNames', $this->fieldName);
        }
        if (Director::is_ajax()) {
            $html = Injector::inst()->get(DataObjectUpdateCMSFieldsHelper::class)
                ->getDescriptionForOneRecordAndField($this->record,  $this->fieldName);
            die($html);
            // this throws a weird error.
            // $response = HTTPResponse::create();
            // $response->addHeader('Content-Type', 'text/html');
            // $response->setBody(
            //     DBHTMLText::create()->setValue($html)->forTemplate()
            // );
            // return $response;
        }
        return $this->redirectBack();
    }


    public function createinstructionforclass($request)
    {
        $this->deconstructParams(false, true);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for this record type.');
    }

    public function createinstructionforclassonefield($request)
    {
        $this->deconstructParams(false, true);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for this record type field.');
    }

    public function createinstructionforonerecordonefieldtestnow($request)
    {
        return $this->runTestForInstruction($request, false);
    }

    public function createinstructionforonerecordonefieldtestnowerror($request)
    {
        return $this->runTestForInstruction($request, true);
    }

    protected function runTestForInstruction($request, $findErrorsOnly = false)
    {
        $this->deconstructParams(false, true);
        if ($this->instruction) {
            $description = $request->postVar('description');
            if ($description) {
                $this->instruction->Description = $description;
                $this->instruction->RunTest = true;
                $this->instruction->FindErrorsOnly = $findErrorsOnly;
                $this->instruction->RecordIdsToAddToSelection = $this->recordID;
                $this->instruction->write();
                sleep(1);
                $lastItem = $this->instruction->RecordsToProcess()
                    ->filter(
                        [
                            'IsTest' => true,
                            'RecordID' => $this->recordID,
                            'Completed' => true,
                            'Instruction.FindErrorsOnly' => $findErrorsOnly
                        ]
                    )
                    ->sort('ID', 'DESC')
                    ->first();
                $v = '<h2>Answer</h2>';
                if ($lastItem) {
                    if ($findErrorsOnly) {
                        if ($lastItem->getIsErrorAnswer()) {
                            $v .= '<h3 style="color: red;">ERROR</h3>';
                        } else {
                            $v .= '<h3 style="color: green;">OK</h3>';
                        }
                    } else {
                        $v .= $lastItem->getAfterHumanValue();
                    }
                } else {
                    $v .= '<h3 style="color: red;">No results found.</h3>';
                }
                die($v);
            } else {
                die('
                    <h2>ERROR</h2>
                    <p>There is no instruction provided.</p>
                    <p>Please provide an instruction in the form.</p>
                ');
            }
        }
        return $this->httpError(500, 'Could not create new instruction for this record type.');
    }


    public function createinstructionforonerecord($request)
    {
        $this->deconstructParams(false, true);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for the record.');
    }

    public function createinstructionforonerecordonefield($request)
    {
        $this->deconstructParams(false, true);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not create new instruction for the record field.');
    }


    public function selectexistinginstructionforonerecord($request)
    {
        $this->deconstructParams(false, false);

        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not add record to instruction.');
    }

    public function selectexistinginstructionforonerecordonefield($request)
    {
        $this->deconstructParams(false, false);
        if ($this->instruction) {
            return $this->redirect($this->instruction->CMSEditLink());
        }
        return $this->httpError(500, 'Could not add record field to instruction.');
    }





    public function preview($request)
    {
        $this->deconstructParams(true, false);
        if ($this->recordProcess) {
            return $this->recordProcess->renderWith(self::class . '_preview');
        } else {
            return $this->httpError(404, 'Could not find preview results.');
        }
        // Logic for saving the edited item
    }

    public function acceptresult($request)
    {
        $this->deconstructParams(true, false);
        if ($this->recordProcess) {
            $this->recordProcess->AcceptResult();
            $record = $this->recordProcess->getRecord();
            $link = $this->getBestLinkForRecord($record);
            if ($link) {
                return $this->redirect($link);
            }
            return $this->redirect($this->recordProcess->CMSEditLink());
        } else {
            return $this->httpError(404, 'Could not find results.');
        }
    }

    public function acceptresultandupdate($request)
    {
        $this->deconstructParams(true, false);
        if ($this->recordProcess) {
            $this->recordProcess->AcceptResult();
            $this->recordProcess->UpdateRecord();
            $record = $this->recordProcess->getRecord();
            $link = $this->getBestLinkForRecord($record);
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
        $this->deconstructParams(true, false);
        if ($this->recordProcess) {
            $this->recordProcess->DeclineResult();
            $record = $this->recordProcess->getRecord();
            $link = $this->getBestLinkForRecord($record);
            if ($link) {
                return $this->redirect($link);
            }
            return $this->redirect($this->recordProcess->CMSEditLink());
        } else {
            return $this->httpError(404, 'Could not find results.');
        }
    }

    /**
     * URL is:
     * llm-quick-edit/ID/OtherID/FieldName
     * ID = instruction ID or class name
     * OtherID = record ID
     * FieldName = field name
     *
     * @param mixed $getRecordProcess
     * @return void
     */
    protected function deconstructParams(?bool $getRecordProcess = false, ?bool $createInstruction = false)
    {
        $request = $this->getRequest();
        //get request params
        $instructionIDOrClassName = rawurldecode((string) $request->param('ID'));
        $instructionIDOrClassName = str_replace('-', '\\', $instructionIDOrClassName);
        $this->recordID = (int) $request->param('OtherID');
        $this->fieldName = rawurldecode((string) $request->param('FieldName'));
        //process params
        if (! intval($instructionIDOrClassName)) {
            if ($instructionIDOrClassName && class_exists($instructionIDOrClassName)) {
                $this->providedClassName = $instructionIDOrClassName;
            } else {
                $this->providedClassName = null;
            }
        }


        $instructionID = 0;
        // create new instruction
        if ($this->providedClassName && $createInstruction) {
            $this->providedClassName = $instructionIDOrClassName;
            $this->instruction = new Instruction();
            $this->instruction->ClassNameToChange = $instructionIDOrClassName;
            //to do -check if the field name is valid
            if ($this->fieldName) {
                $this->instruction->FieldToChange = $this->fieldName;
            }
            if ($this->instruction->HasValidClassName()) {
                $instructionID = $this->instruction->write();
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
                if ($this->recordID) {
                    if ($getRecordProcess) {
                        if ($this->instruction) {
                            $this->recordProcess = $this->instruction->RecordsToProcess()->byID($this->recordID);
                        }
                    } else {
                        $className = $this->instruction->ClassNameToChange;
                        $this->record = $className::get()->byID($this->recordID);
                        if ($this->record && $this->instruction) {
                            $this->instruction->AddRecordsToInstruction($this->record->ID);
                        }
                    }
                }
            }
        } else {
            if ($this->providedClassName) {
                $className = $this->providedClassName;
                $this->record = $className::get()->byID($this->recordID);
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

    protected function removeSiteConfigArrayField(string $fieldName, $value)
    {
        $siteConfig = SiteConfig::current_site_config();
        $currentValue = $siteConfig->$fieldName ?: '';
        $enabledClassNamesArray = explode(',', $currentValue);
        if (in_array($value, $enabledClassNamesArray)) {
            $enabledClassNamesArray = array_diff($enabledClassNamesArray, [$value]);
            $siteConfig->$fieldName = implode(',', $enabledClassNamesArray);
            $siteConfig->write();
        }
    }

    protected function addSiteConfigArrayField(string $fieldName, $value)
    {
        $siteConfig = SiteConfig::current_site_config();
        $currentValue = $siteConfig->$fieldName ?: '';
        $enabledClassNamesArray = explode(',', $currentValue);
        if (! in_array($value, $enabledClassNamesArray)) {
            $enabledClassNamesArray[] = $value;
            $siteConfig->$fieldName = implode(',', $enabledClassNamesArray);
            $siteConfig->write();
        }
    }

    protected function getBestLinkForRecord($record = null): ?string
    {
        if ($record && $record->hasMethod('CMSEditLink')) {
            return $record->CMSEditLink();
        }
        return null;
    }
}
