<?php

namespace Sunnysideup\AutomatedContentManagement\Admin;

use Override;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Permission;
use SilverStripe\SiteConfig\SiteConfig;
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

    #[Override]
    public function canView($member = null): bool
    {
        return Permission::check('CMS_ACCESS_LLMEDITOR', 'any', $member);
    }

    #[Override]
    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        if (! SiteConfig::current_site_config()->IsLLMEnabled()) {
            $form->Fields()->unshift(
                LiteralField::create(
                    'Instructions',
                    '<h2>Before you start</h2>
                    <p>
                        Before editing records here,
                        please enable it <a href="/admin/settings#Root_LLM">setting your LLM Credentials in the SiteConfig</a>.
                    </p>
                    '
                )

            );
        }

        return $form;
    }
}
