# tl;dr

This module allows you to connect your SilverStripe site with LLMs (Large Language Models) like ChatGPT and Claude to automate content generation and updates.

## Acknowledgement


## Also see

https://github.com/emteknetnz/silverstripe-content-ai


## Connecting to the LLM

The connection to ChatGPT, Claude, Gemini, etc. is handled by
[sunnysideup/ai-llm-connector](../../../ai-llm-connector/docs/en/INDEX.md).
See its documentation for environment variables, YML settings, supported models and
how to write your own connector. For example:

```yml
Sunnysideup\AiLlmConnector\Api\ConnectorBaseClass:
  client_name: OpenAI
  client_model: gpt-4o
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

See the [ai-llm-connector docs](../../../ai-llm-connector/docs/en/INDEX.md#writing-your-own-connector).

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

    protected function sendToLLM(string $question): string
    {
        // Customize how the question is sent to the LLM
        $enhancedPrompt = "Follow these guidelines:\n- Be concise\n- Maintain brand voice\n\n" . $question;
        // Get the LLM connector and query it
        return ConnectorBaseClass::inst()->askQuestion($enhancedPrompt);
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

### Rechecking answers after some time

Set "Recheck answers after (days)" on an instruction to have records processed again after that many days.

- Each recheck creates a **new** process log (`RecheckOf` points to the previous one), so history is kept.
- Rechecks ignore the record selection. A selection such as "records without a value" would otherwise never
  include records that were filled in by an earlier run.
- A log is due when it is completed, its original record was updated (for "find errors only": completed),
  it was not rejected or skipped, it is older than the interval, and it has not been rechecked yet.
- The original record is updated automatically. The exception is when the live value differs from what the
  previous run wrote (somebody edited it by hand): that answer is left for review instead.
- Run `dev/build` after upgrading (new fields: `Instruction.RecheckAfterDays`,
  `RecordProcess.RecheckOfID`, `RecordProcess.AutoAccept`).
