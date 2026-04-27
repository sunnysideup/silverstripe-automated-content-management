<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\Console\PolyOutput;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ReviewRecentLLMEdits extends BuildTask
{
    protected string $title = 'Review Recent LLM Edits';

    protected $description = 'Review recent changes made via LLM.';

    protected static string $commandName = 'acm-review-recent-llm-edits';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->editsMadeInLastFewDays();
        return 0;
    }

    protected function editsMadeInLastFewDays()
    {
        $days = 7;
        $date = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $edits = RecordProcess::get()->filter([
            'LastEdited:GreaterThan' => $date,
            'OriginalUpdated' => true,
        ])->sort(['LastEdited' => 'DESC']);
        echo '<h2>LLM Updates made in the last ' . $days . ' days</h2>';
        echo '<ul>';
        foreach ($edits as $edit) {
            echo '<li>
                <a href="' . $edit->Link() . '">' . $edit->getTitle() . '</a>
            </li>';
        }

        echo '</ul>';
    }
}
