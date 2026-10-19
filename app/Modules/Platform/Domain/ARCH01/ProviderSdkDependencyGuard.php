<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\ARCH01;

use ParseError;

final class ProviderSdkDependencyGuard
{
    /** @var list<string> */
    private const array FORBIDDEN_NAMESPACE_ROOTS = [
        'openai',
        'anthropic',
        'google',
        'aws',
        'laravel',
        'twilio',
        'vonage',
        'messagebird',
        'infobip',
        'sendgrid',
        'mailgun',
        'postmark',
    ];

    public function allowsSource(string $source): bool
    {
        try {
            $tokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! is_array($token)
                || ! in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_STRING], true)) {
                continue;
            }

            if ($this->isProviderNamespace($token[1])) {
                return false;
            }
        }

        return true;
    }

    private function isProviderNamespace(string $name): bool
    {
        $name = strtolower(ltrim($name, '\\'));

        foreach (self::FORBIDDEN_NAMESPACE_ROOTS as $root) {
            if ($name === $root || str_starts_with($name, $root.'\\')) {
                return true;
            }
        }

        return false;
    }
}
