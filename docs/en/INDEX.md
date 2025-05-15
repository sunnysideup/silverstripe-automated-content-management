# tl;dr

This module allows you to connect your SilverStripe site with LLMs (Large Language Models) like ChatGPT and Claude to automate content generation and updates.

## Acknowledgement


## Also see

https://github.com/emteknetnz/silverstripe-content-ai


## Environment Configuration

Add these variables to your `.env` file:

```shell
# Required: Which LLM to use - "OpenAI" or "Claude"
SS_LLM_CLIENT_NAME="OpenAI"

# Required: Your API key
SS_LLM_CLIENT_API_KEY="your-api-key-here"

# If you want to use more than one LLMs, 
# add their short name to the end of the environment variable
SS_LLM_CLIENT_API_KEY_CLAUDE="your-api-key-here"
SS_LLM_CLIENT_API_KEY_OPENAI="your-api-key-here"

# Optional: Specific model to use (defaults provided if not set)
SS_LLM_CLIENT_MODEL="gpt-4o"  # For OpenAI
# Again, if you are using more than one LLM, 
# use their short name like this:
SS_LLM_CLIENT_MODEL_OPENAI="gpt-4o"  # For OpenAI
SS_LLM_CLIENT_MODEL_CLAUDE="claude-3-opus-20240229"  # For OpenAI

# OR
SS_LLM_CLIENT_MODEL="claude-3-opus-20240229"  # For Anthropic
```

## Environment Configuation in YML

You can also set the variables in the Silverstripe Config Layer, like this:

```yml
Sunnysideup\AutomatedContentManagement\Api\ConnectorBaseClass:
  client_name: OpenAI # OR Sunnysideup\AutomatedContentManagement\Api\Connectors\OpenAIConnector
  client_model: gpt-4o
  client_model_openai: gpt-4o
  client_model_claude: claude-3-opus-20240229

```

## Customising this project

Here is how you can set the classes and fields that are available for the CMS User:

```
Sunnysideup\AutomatedContentManagement\Model\Instruction:
  excluded_models: # include all except ones listed
    - MyClass\Foo\Bar
  included_models: # only include ones listed
    - MyOtherClass\Foo\Bar

```

### getting CMS Fields to work

You may need to add this to your `getCMSFields` method:

```php

class MyPage extends Page {
    public function getCMSFields() {
        $fields = parent::getCMSFields();
        if (SiteConfig::current_site_config()->isLLMEnabled()) {
            $obj = Injector::inst()->create(DataObjectUpdateCMSFieldsHelper::class);
            $obj->updateCMSFields($this, $fields);
        }

        return $fields;
    }
}

```

### Custom Record Processor

Use a custom record processor to change how content is processed:

`app/_config/automated-content-management.yml`

```yml
SilverStripe\Core\Injector\Injector:
Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord:
    class: MyProject\CustomRecordProcessor
```

### Creating Instructions

1. In the CMS, go to the "Automated Edits" section
2. Create a new instruction with:
    - Title: Name for this operation
    - Description: The prompt for the LLM (supports template variables)
    - ClassNameToChange: Which records to update
    - FieldToChange: Which field to modify
3. Use "Run Test" for a single record test or "ReadyToProcess" for all records

### Processing Instructions

Run the task to process queued instructions:

`vendor/bin/sake dev/tasks/acm-process-instructions`

For automated processing, set up a cron job:

```shell
* * * * * /path/to/site/vendor/bin/sake dev/tasks/acm-process-instructions
```

### Extending the Module

#### Custom LLM Connector

Create a custom LLM connector by extending the base class:

`app/_config/automated-content-management.yml`

```yml

---
Name: custom-acm-config
After: 'automated-content-management'
---
SilverStripe\Core\Injector\Injector:
Sunnysideup\AutomatedContentManagement\Api\ConnectWithLLM:
    class: MyProject\CustomLLMConnector
```

```php
<?php
namespace MyProject;

use Sunnysideup\AutomatedContentManagement\Api\ConnectWithLLM;

class CustomLLMConnector extends ConnectWithLLM
{
    // Method name must be ask{ClientName}
    protected function askMistral(string $apiKey, string $question, ?string $model = null): string
    {
        // Implementation for a different LLM service
        // Return the response text
    }
}

Customize Processing Logic

<?php
namespace MyProject;

use Sunnysideup\AutomatedContentManagement\Api\ProcessOneRecord;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class CustomRecordProcessor extends ProcessOneRecord
{
    public function recordAnswer(RecordProcess $recordProcess)
    {
        // Custom logic before processing
        parent::recordAnswer($recordProcess);
        // Custom logic after processing
    }

    protected function sendToLLM($instruction, $before)
    {
        // Customize how instructions are sent to the LLM
        $enhancedPrompt = "Follow these guidelines:\n- Be concise\n- Maintain brand voice\n\n" . $instruction;
        // Get the LLM connector and query it
        $connector = Injector::inst()->get(ConnectWithLLM::class);
        return $connector->askQuestion($enhancedPrompt);
    }
}
```



### Workflow

1. Create Instructions: Define what content needs to be generated or updated
2. Run Tests: Test with a single record to verify results
3. Queue Records: Mark instructions as ready to process all records
4. Review Changes: Review generated content in the CMS
5. Accept/Reject: Approve or reject individual changes
6. Apply Changes: Run the task to apply approved changes to records

### Template Variables

Use SilverStripe template syntax in your prompts to include record data:

Rewrite the following product description to be more engaging:
$Description

Make sure to emphasize these key features: $Features

### Security Notes

- API keys should always be stored in environment variables
- Review all AI-generated content before applying to live content
- Consider implementing content validation rules
- Restrict CMS access to the automated content management section

### Troubleshooting

- Check environment variables are correctly set
- Verify API key is valid and has appropriate permissions
- Look for error messages in the SilverStripe logs
- Test API connectivity from your server

### Included models

In order to include a model, it needs to have a method `CMSEditLink`. 
