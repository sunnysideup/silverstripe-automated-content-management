<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model\Api;

use Exception;
use OpenAI;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use Mozex\Anthropic\Client;

class ConnectWithLLM
{
    use Configurable;
    use Injectable;

    /**
     * Run a query against the configured LLM
     */
    public function runQuery(string $query): string
    {
        $client = Environment::getEnv('SS_LLM_CLIENT')
            ?? throw new Exception('LLM client not configured in environment');

        $apiKey = Environment::getEnv('SS_LLM_API_KEY')
            ?? throw new Exception('LLM API key not configured in environment');

        $method = 'ask' . $client;

        return method_exists($this, $method)
            ? $this->$method($apiKey, $query)
            : throw new Exception('Unsupported LLM client: ' . $client);
    }

    /**
     * Send a question to OpenAI and get a response
     */
    protected function askOpenAI(string $apiKey, string $question, string $model = 'gpt-4o'): string
    {
        try {
            $client = OpenAI::client($apiKey);
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $question]],
                'temperature' => 0.7,
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            error_log('OpenAI API error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Send a question to Claude and get a response
     */
    protected function askClaude(string $apiKey, string $question, string $model = 'claude-3-opus-20240229'): string
    {
        try {
            $client = new Client($apiKey);
            $response = $client->messages()->create([
                'model' => $model,
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
