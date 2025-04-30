<?php

namespace Sunnysideup\AutomatedContentManagement\Model\Api;


class Converters
{
    public static function standardised_field_type(string $type): string
    {
        // anything up to (
        return preg_replace('/\(.*$/', '', $type);
    }
}
