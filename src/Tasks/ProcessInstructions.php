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

    private static $max_number_of_ai_interactions = 25;

    protected int $countOfAiInteractions = 0;

    protected bool $returnResultsAsArray = false;
    protected array $resultsAsArray = [];


    public function setReturnResultsAsArray(?bool $b = true)
    {
        $this->returnResultsAsArray = $b;
        return $this;
    }


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

    public function getResultsAsArray(): array
    {
        return $this->resultsAsArray;
    }

    public function run($request)
    {
        DB::query('SET SESSION wait_timeout=1200;');
        if ($request && $request->getVar('instruction')) {
            $this->instruction = Instruction::get()->byID($request->getVar('instruction'));
            if (! $this->instruction) {
                $this->log('ERROR: Instruction not found', 'deleted');
                return;
            }
        }
        if ($request && $request->getVar('recordprocess')) {
            $this->recordProcess = RecordProcess::get()->byID($request->getVar('recordprocess'));
            if (! $this->recordProcess) {
                $this->log('ERROR: Record Process not found', 'deleted');
                return;
            }
        }
        $this->processor = Injector::inst()->get(ProcessOneRecord::class);
        $this->processor->setVerbose(true);
        $this->processor->setReturnResultsAsArray($this->returnResultsAsArray);
        $this->removeObsoleteRecordsToProcess();
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
        $list = Instruction::get()->excludeAny(['Cancelled' => true, 'Locked' => true]);
        return $this->filterInstructionsByCurrentInstruction($list);
    }

    protected function getAnswersInstructions()
    {
        return $this->allInstructions()->filterAny([
            'ReadyToProcess' => true,
            'RunTest' => true,
        ])->excludeAny([
            'Locked' => true,
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
            'Locked' => false,
            'StartedProcess' => false,
            'Created:LessThan' => date('Y-m-d H:i:s', strtotime($delay)),
        ]);
    }


    protected function updateAllInstructions()
    {
        $this->log('=== Writing all instructions ready for processing');
        $instructions = $this->allInstructions();
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                $this->log('... Writing instruction: ' . $instruction->getTitle() . ' as it is ready to process ... ');
                $instruction->AddRecordProcesses(false, null, $instruction->NumberOfRecordsToProcessPerBatch);
                $instruction->write();
            } else {
                $this->log('... NOT writing instruction: ' . $instruction->getTitle() . ' as it is NOT ready to process, or has been cancelled/completed).');
            }
        }
    }

    protected function removeObsoleteRecordsToProcess()
    {
        $this->log('=== Writing all instructions ready for processing: RemoveObsoleteRecordsToProcess');
        $instructions = $this->allInstructions();
        foreach ($instructions as $instruction) {
            if ($instruction->getIsReadyForProcessing()) {
                $this->log('... Writing instruction: ' . $instruction->getTitle() . ' as it is ready to process ... ');
                $instruction->RemoveObsoleteRecordsToProcess();
            } else {
                $this->log('... NOT writing instruction: ' . $instruction->getTitle() . ' as it is NOT ready to process, or has been cancelled/completed).');
            }
        }
    }

    protected function getAnswers()
    {
        $this->log('=== Get Answers for all instructions ready for processing');

        $instructions = $this->getAnswersInstructions();
        $maxInteractions = (int) $this->Config()->get('max_number_of_ai_interactions') ?: self::$max_number_of_ai_interactions ?: 25;
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
            $this->log('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to process');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                $this->log('... ... Processing record process: ' . $recordProcess->getRecordTitle());
                if ($recordProcess->getCanProcess()) {
                    if ($recordProcess->IsInTargetRecords() === false) {
                        $recordProcess->delete();
                        $this->log('... ... ... Deleted record process as record is no longer in target records', 'deleted');
                        continue;
                    }
                    $this->countOfAiInteractions++;
                    if ($this->countOfAiInteractions > $maxInteractions) {
                        $this->log('... ... ... Stopping as max number of AI interactions reached', 'deleted');
                        break 2;
                    }
                    $outcome = $this->processor->recordAnswer($recordProcess);
                    if ($outcome) {
                        $this->log('... ... ... Processed this record process', 'created');
                    } else {
                        $this->log('... ... ... Could not process this record process', 'deleted');
                    }
                    $this->logMany($this->processor->getResultsAsArray());
                } else {
                    $this->log('... ... ... Cannot process this record process', 'deleted');
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
            $this->log('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to accept');

            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                $this->log('... ... Accepting record process: ' . $recordProcess->getRecordTitle(), 'created');
                $recordProcess->AcceptResult();
            }
        }
    }


    protected function updateOrRejectAll()
    {
        $this->log('=== Process Update or Reject All selections');

        $instructions = $this->readyToReviewAllInstructions();
        $listAcceptAll = $instructions->filter(['AcceptAll' => true]);
        foreach ($listAcceptAll as $instruction) {

            $this->log('... Updating or rejecting all for instruction: ' . $instruction->getTitle());
            $recordProcesses = $instruction->ReviewableRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            $this->log('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to accept');

            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {

                $this->log('... ... Accepting record process: ' . $recordProcess->getRecordTitle(), 'created');
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
            $this->log('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' record processes to reject');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                $this->log('... ... Rejecting record process: ' . $recordProcess->getRecordTitle(), 'deleted');
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
        $this->log('=== Updating original records');
        $instructions = $this->updateOriginalsInstructions();

        foreach ($instructions as $instruction) {
            $recordProcesses = $instruction->AcceptedRecords();
            $recordProcesses = $this->filterAndLimitRecordProcesses($recordProcesses, $instruction);
            if (! $recordProcesses->exists()) {
                continue;
            }
            $this->log('... Processing instruction: ' . $instruction->getTitle() . '... Found ' . $recordProcesses->count() . ' original records to update');
            /**
             * @var RecordProcess $recordProcess
             */
            foreach ($recordProcesses as $recordProcess) {
                $this->log('... ... Updating original record: ' . $recordProcess->getRecordTitle(), 'created');
                $this->processor->updateOriginalRecord($recordProcess);
                $this->logMany($this->processor->getResultsAsArray());
            }
            $instruction->write();
        }
    }

    protected function cleanupObsoleteInstructions()
    {
        $this->log('=== Cleaning up obsolete instructions (deleting old ones)');
        $instructions = $this->cleanupObsoleteInstructionsInstructions();
        foreach ($instructions as $instruction) {
            if ($instruction->AcceptedRecords()->exists() || $instruction->UpdatedOriginalsRecords()->exists()) {
                $this->log('... NOT deleting instruction: ' . $instruction->getTitle() . ' as it has accepted or updated records ... ', 'deleted');
                continue;
            }
            $this->log('... Deleting instruction: ' . $instruction->getTitle() . ' ... ', 'deleted');
            $instruction->delete();
        }
    }

    protected function cleanupRecordProcesses()
    {
        $this->log('=== Cleaning up record processes (deleting old ones)');
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
            $this->log('... Processing instruction: ' . $instruction->getTitle() . ' for cleanup of old record processes');
            // $this->log('... Found ' . $recordProcessesFullList->count() . ' record processes for instruction: ' . $instruction->getTitle());
            foreach ($filters as $filter) {
                $recordProcesses = $recordProcessesFullList->filter($filter);
                if (!$recordProcesses->exists()) {
                    continue;
                }
                $this->log('... ... Found ' . $recordProcesses->count() . ' record processes to delete with filter: ' . print_r($filter, true));
                /**
                 * @var RecordProcess $recordProcess
                 */
                foreach ($recordProcesses as $recordProcess) {
                    $this->log('... ... ... Deleting record process: ' . $recordProcess->ID, 'deleted');
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
        return $instructions->shuffle();
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
        $recordProcesses = $recordProcesses->shuffle();
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

    protected function logMany(array $array)
    {
        foreach ($array as $item) {
            $this->log($item['message'] ?? 'ERROR - NO MESSAGE', $item['style'] ?? '');
        }
    }
    protected function log(string $message, string $style = '')
    {
        if (strlen($message) > 100) {
            $message = substr($message, 0, 100) . '...';
        }
        if ($this->returnResultsAsArray) {
            $this->resultsAsArray[] = ['message' => $message, 'style' => $style];
        } else {
            DB::alteration_message($message, $style);
        }
    }
}
