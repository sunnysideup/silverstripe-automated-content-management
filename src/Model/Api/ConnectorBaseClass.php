<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Model\Api;

use Anthropic;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use OpenAI;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

abstract class ConnectorBaseClass
{
    use Configurable;
    use Injectable;

    private static string $client = '';
    private static string $model = '';

    abstract protected string $defaultModel;
    abstract protected string $shortName;

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    public static function inst(?string $client = null): static
    {
        if (! $client) {
            $client = Environment::getEnv('SS_LLM_CLIENT') ?: Config::inst()->get(static::class, 'client');
            if (! class_exists($client)) {
                $classes = ClassInfo::subclassesFor(self::class, false);
                foreach ($classes as $class) {
                    if ($class->getShortName() === $client) {
                        $client = $class;
                        break;
                    }
                }
            }
        }
        if (class_exists($client)) {
            return Injector::inst()->get($client);
        } else {
            throw new Exception('
                Client requires a class name, but --' . $client . '-- is not a class.
                You can set this in your .env file using SS_LLM_CLIENT, as static property ConnectorBaseClass::client, or pass it in as a parameter.
                You can provide the full class name, or just the short name (e.g. "OpenAIConnector" or "Sunnysideup\AutomatedContentManagement\Model\Api\Connectors\OpenAIConnector").
            ');
        }
    }

    /**
     * Run a query against the configured LLM
     */
    abstract public function runQuery(string $query, ?string $model = ''): string;

    protected function getApiKey(): string
    {
        $v = Environment::getEnv('SS_LLM_API_KEY');
        if (! $v) {
            $myVarName = Environment::getEnv('SS_LLM_API_KEY_' . strtoupper($this->getShortName()));
            $v = Environment::getEnv($myVarName);
            if (! $v) {
                throw new Exception('LLM API key (SS_LLM_API_KEY or ' . $myVarName . ') not configured in environment');
            }
        }
        return $v;
    }

    protected function getModel(?string $model = ''): string
    {
        $v = Environment::getEnv('SS_LLM_CLIENT_MODEL');
        if (! $v) {
            if (! $v) {
                $v = Environment::getEnv('SS_LLM_CLIENT_MODEL_' . $this->getShortName());
                if (! $v) {
                    $v = Config::inst()->get(static::class, 'model');
                    if (! $v) {
                        $v = Config::inst()->get(static::class, 'model_' . strtolower($this->getShortName()));
                        if (! $v) {
                            $v = $this->getDefaultModel();
                        }
                    }
                }
            }
        }
        return $v;
    }
}
