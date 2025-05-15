<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ProcessInstructions extends BuildTask
{
    protected $title = 'Process LLM Instructions';

    protected $description = 'Processes LLM instructions for automated content management.';

    protected $enabled = true;

    private static $segment = 'acm-process-instructions';

    protected $processor;

    protected $instruction = null;

    public function setInstruction(Instruction $instruction)
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function run($request)
    {
        DB::alteration_message($this->title);
        DB::alteration_message('... ' . $this->description);
        if ($request && $request->getVar('instruction')) {
            $this->instruction = Instruction::get()->byID($request->getVar('instruction'));
            if (! $this->instruction) {
                DB::alteration_message('... Instruction not found', 'error');
                return;
            }
        }
        $this->processor = Injector::inst()->get(ProcessOneRecord::class);
        $this->cleanupInstructions();
        $this->getAnswers();
        $this->updateOriginals();
        $this->cleanupRecordProcesses();
        $this->cleanupInstructions();
    }

    protected function cleanupInstructions()
    {
        DB::alteration_message('Writing all instructions ready for processing');
        $instructions = Instruction::get()->filter([
            'Completed' => false,
            'Cancelled' => false,
        ]);
        if ($this->instruction) {
            $instructions = $instructions->filter([
                'ID' => $this->instruction->ID,
            ]);
        }
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                DB::alteration_message('... Writing instruction: ' . $instruction->getTitle());
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
        if ($this->instruction) {
            $instructions = $instructions->filter([
                'ID' => $this->instruction->ID,
            ]);
        }
        foreach ($instructions as $instruction) {
            DB::alteration_message('Processing instruction: ' . $instruction->getTitle());
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
            if ($instruction->NumberOfRecordsToProcessPerBatch) {
                $list = $list->limit($instruction->NumberOfRecordsToProcessPerBatch);
            }
            foreach ($list as $recordProcess) {
                DB::alteration_message('... Processing record process: ' . $recordProcess->getRecordTitle());
                if ($recordProcess->getCanProcess()) {
                    $this->processor->recordAnswer($recordProcess);
                }
            }
        }
    }

    protected function updateOriginals()
    {
        DB::alteration_message('Updating original records');
        $recordProcesses = RecordProcess::get()->filter([
            'Completed' => true,
            'Accepted' => true,
            'IsTest' => false,
        ]);
        if ($this->instruction) {
            $recordProcesses = $recordProcesses->filter([
                'InstructionID' => $this->instruction->ID,
            ]);
        }
        foreach ($recordProcesses as $recordProcess) {
            DB::alteration_message('... Updating original record: ' . $recordProcess->getRecordTitle(), 'created');
            $this->processor->updateOriginalRecord($recordProcess);
        }
    }

    protected function cleanupRecordProcesses()
    {
        DB::alteration_message('Cleaning up record processes');
        $filters = [
            ['Instruction.Cancelled' => true],
            ['OriginalUpdated' => true],
            ['Rejected' => true],
            ['RecordID' => 0],
        ];
        if ($this->instruction) {
            $filters['InstructionID'] = $this->instruction->ID;
        }
        foreach ($filters as $filter) {
            DB::alteration_message('... Deleting by filter: ' . json_encode($filter), 'deleted');
            $recordProcesses = RecordProcess::get()->filter($filter);
            foreach ($recordProcesses as $recordProcess) {
                DB::alteration_message('... ... Deleting record process: ' . $recordProcess->ID, 'deleted');
                $recordProcess->delete();
            }
        }
        $recordProcesses = RecordProcess::get();
        if ($this->instruction) {
            $recordProcesses = $recordProcesses->filter([
                'InstructionID' => $this->instruction->ID,
            ]);
        }
        foreach ($recordProcesses as $recordProcess) {
            DB::alteration_message('... Deleting record process without record: ' . $recordProcess->ID, 'deleted');
            if (! $recordProcess->getRecord()) {
                $recordProcess->delete();
            }
        }
    }
}
