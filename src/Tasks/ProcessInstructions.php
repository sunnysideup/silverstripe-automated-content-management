<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
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
        DB::alteration_message('Writing all instructions ready for processing', 'created');
        $instructions = Instruction::get()->filter([
            'Completed' => false,
            'Cancelled' => false,
        ]);
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                DB::alteration_message('Writing instruction: ' . $instruction->getTitle(), 'created');
                $instruction->write();
            }
        }
    }

    protected function getAnswers()
    {
        $instructions = Instruction::get()->filterAny([
            'ReadyToProcess' => true,
            'RunTest' => true,
        ])->excludeAny([
            'Cancelled' => true,
            'Completed' => true,
        ]);
        foreach ($instructions as $instruction) {
            if (! $instruction->RunTest) {
                $instruction->StartedProcess = true;
                $instruction->write();
            }
            $list = RecordProcess::get()->filter([
                'Started' => false,
                'Completed' => false,
                'Skip' => false,
                'InstructionID' => $instruction->ID,
            ]);
            $list = $list->filter([
                'IsTest' => $instruction->RunTest,
            ]);
            foreach ($list as $recordProcess) {
                if ($recordProcess->getCanProcess()) {
                    $this->processor->recordAnswer($recordProcess);
                }
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
            'Instruction.Cancelled' => true,
        ]);
        foreach ($recordProcesses as $recordProcess) {
            $recordProcess->delete();
        }
        // todo: delete all records that are not needed anymore
    }
}
