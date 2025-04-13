<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Dev\BuildTask;
use Sunnysideup\AutomatedContentManagement\Model\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ProcessInstructions extends BuildTask
{
    protected $title = 'Process Instructions';

    protected $description = 'Processes instructions for automated content management.';

    protected $enabled = true;

    private static $segment = 'acm-process-instructions';

    protected $processor;

    public function run($request)
    {
        $this->processor = ProcessOneRecord::create();
        $this->cleanupInstructions();
        $this->getAnswers();
        $this->updateOriginals();
        $this->cleanupRecordProcesses();
        $this->cleanupInstructions();

        $processor = ProcessOneRecord::create();
        foreach ($recordProcesses as $recordProcess) {
            $processor->recordAnswer($recordProcess);
        }
        $recordProcesses = RecordProcess::get()->filter([
            'Completed' => true,
            'Accepted' => true,
        ]);
        foreach ($recordProcesses as $recordProcess) {
            $processor->updateOriginalRecord($recordProcess);
        }
        $recordProcesses = RecordProcess::get()->filter([
            'Completed' => true,
            'Accepted' => true,
        ]);
    }

    protected function cleanupInstructions()
    {
        $instructions = Instruction::get()->filter([
            'Completed' => false,
            'Cancelled' => false,
        ]);
        foreach ($instructions as $instruction) {
            $instruction->write();
        }
    }

    protected function getAnswers()
    {
        $recordProcesses = RecordProcess::get()->filter([
            'Started' => false,
            'Completed' => false,
        ]);
        foreach ($recordProcesses as $recordProcess) {
            $this->processor->recordAnswer($recordProcess);
        }
    }

    protected function updateOriginals()
    {
        $recordProcesses = RecordProcess::get()->filter([
            'Completed' => true,
            'Accepted' => true,
        ]);
        foreach ($recordProcesses as $recordProcess) {
            $this->processor->updateOriginalRecord($recordProcess);
        }
    }

    protected function cleanupRecordProcesses()
    {
        $recordProcesses = RecordProcess::get()->filter([
            'Cancelled' => true,
        ]);
        foreach ($recordProcesses as $recordProcess) {
            $recordProcess->delete();
        }
    }
}
