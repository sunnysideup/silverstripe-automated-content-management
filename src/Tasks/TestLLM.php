<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\Console\PolyOutput;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;

class TestLLM extends BuildTask
{
    protected string $title = 'Test LLM';

    protected $description = 'Connect to LLM and see if something comes back.';

    protected $enabled = true;

    protected static string $commandName = 'acm-test-llm';

    protected $processor;

    protected function execute(InputInterface $input, PolyOutput $output): int
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
        return 0;
    }
}
