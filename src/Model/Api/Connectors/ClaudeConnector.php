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

class ClaudeConnector extends ConnectorBaseClass
{
    protected string $shortName = 'Claude';
    protected string $defaultModel = 'claude-3-opus-20240229';

    /**
     * Send a question to Claude and get a response
     */
    public function askQuestion(string $question, ?string $model = 'claude-3-opus-20240229'): string
    {
        try {
            $client = Anthropic::client($this->getApiKey());
            $response = $client->messages()->create([
                'model' => $this->getModel($model),
                'max_tokens' => 1000,
                'messages' => [['role' => 'user', 'content' => $question]],
                'temperature' => 0.7,
            ]);


            return $response->content[0]->text;
        } catch (Exception $e) {
            error_log('Anthropic API error: ' . $e->getMessage());
            throw $e;
        }
    }
}
