<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\SEC02;

use App\Modules\Identity\Domain\SEC02\ContactMaskingProjector;
use App\Modules\Identity\Domain\SEC02\ContactReadModel;

final readonly class ProjectContactReadModel
{
    public function __construct(private ContactMaskingProjector $projector) {}

    public function project(mixed $channel, mixed $contact): ContactReadModel
    {
        return $this->projector->project($channel, $contact);
    }
}
