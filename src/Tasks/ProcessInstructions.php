<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Core\Injector\Injector;
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
        $this->processor = Injector::inst()->get(ProcessOneRecord::class);
        $this->cleanupInstructions();
        $this->getAnswers();
        $this->updateOriginals();
        $this->cleanupRecordProcesses();
        $this->cleanupInstructions();
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
        $instructions = Instruction::get()->filterAny([
            'ReadyToProcess' => true,
            'RunTest' => false,
        ])->excludeAny([
            'Cancelled' => true,
            'Completed' => true,
        ]);
        foreach ($instructions as $instruction) {
            foreach (
                RecordProcess::get()->filter([
                    'Started' => false,
                    'Completed' => false,
                    'InstructionID' => $instruction->ID,
                ]) as $recordProcess
            ) {
                $this->processor->recordAnswer($recordProcess);
            }
        }
    }

    protected function updateOriginals()
    {
        $recordProcesses = RecordProcess::get()->filter([
            'Completed' => true,
            'Accepted' => true,
            'IsTest' => false,
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
