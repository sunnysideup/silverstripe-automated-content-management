<?php

namespace Sunnysideup\AutomatedContentManagement\Admin;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use Sunnysideup\AutomatedContentManagement\Model\Instruction;
use Sunnysideup\AutomatedContentManagement\Model\RecordProcess;
use Sunnysideup\Selections\Model\Selection;

class AdminInstructions extends ModelAdmin
{
    private static $url_segment = 'llm-edits';

    private static $menu_title = 'LLM Edits';

    private static $menu_icon_class = 'font-icon-block-content';

    private static $managed_models = [
        Instruction::class,
        RecordProcess::class,
        Selection::class,
    ];

    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_LLMEDITOR', 'any', $member);
    }
}
