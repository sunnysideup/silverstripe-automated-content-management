# Upgrade Guide: Migrating to Silverstripe CMS 6

This guide outlines the necessary steps to upgrade your project to be compatible with Silverstripe CMS 6, based on the changes in this module.

## ⚠️ BREAKING CHANGE: Core Dependency Updates

Your project\'s `composer.json` must be updated to require the new major versions of Silverstripe.

-   `silverstripe/framework`: `^5.0` has been updated to `^6.0`
-   `silverstripe/admin`: `^2.0` has been updated to `^3.0`

## ⚠️ BREAKING CHANGE: PHP 8 Attributes

This upgrade replaces annotations with native PHP 8 attributes. You must update your custom code that extends engine classes.

-   The `@mixin` annotation has been removed.
-   The `@var` annotation has been removed in favor of native type hints.
-   `@param` and `@return` annotations have been removed where native types are present.
-   The `@config` annotation has been removed.
-   `@property` annotations have been removed; use native properties instead.
-   Method-level annotations like `@inheritdoc` are no longer used.
-   The `@deprecated` annotation has been removed.

## 🚨 CRITICAL REVIEW REQUIRED: BuildTask Updates to `sake` Commands

All `BuildTask` classes have been refactored to align with the new command-line execution model in Silverstripe CMS 6.

-   **Execution Method**: The `run()` method has been replaced with `execute(InputInterface $input, PolyOutput $output): int`.
-   **Output**: `echo`, `print`, and `DB::alteration_message` have been replaced with `$this->output->writeln()` or `$this->output->writeForHtml()`.
-   **Request Handling**: HTTP request parameters (e.g., `$request->getVar(\'myparam\')`) have been replaced with console input options (e.g., `$input->getOption(\'myparam\')`).
-   **Command Naming**: The static `$segment` property has been replaced with `$commandName`.

**You must review all custom `BuildTask` implementations and update them to use the new `Symfony\Component\Console` components.**

-   `Sunnysideup\\AutomatedContentManagement\\Tasks\\ProcessInstructions`
-   `Sunnysideup\\AutomatedContentManagement\\Tasks\\ReviewRecentLLMEdits`
-   `Sunnysideup\\AutomatedContentManagement\\Tasks\\TestLLM`

## 🚨 CRITICAL REVIEW REQUIRED / RISKY: Removed Dependencies

The following modules have been removed from the `require` section. If your project relies on them, you must either find a compatible replacement or manually re-add them after ensuring they are compatible with Silverstripe CMS 6.

-   `sunnysideup/classes-and-fields-info`
-   `sunnysideup/selections`
-   `sunnysideup/add-casted-variables`
-   `sunnysideup/optionsetfield-grouped`

**Note**: The diff includes a `yet-to-update` section in `composer.json` which indicates these modules do not yet have a compatible stable release. You will need to monitor their repositories for updates.

## API Changes & Deprecations

### 1. `SSViewer_FromString` Removed

The deprecated `SilverStripe\View\SSViewer_FromString` class has been removed.

-   **Old Code**: `SSViewer_FromString::create($template)->process($this)`
-   **New Approach**: Use `SilverStripe\TemplateEngine\SSTemplateEngine::renderString()`

**A placeholder comment (`@@@@ START REPLACEMENT @@@@`) has been added in `src/Model/RecordProcess.php`. You must replace this with a functional implementation.**

**🚨 CRITICAL REVIEW REQUIRED**: The automated replacement in `src/Model/RecordProcess.php` is syntactically incorrect (`use SilverStripe\TemplateEngine\SSTemplateEngine::renderString();`). This must be fixed manually.

### 2. `FormField` Type Check

The check for a field\'s existence in `src/Api/DataObjectUpdateCMSFieldsHelper.php` has been made more specific.

-   **Old**: `if (! $field)`
-   **New**: `if (!$field instanceof FormField)`

### 3. Namespace Imports

The location of `ArrayList` and `ArrayData` has changed.

-   **Old**: `SilverStripe\ORM\ArrayList` and `SilverStripe\View\ArrayData`
-   **New**: `SilverStripe\Model\List\ArrayList` and `SilverStripe\Model\ArrayData`

This has been updated in `src/Control/QuickReviewController.php`.
