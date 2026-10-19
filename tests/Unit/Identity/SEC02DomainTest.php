<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Application\SEC02\ProjectContactReadModel;
use App\Modules\Identity\Domain\SEC02\ContactMaskingProjector;
use App\Modules\Identity\Domain\SEC02\ContactReadModel;
use PHPUnit\Framework\TestCase;

final class SEC02DomainTest extends TestCase
{
    private const SAFE_OUTPUT = [
        'contact' => null,
        'contact_visibility' => 'hidden',
    ];

    public function test_every_declared_channel_returns_the_same_fixed_hidden_projection(): void
    {
        $projection = new ProjectContactReadModel(new ContactMaskingProjector);
        $contact = [
            'phone' => '+1 415 555 0100',
            'whatsapp' => '+1 415 555 0101',
            'email' => 'person@example.test',
        ];

        foreach (['ui', 'api', 'search', 'export', 'log', 'notification_preview', 'file_download'] as $channel) {
            self::assertSame(self::SAFE_OUTPUT, $projection->project($channel, $contact)->toArray());
        }
    }

    public function test_nested_contact_values_and_untrusted_extra_keys_are_never_retained(): void
    {
        $readModel = (new ContactMaskingProjector)->project('api', [
            'phone' => ['primary' => '+1 415 555 0100'],
            'metadata' => ['customer' => ['email' => 'person@example.test']],
            'note' => 'Call person@example.test or +14155550100',
            'number' => 14155550100,
            'encoded' => 'cGVyc29uQGV4YW1wbGUudGVzdA==',
        ]);

        self::assertSame(self::SAFE_OUTPUT, $readModel->toArray());
        self::assertSame(json_encode(self::SAFE_OUTPUT), json_encode($readModel));
        self::assertSame(self::SAFE_OUTPUT, $readModel->__debugInfo());
        self::assertSame([], get_object_vars($readModel));
    }

    public function test_unknown_or_malformed_channel_fails_closed_to_the_same_hidden_output(): void
    {
        $projector = new ContactMaskingProjector;

        foreach (['', 'raw', 'reveal', null, 42] as $channel) {
            self::assertSame(self::SAFE_OUTPUT, $projector->project($channel, ['email' => 'person@example.test'])->toArray());
        }
    }

    public function test_readmodel_cannot_be_constructed_with_contact_values(): void
    {
        $readModel = new ContactReadModel;

        self::assertSame(self::SAFE_OUTPUT, $readModel->toArray());
        self::assertSame(self::SAFE_OUTPUT, json_decode(json_encode($readModel), true));
    }

    public function test_privileged_sounding_channel_has_no_reveal_bypass(): void
    {
        $readModel = (new ContactMaskingProjector)->project('super-administrator', [
            'phone' => '+1 415 555 0100',
            'email' => 'person@example.test',
        ]);

        self::assertSame(self::SAFE_OUTPUT, $readModel->toArray());
    }
}
