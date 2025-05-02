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
            $recordProcess->Started = true;
            $recordProcess->write();
            $answer = $this->sendToLLM($question);
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
                $record->publishRecursive();
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
}
