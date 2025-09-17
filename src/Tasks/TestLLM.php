<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;
use Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class TestLLM extends BuildTask
{
    protected $title = 'Test LLM';

    protected $description = 'Connect to LLM and see if something comes back.';

    protected $enabled = true;

    private static $segment = 'acm-test-llm';

    protected $processor;

    public function run($request)
    {
        $obj = ConnectorBaseClass::inst();
        $question = 'Give me a random amazing fact about nature.';
        if ($request->getVar('question')) {
            $question = $request->getVar('question');
        }
        echo '<hr>';
        echo 'Question: <br /><em>' . $question . '</em>';
        echo '<hr>';
        echo '<hr>';
        echo '<hr>';
        echo 'Answer: <br /><em>' . $obj->askQuestion($question) . '</em>';
    }
}
