<?php

namespace Sunnysideup\AutomatedContentManagement\Model\Api;

use Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;

class ConnectWithLLM
{

    use Configurable;
    use Injectable;

    public function RunQuery(string $query): string
    {
        $client = Environment::getEnv('SS_LLM_CLIENT');
        $apiKey = Environment::getEnv('SS_LLM_API_KEY');
        if ($client) {
            $method = 'ask' . $client;
            if (method_exists($this, $method)) {
                return $this->$method($apiKey, $query);
            } else {
                throw new Exception('Method not found: ' . $method);
            }
        }
        return $query;
    }
}
