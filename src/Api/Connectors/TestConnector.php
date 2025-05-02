<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api\Connectors;

use Exception;
use OpenAI;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;

class TestConnector extends ConnectorBaseClass
{

    protected string $shortName = 'Test';
    protected string $defaultModel = 'test-model';

    /**
     * Send a question
     */
    public function askQuestion(string $question, ?string $model = ''): string
    {
        return 'This is a test response from the TestConnector.';
    }
}
