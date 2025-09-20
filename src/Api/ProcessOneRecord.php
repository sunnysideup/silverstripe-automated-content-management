<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ProcessOneRecord
{
    use Injectable;
    use Configurable;

    protected $verbose = false;

    public function setVerbose(bool $bool): self
    {
        $this->verbose = $bool;
        return $this;
    }

    private static $call_back_method_after_update = 'OnAfterProcessedByACM';

    public function recordAnswer(RecordProcess $recordProcess): bool
    {
        $question = $recordProcess->getHydratedInstructions();
        $record = $recordProcess->getRecord();
        $field = $recordProcess->Instruction->FieldToChange;
        if (! $field) {
            $this->outputMessage('NO field to change for record ID: ' . $recordProcess->RecordID . ' - ' . $recordProcess->RecordClassName, 'error');
        } elseif ($recordProcess->getCanNotProcessAnymore()) {
            $this->outputMessage('NOT processing record ID: ' . $recordProcess->RecordID . ' - ' . $recordProcess->RecordClassName . ' for field ' . $recordProcess->Instruction->FieldToChange . ' as it has already been processed or marked as skipped.', 'error');
        } elseif ($recordProcess->getCanProcess()) {
            $recordProcess->Before = $record->$field;
            $connector = ConnectorBaseClass::inst();
            $temperature = $recordProcess->Instruction()->Temperature;
            if ($temperature && is_numeric($temperature)) {
                $connector->setTemperature((float) $temperature);
            }
            $recordProcess->Started = true;
            $recordProcess->Question = $question;
            $recordProcess->LLMClient = $connector->getClientNameNice();
            $recordProcess->LLMModel = $connector->getModelNice();
            $recordProcess->write();
            $answer = $this->sendToLLM($question);
            $this->outputMessage('QUESTION: ' . PHP_EOL . $question . PHP_EOL);
            $answer = $this->removeQuotesFromAnswer($answer);
            $this->outputMessage('ANSWER: ' . $answer . PHP_EOL . " writing to record process ID: " . $recordProcess->ID . PHP_EOL);
            $recordProcess->After = $answer;
            $recordProcess->Completed = true;
            if ($recordProcess->getFindErrorsOnly()) {
                $recordProcess->ErrorFound = $recordProcess->getIsErrorAnswer($answer);
            }
            $recordProcess->write();
            return true;
        } else {
            $this->outputMessage('... NOT processing record ID: ' . $recordProcess->RecordID . ' - ' . $recordProcess->RecordClassName . ' for field ' . $recordProcess->Instruction->FieldToChange, 'error');
        }
        return false;
    }

    public function updateOriginalRecord(RecordProcess $recordProcess)
    {
        if ($recordProcess->getCanUpdateOriginalRecord()) {
            $record = $recordProcess->getRecord();
            $isPublished = $record->hasMethod('isPublished') && $record->isPublished();
            if ($isPublished) {
                if ($record->hasMethod('isModifiedOnDraft')) {
                    $isPublished = $record->isModifiedOnDraft() ? false : true;
                }
            }
            $field = $recordProcess->getFieldToChange();
            $this->outputMessage('... updating field ' . $field . ' for record ID: ' . $record->ID . ' - ' . $record->ClassName, 'changed');
            $this->outputMessage($recordProcess->getAfterDatabaseValue());
            $record->$field = $recordProcess->getAfterDatabaseValue();
            $record->write();
            if ($isPublished) {
                $record->publishSingle();
            }
            $callback = $this->config()->get('call_back_method_after_update');
            if ($callback) {
                if ($record->hasMethod($callback)) {
                    $record->$callback($recordProcess);
                } else {
                    $this->outputMessage('No method ' . $callback . ' on record ID: ' . $record->ID . ' - ' . $record->ClassName, 'error');
                }
            } else {
                $this->outputMessage('No callback set up for after update of record ID: ' . $record->ID . ' - ' . $record->ClassName, 'error');
            }
            $recordProcess->OriginalUpdated = true;
            $recordProcess->write();
        } else {
            $this->outputMessage('... NOT updating original record ID: ' . $recordProcess->RecordID . ' - ' . $recordProcess->RecordClassName . ' for field ' . $recordProcess->Instruction->FieldToChange, 'error');
        }
    }


    protected function sendToLLM(string $question): string
    {
        // This is where you would send the instruction and before value to the LLM
        // For now, we will just return a dummy response
        return ConnectorBaseClass::inst()->askQuestion($question);
    }

    protected function removeQuotesFromAnswer(string $answer): string
    {

        $value = trim($answer);

        // remove opening backticks + optional language word
        $value = preg_replace('/^```(?:\w+)?\n?/', '', $value);

        // remove closing backticks at the very end
        $value = preg_replace('/```$/', '', $value);

        return trim($value);
    }


    protected function outputMessage(string $message, string $type = 'info'): void
    {
        if (! $this->verbose) {
            return;
        }
        $message = '... ... ... ' . trim($message);
        DB::alteration_message($message, $type);
    }
}
