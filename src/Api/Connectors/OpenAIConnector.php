<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api\Connectors;

use Exception;
use OpenAI;
use Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass;

class OpenAIConnector extends ConnectorBaseClass
{

    protected string $shortName = 'OpenAI';
    protected string $defaultModel = 'gpt-4o';

    /**
     * Send a question to OpenAI and get a response
     */
    public function askQuestion(string $question, ?string $model = ''): string
    {
        try {
            $client = OpenAI::client($this->getApiKey());
            $response = $client->chat()->create([
                'model' => $this->getModel($model),
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $question
                    ]
                ],
                'temperature' => 0.7,
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            error_log('OpenAI API error: ' . $e->getMessage());
            throw $e;
        }
    }
}
