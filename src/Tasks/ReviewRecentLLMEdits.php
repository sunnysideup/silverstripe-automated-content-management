<?php


namespace Sunnysideup\AutomatedContentManagement\Tasks;

use SilverStripe\Dev\BuildTask;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class ReviewRecentLLMEdits extends BuildTask
{
    protected $title = 'Review Recent LLM Edits';

    protected $description = 'Review recent changes made via LLM.';

    private static $segment = 'acm-review-recent-llm-edits';
    public function run($request)
    {
        $this->editsMadeInLastFewDays();
    }

    protected function editsMadeInLastFewDays()
    {
        $days = 7;
        $date = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
        $edits = RecordProcess::get()->filter([
            'LastEdited:GreaterThan' => $date,
            'OriginalUpdated' => true,
        ])->sort('LastEdited', 'DESC');
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
