<?php

namespace Sunnysideup\AutomatedContentManagement\Api;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\PermissionProvider;

class MyPermissionProvider implements PermissionProvider
{

    use Injectable;
    use Configurable;

    private static $permissions =  [
        'CMS_ACCESS_LLMEDITOR' => [
            'name'     => 'Manage LLM Suggestions',
            'category' => 'Edting',
            'help'     => 'Edit LLM suggestions (only ADMINS can update original records directly)',
            'sort'     => 100,
        ],
    ];

    public function providePermissions()
    {
        return $this->config()->get('permissions');
    }
}
