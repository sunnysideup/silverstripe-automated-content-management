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

    private static string $client_name = '';
    private static string $client_model = '';

    protected string $defaultModel;
    protected string $shortName;

    public function getShortName(): string
    {
        if (! $this->shortName) {
            $this->shortName = ClassInfo::shortName($this);
        }
        return $this->shortName;
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    /**
     * @param string|null $client
     * @throws Exception
     * @return static
     */
    public static function inst(?string $client = null)
    {
        if (! $client) {
            $client = (string) (
                Environment::getEnv('SS_LLM_CLIENT_NAME') ?:
                Config::inst()->get(self::class, 'client_name')
            );
            $test = class_exists($client) && is_subclass_of($client, static::class);
            if (! $test) {
                $classes = ClassInfo::subclassesFor(self::class, false);
                foreach ($classes as $class) {
                    if (Injector::inst()->get($class)->getShortName() === $client) {
                        $client = $class;
                        break;
                    }
                }
            }
        }
        if (class_exists($client) && is_subclass_of($client, static::class)) {
            return Injector::inst()->get($client);
        } else {
            throw new Exception('
                Client requires a class name, but --' . ($client ?: 'NOTHING HERE') . '-- is not a class.
                You can set this in your .env file using SS_LLM_CLIENT_NAME, as static property ConnectorBaseClass::client_name, or pass it in as a parameter.
                You can provide the full class name, or just the short name (e.g. "OpenAIConnector" or "Sunnysideup\AutomatedContentManagement\Model\Api\Connectors\OpenAIConnector").
            ');
        }
    }

    /**
     * Run a query against the configured LLM
     */
    abstract public function askQuestion(string $query, ?string $model = ''): string;

    protected function getApiKey(): string
    {
        $v = Environment::getEnv('SS_LLM_CLIENT_API_KEY');
        if (! $v) {
            $myVarName = Environment::getEnv('SS_LLM_CLIENT_API_KEY_' . strtoupper($this->getShortName()));
            $v = Environment::getEnv($myVarName);
            if (! $v) {
                throw new Exception('LLM API key (SS_LLM_CLIENT_API_KEY or ' . $myVarName . ') not configured in environment');
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
                    $v = Config::inst()->get(static::class, 'client_model');
                    if (! $v) {
                        $v = Config::inst()->get(static::class, 'client_model_' . strtolower($this->getShortName()));
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
