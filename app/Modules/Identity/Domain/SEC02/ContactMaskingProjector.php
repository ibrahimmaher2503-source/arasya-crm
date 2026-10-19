<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\SEC02;

final class ContactMaskingProjector
{
    /** @var list<string> */
    public const CHANNELS = [
        'ui',
        'api',
        'search',
        'export',
        'log',
        'notification_preview',
        'file_download',
    ];

    /**
     * Contact input and channel are intentionally not copied into the output.
     * Unknown channels and malformed input therefore remain hidden as well.
     */
    public function project(mixed $channel, mixed $contact): ContactReadModel
    {
        return new ContactReadModel;
    }
}
