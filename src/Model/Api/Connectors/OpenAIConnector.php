<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model\Api\Connectors;

use Anthropic;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use OpenAI;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\AutomatedContentManagement\Model\Api\ConnectorBaseClass;

class OpenAIConnector extends ConnectorBaseClass
{

    protected string $shortName = 'OpenAIConnector';
    protected string $defaultModel = 'gpt-4o';

    /**
     * Send a question to OpenAI and get a response
     */
    public function runQuery(string $question, ?string $model = ''): string
    {
        try {
            $client = OpenAI::client($this->getApiKey());
            $response = $client->chat()->create([
                'model' => $this->getModel($model),
                'messages' => [['role' => 'user', 'content' => $question]],
                'temperature' => 0.7,
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            error_log('OpenAI API error: ' . $e->getMessage());
            throw $e;
        }
    }
}
