<?php

declare(strict_types=1);

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\SiteConfig\SiteConfig;
use Anthropic;
use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use OpenAI;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\AutomatedContentManagement\Api\Connectors\TestConnector;

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

    public static function is_ready(?string $client = null): bool
    {
        return true;
    }

    /**
     * @param string|null $client
     * @throws Exception
     * @return static
     */
    public static function inst(?string $client = null)
    {
        $client = static::get_client_name($client);
        return Injector::inst()->get($client);
    }

    protected static function get_client_name(?string $client = null): string
    {
        if (! $client) {
            $client = SiteConfig::current_site_config()->LLMClient;
            if (! $client) {
                $client = Environment::getEnv('SS_LLM_CLIENT_NAME');
                if (! $client) {
                    $client = Config::inst()->get(self::class, 'client_name');
                }
            }
            $client = (string) (
                Environment::getEnv('SS_LLM_CLIENT_NAME') ?:
                Config::inst()->get(self::class, 'client_name')
            );
        }
        $isValidClient = self::get_client_is_valid($client);
        if (! $isValidClient) {
            $classes = ClassInfo::subclassesFor(self::class, false);
            foreach ($classes as $class) {
                if (Injector::inst()->get($class)->getShortName() === $client) {
                    $client = $class;
                    break;
                }
            }
        }
        if (self::get_client_is_valid($client) !== true) {
            $client = TestConnector::class;
        }

        return $client;
    }

    protected static function get_client_is_valid(string $client): bool
    {
        return class_exists($client) && is_subclass_of($client, static::class);
    }

    /**
     * Run a query against the configured LLM
     */
    abstract public function askQuestion(string $query, ?string $model = ''): string;

    protected function getApiKey(): string
    {
        $v = SiteConfig::current_site_config()->LLMKey;
        if (! $v) {
            $v = Environment::getEnv('SS_LLM_CLIENT_API_KEY');
            if (! $v) {
                $myVarName = Environment::getEnv('SS_LLM_CLIENT_API_KEY_' . strtoupper($this->getShortName()));
                $v = Environment::getEnv($myVarName);
                if (! $v) {
                    throw new Exception(
                        'The LLM Api key (using SS_LLM_CLIENT_API_KEY or ' . $myVarName . ')  is not configured in this environment.'
                    );
                }
            }
        }
        return $v;
    }

    protected function getModel(?string $model = ''): string
    {
        $v = $model;
        if (! $v) {
            $v = SiteConfig::current_site_config()->LLMModel;
            if (! $v) {
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
            }
        }
        return $v;
    }

    public function getClientNameNice(): string
    {
        return $this->getShortName();
    }

    public function getModelNice(): string
    {
        return $this->getModel();
    }

    public function getClientNameList(): array
    {
        $list = [];
        $classes = ClassInfo::subclassesFor(self::class, false);
        foreach ($classes as $class) {
            $obj = Injector::inst()->get($class);
            $list[$obj->getShortName()] = $obj->getClientNameNice();
        }
        return $list;
    }

    public function getTestLink(): string
    {
        return '/dev/tasks/acm-test-llm';
    }
}
