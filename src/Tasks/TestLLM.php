<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use Override;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;

class TestLLM extends BuildTask
{
    protected string $title = 'Test LLM';

    protected static string $description = 'Connect to LLM and see if something comes back.';

    /**
     * @config
     */
    private static $is_enabled = true;

    protected static string $commandName = 'acm-test-llm';

    protected $processor;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $obj = ConnectorBaseClass::inst();
        $question = 'Give me a random amazing fact about nature.';
        if ($input->getOption('question')) {
            $question = $input->getOption('question');
        }

        $output->writeln('<hr>');
        echo 'Question: <br /><em>' . $question . '</em>';
        $output->writeln('<hr>');
        $output->writeln('<hr>');
        $output->writeln('<hr>');
        echo 'Answer: <br /><em>' . $obj->askQuestion($question) . '</em>';
        return Command::SUCCESS;
    }

    #[Override]
    public function getOptions(): array
    {
        return [new InputOption('question', null, InputOption::VALUE_NONE, 'do something specific')];
    }
}
