<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;

use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ProcessOneRecord
{
    use Injectable;
    use Configurable;

    public function recordAnswer(RecordProcess $recordProcess)
    {
        $question = $recordProcess->getHydratedInstructions();
        $record = $recordProcess->getRecord();
        $field = $recordProcess->Instruction->FieldToChange;
        $recordProcess->Before = $record->$field;
        if ($recordProcess->getCanNotProcessAnymore()) {
            return;
        }
        if ($recordProcess->getCanProcess()) {
            $connector = ConnectorBaseClass::inst();
            $recordProcess->Started = true;
            $recordProcess->Question = $question;
            $recordProcess->LLMClient = $connector->getClientNameNice();
            $recordProcess->LLMModel = $connector->getModelNice();
            $recordProcess->write();
            $answer = $this->sendToLLM($question);
            $answer = $this->removeQuotesFromAnswer($answer);
            $recordProcess->After = $answer;
            $recordProcess->Completed = $answer;
            if ($recordProcess->getFindErrorsOnly()) {
                $recordProcess->ErrorFound = $recordProcess->getIsErrorAnswer($answer);
            }
            $recordProcess->write();
        }
    }

    public function updateOriginalRecord(RecordProcess $recordProcess)
    {
        if ($recordProcess->Accepted) {
            $record = $recordProcess->getRecord();
            $isPublished = $record->hasMethod('isPublished') && $record->isPublished();
            if ($isPublished) {
                if ($record->hasMethod('isModifiedOnDraft')) {
                    $isPublished = $record->isModifiedOnDraft() ? false : true;
                }
            }
            $field = $recordProcess->Instruction()->FieldToChange;
            $record->$field = $recordProcess->getAfterDatabaseValue();
            $record->write();
            if ($isPublished) {
                $record->publishSingle();
            }
            $recordProcess->OriginalUpdated = true;
            $recordProcess->write();
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
        // This is where you would clean the answer from the LLM
        // For now, we will just return the answer as is
        return preg_replace('/```[a-zA-Z0-9]+\n(.*?)```/s', '$1', $answer);
    }
}
