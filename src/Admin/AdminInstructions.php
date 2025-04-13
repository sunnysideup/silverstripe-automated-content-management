<?php

namespace Sunnysideup\AutomatedContentManagement\Admin;

use SilverStripe\Admin\ModelAdmin;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;

class AdminInstructions extends ModelAdmin
{
    private static $url_segment = 'automated-edits';

    private static $menu_title = 'Automated Edits';

    private static $managed_models = [
        Instruction::class,
        RecordProcess::class,
    ];

    private static $menu_icon_class = 'font-icon-block-content';
}
