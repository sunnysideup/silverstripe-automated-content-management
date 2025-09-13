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

    private static string $delete_delay_for_record_processes = '-90 days';

    private static string $delete_delay_for_instructions = '-21 days';

    private static $max_number_of_ai_interactions = 50;

    protected int $countOfAiInteractions = 0;

    public function setInstruction(Instruction $instruction)
    {
        $this->instruction = $instruction;
        return $this;
    }

    public function setRecordProcess(RecordProcess $recordProcess)
    {
        $this->recordProcess = $recordProcess;
        return $this;
    }

    public function run($request)
    {
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
        $this->updateAllInstructions();
        $this->getAnswers();
        $this->readyToAcceptAnswersImmediately();
        $this->updateOrRejectAll();
        $this->updateOriginals();
        $this->cleanupObsoleteInstructions();
        $this->cleanupRecordProcesses();
        $this->updateAllInstructions();
        $this->showLink();
    }

    protected function allInstructions()
    {
        $list = Instruction::get()->exclude(['Cancelled' => true]);
        return $this->filterInstructionsByCurrentInstruction($list);
    }

    protected function getAnswersInstructions()
    {
        return $this->allInstructions()->filterAny([
            'ReadyToProcess' => true,
            'RunTest' => true,
        ])->excludeAny([
            'Completed' => true,
        ]);
    }

    protected function readyToAcceptAnswersImmediatelyInstructions()
    {
        return $this->allInstructions()->filter([
            'ReadyToProcess' => true,
            'AcceptAnswersImmediately' => true,
        ]);
    }

    protected function readyToReviewAllInstructions()
    {
        return $this->allInstructions()->filter([
            'ReadyToProcess' => true,
            'Completed' => true,
        ]);
    }

    protected function updateOriginalsInstructions()
    {
        return $this->allInstructions()->filter([
            'ReadyToProcess' => true
        ]);
    }

    protected function cleanupRecordProcessesInstructions()
    {
        return $this->allInstructions()->filter([
            'Completed' => true
        ]);
    }

    protected function cleanupObsoleteInstructionsInstructions()
    {
        $delay = $this->Config()->get('delete_delay_for_instructions') ?: self::$delete_delay_for_instructions ?: '-21 days';
        return $this->allInstructions()->filter([
            'ReadyToProcess' => false,
            'Completed' => false,
            'StartedProcess' => false,
            'Created:LessThan' => date('Y-m-d H:i:s', strtotime($delay)),
        ]);
    }


    protected function updateAllInstructions()
    {
        DB::alteration_message('=== Writing all instructions ready for processing');
        $instructions = $this->allInstructions();
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                DB::alteration_message('... Writing instruction: ' . $instruction->getTitle() . ' as it is ready to process ... ');
                $instruction->write();
            } else {
                DB::alteration_message('... NOT writing instruction: ' . $instruction->getTitle() . ' as it is NOT ready to process, or has been cancelled/completed).');
            }
        }
    }

    protected function getAnswers()
    {
        DB::alteration_message('=== Get Answers for all instructions ready for processing');

        $instructions = $this->getAnswersInstructions();
        $maxInteractions = (int) $this->Config()->get('max_number_of_ai_interactions') ?: self::$max_number_of_ai_interactions ?: 100;
        foreach ($instructions as $instruction) {
            if (! $instruction->RunTest) {
                $instruction->StartedProcess = true;
                $instruction->write();
            }
            $recordProcesses = $instruction->ReadyForProcessingRecords();
            // if it is a test, only include the tests.

            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to process');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                DB::alteration_message('... ... Processing record process: ' . $recordProcess->getRecordTitle());
                if ($recordProcess->getCanProcess()) {
                    $this->countOfAiInteractions++;
                    if ($this->countOfAiInteractions > $maxInteractions) {
                        DB::alteration_message('... ... ... Stopping as max number of AI interactions reached', 'error');
                        break 2;
                    }
                    $this->processor->recordAnswer($recordProcess);
                    DB::alteration_message('... ... ... Processed this record process', 'created');
                } else {
                    DB::alteration_message('... ... ... Cannot process this record process', 'error');
                }
            }
        }
    }

    protected function readyToAcceptAnswersImmediately()
    {
        $instructions = $this->readyToAcceptAnswersImmediatelyInstructions();
        foreach ($instructions as $instruction) {
            $recordProcesses = $instruction->ReviewableRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to accept');

            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                DB::alteration_message('... ... Accepting record process: ' . $recordProcess->getRecordTitle(), 'created');
                $recordProcess->AcceptResult();
            }
        }
    }


    protected function updateOrRejectAll()
    {
        DB::alteration_message('=== Process Update or Reject All selections');

        $instructions = $this->readyToReviewAllInstructions();
        $listAcceptAll = $instructions->filter(['AcceptAll' => true]);
        foreach ($listAcceptAll as $instruction) {

            DB::alteration_message('... Updating or rejecting all for instruction: ' . $instruction->getTitle());
            $recordProcesses = $instruction->ReviewableRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to accept');

            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {

                DB::alteration_message('... ... Accepting record process: ' . $recordProcess->getRecordTitle(), 'created');
                $recordProcess->AcceptResult();
            }
            if (!$instruction->ReviewableRecords()->exists()) {
                $instruction->AcceptAll = false;
                $instruction->write();
            }
        }
        $listRejectAll = $instructions->filter(['RejectAll' => true]);
        foreach ($listRejectAll as $instruction) {
            $recordProcesses = $instruction->ReviewableRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to reject');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
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
        $instructions = $this->updateOriginalsInstructions();

        foreach ($instructions as $instruction) {
            $recordProcesses = $instruction->AcceptedRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' original records to update');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                DB::alteration_message('... ... Updating original record: ' . $recordProcess->getRecordTitle(), 'created');
                $this->processor->updateOriginalRecord($recordProcess);
            }
            $instruction->write();
        }
    }

    protected function cleanupObsoleteInstructions()
    {
        DB::alteration_message('=== Cleaning up obsolete instructions (deleting old ones)');
        $instructions = $this->cleanupObsoleteInstructionsInstructions();
        foreach ($instructions as $instruction) {
            DB::alteration_message('... Deleting instruction: ' . $instruction->getTitle() . ' ... ', 'deleted');
            $instruction->delete();
        }
    }

    protected function cleanupRecordProcesses()
    {
        DB::alteration_message('=== Cleaning up record processes (deleting old ones)');
        $delay = $this->Config()->get('delete_delay_for_record_processes') ?: self::$delete_delay_for_record_processes ?: '-90 days';
        $oldFilter = ['LastEdited:LessThan' => date('Y-m-d H:i:s', strtotime($delay))];
        $filters = [
            ['Instruction.Cancelled' => true],
            ['Rejected' => true],
            ['RecordID' => 0],
            ['Skip' => 1],
        ];
        $instructions = $this->cleanupRecordProcessesInstructions();

        foreach ($instructions as $instruction) {
            $recordProcessesFullList = $instruction->RecordsToProcess()
                ->filter($oldFilter);
            $recordProcessesFullList = $this->filterAndLimitRecordProcesses($recordProcessesFullList, $instruction);
            if (!$recordProcessesFullList->exists()) {
                continue;
            }
            DB::alteration_message('... Processing instruction: ' . $instruction->getTitle() . ' for cleanup of old record processes');
            // DB::alteration_message('... Found ' . $recordProcessesFullList->count() . ' record processes for instruction: ' . $instruction->getTitle());
            foreach ($filters as $filter) {
                $recordProcesses = $recordProcessesFullList->filter($filter);
                if (!$recordProcesses->exists()) {
                    continue;
                }
                DB::alteration_message('... ... Found ' . $recordProcesses->count() . ' record processes to delete with filter: ' . print_r($filter, true));
                /**
                 * @var RecordProcess $recordProcess
                 */
                foreach ($recordProcesses as $recordProcess) {
                    DB::alteration_message('... ... ... Deleting record process: ' . $recordProcess->ID, 'deleted');
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
        } elseif ($this->recordProcess) {
            $instructions = $instructions->filter([
                'ID' => $this->recordProcess->InstructionID,
            ]);
        }
        return $instructions->orderBy(DB::get_conn()->random());
    }


    protected function filterAndLimitRecordProcesses(DataList $recordProcesses, Instruction $instruction): DataList
    {
        if ($this->recordProcess) {
            $recordProcesses = $recordProcesses->filter([
                'ID' => $this->recordProcess->ID,
            ]);
        }
        if ($instruction->NumberOfRecordsToProcessPerBatch) {
            $recordProcesses = $recordProcesses->limit($instruction->NumberOfRecordsToProcessPerBatch);
        } else {
            $recordProcesses = $recordProcesses->limit(25);
        }
        $recordProcesses = $recordProcesses->filter([
            'IsTest' => $instruction->RunTest,
        ]);
        return $recordProcesses;
    }

    protected function showLink()
    {
        $obj = $this->instruction ?: $this->recordProcess;
        if ($obj) {
            echo '<h2>Back to <a href="/' . $obj->CMSEditLink() . '">' . $obj->getTitle() . '</a></h2>';
        } else {
            echo '<h2><a href="/admin/llm-edits/">Go to LLM Edits</a></h2>';
        }
    }
}
