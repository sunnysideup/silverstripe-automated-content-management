<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataList;
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
    protected $recordProcess = null;

    private static string $delete_delay = '-90 days';

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
                DB::alteration_message('ERROR: Instruction not found', 'error');
                return;
            }
        }
        if ($request && $request->getVar('recordprocess')) {
            $this->recordProcess = RecordProcess::get()->byID($request->getVar('recordprocess'));
            if (! $this->recordProcess) {
                DB::alteration_message('ERROR: Record Process not found', 'error');
                return;
            }
        }
        $this->processor = Injector::inst()->get(ProcessOneRecord::class);
        $this->processor->setVerbose(true);
        $this->cleanupInstructions();
        $this->getAnswers();
        $this->updateOriginals();
        $this->cleanupRecordProcesses();
        $this->cleanupInstructions();
    }

    protected function cleanupInstructions()
    {
        DB::alteration_message('=== Writing all instructions ready for processing');
        $instructions = Instruction::get()->filter([
            'Completed' => false,
            'Cancelled' => false,
        ]);
        $instructions = $this->filterInstructionsByCurrentInstruction($instructions);
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                // DB::alteration_message('... Writing instruction: ' . $instruction->getTitle() . ' as it is ready to process');
                $instruction->write();
            } else {
                // DB::alteration_message('... NOT writing instruction: ' . $instruction->getTitle() . ' as it is NOT ready to process');
            }
        }
    }

    protected function getAnswers()
    {
        DB::alteration_message('=== Get Answers for all instructions ready for processing');
        $instructions = Instruction::get()->filterAny([
            'ReadyToProcess' => true,
            'RunTest' => true,
        ])->excludeAny([
            'Cancelled' => true,
            'Completed' => true,
        ]);
        $instructions = $this->filterInstructionsByCurrentInstruction($instructions);

        foreach ($instructions as $instruction) {
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle());
            if (! $instruction->RunTest) {
                $instruction->StartedProcess = true;
                $instruction->write();
            }
            $recordProcesses = $instruction->ReadyForProcessingRecords();
            // if it is a test, only include the tests.
            $recordProcesses = $recordProcesses->filter([
                'IsTest' => $instruction->RunTest,
            ]);
            if ($instruction->NumberOfRecordsToProcessPerBatch) {
                $recordProcesses = $recordProcesses->limit($instruction->NumberOfRecordsToProcessPerBatch);
            }
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                if ($this->SkipRecordProcess($recordProcess)) {
                    continue;
                }
                DB::alteration_message('... ... Processing record process: ' . $recordProcess->getRecordTitle());
                if ($recordProcess->getCanProcess()) {
                    $this->processor->recordAnswer($recordProcess);
                    DB::alteration_message('... ... ... Processed this record process', 'created');
                } else {
                    DB::alteration_message('... ... ... Cannot process this record process', 'error');
                }
            }
        }
    }

    protected function updateOrRejectAll()
    {
        DB::alteration_message('=== Process Update or Reject All selections');
        $instructions = Instruction::get()->filter([
            'Completed' => true,
            'Cancelled' => false,
        ]);
        $instructions = $this->filterInstructionsByCurrentInstruction($instructions);

        $listAcceptAll = $instructions->filter('AcceptAll', true);
        foreach ($listAcceptAll as $instruction) {

            DB::alteration_message('... Updating or rejecting all for instruction: ' . $instruction->getTitle());
            $recordProcesses = $instruction->ReviewableRecords();
            if ($instruction->NumberOfRecordsToProcessPerBatch) {
                $recordProcesses = $recordProcesses->limit($instruction->NumberOfRecordsToProcessPerBatch);
            }
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                if ($this->SkipRecordProcess($recordProcess)) {
                    continue;
                }
                DB::alteration_message('... ... Accepting record process: ' . $recordProcess->getRecordTitle(), 'created');
                $recordProcess->AcceptResult();
            }
            if (!$instruction->ReviewableRecords()->exists()) {
                $instruction->AcceptAll = false;
                $instruction->write();
            }
        }
        $listRejectAll = $instructions->filter('RejectAll', true);
        foreach ($listRejectAll as $instruction) {
            $recordProcesses = $instruction->ReviewableRecords();
            if ($instruction->NumberOfRecordsToProcessPerBatch) {
                $recordProcesses = $recordProcesses->limit($instruction->NumberOfRecordsToProcessPerBatch);
            }
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                if ($this->SkipRecordProcess($recordProcess)) {
                    continue;
                }
                DB::alteration_message('... ... Rejecting record process: ' . $recordProcess->getRecordTitle(), 'deleted');
                $recordProcess->RejectResult();
            }
            if (!$instruction->ReviewableRecords()->exists()) {
                $instruction->RejectAll = false;
                $instruction->write();
            }
        }
    }

    protected function updateOriginals()
    {
        DB::alteration_message('=== Updating original records');
        $instructions = Instruction::get()->filter([
            'Completed' => true,
            'Cancelled' => false,
        ]);
        $instructions = $this->filterInstructionsByCurrentInstruction($instructions);

        foreach ($instructions as $instruction) {
            $recordProcesses = $instruction->AcceptedRecords();
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Found ' . $recordProcesses->count() . ' record processes to update');
            if ($instruction->NumberOfRecordsToProcessPerBatch) {
                $recordProcesses = $recordProcesses->limit($instruction->NumberOfRecordsToProcessPerBatch);
            }
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                if ($this->SkipRecordProcess($recordProcess)) {
                    DB::alteration_message('... ... Skipping record process ID: ' . $this->recordProcess->ID . ' - we are processing ID: ' . $recordProcess->ID, 'error');
                    continue;
                }
                DB::alteration_message('... ... Updating original record: ' . $recordProcess->getRecordTitle(), 'created');
                $this->processor->updateOriginalRecord($recordProcess);
            }
            $instruction->write();
        }
    }

    protected function cleanupRecordProcesses()
    {
        DB::alteration_message('=== Cleaning up record processes (deleting old ones)');
        $oldFilter = ['LastEdited:LessThan' => date('Y-m-d H:i:s', strtotime($this->Config()->get('delete_delay')))];
        $filters = [
            ['Instruction.Cancelled' => true],
            ['Rejected' => true],
            ['RecordID' => 0],
            ['Skip' => 1],
        ];
        $instructions = Instruction::get()->filter([
            'Completed' => true,
            'Cancelled' => false,
        ]);
        $instructions = $this->filterInstructionsByCurrentInstruction($instructions);

        foreach ($instructions as $instruction) {
            $recordProcessesFullList = $instruction->RecordsToProcess();
            if (!$recordProcessesFullList->exists()) {
                continue;
            }
            // DB::alteration_message('... Found ' . $recordProcessesFullList->count() . ' record processes for instruction: ' . $instruction->getTitle());
            foreach ($filters as $filter) {
                // IMPORTANT!!!
                $filter += $oldFilter;
                $recordProcesses = $recordProcessesFullList->filter($filter);
                if (!$recordProcesses->exists()) {
                    continue;
                }
                DB::alteration_message('... ... Found ' . $recordProcesses->count() . ' record processes to delete with filter: ' . print_r($filter, true));
                /**
                 * @var RecordProcess $recordProcess
                 */
                foreach ($recordProcesses as $recordProcess) {
                    if ($this->SkipRecordProcess($recordProcess)) {
                        continue;
                    }
                    DB::alteration_message('... ... ... Deleting record process: ' . $recordProcess->ID, 'deleted');
                    $recordProcess->delete();
                }
            }

            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcessesFullList as $recordProcess) {
                if ($this->SkipRecordProcess($recordProcess)) {
                    continue;
                }
                if (! $recordProcess->getRecord()) {
                    DB::alteration_message('... ... Deleting record process without record: ' . $recordProcess->ID, 'deleted');
                    $recordProcess->delete();
                }
            }
        }
    }

    protected function filterInstructionsByCurrentInstruction(DataList $instructions): DataList
    {
        if ($this->instruction) {
            $instructions = $instructions->filter([
                'ID' => $this->instruction->ID,
            ]);
        }
        return $instructions;
    }

    protected function SkipRecordProcess(RecordProcess $recordProcess): bool
    {
        if ($this->recordProcess) {
            if ($recordProcess->Skip) {
                return true;
            }
            return $this->recordProcess->ID !== $recordProcess->ID;
        }
        return false;
    }
}
