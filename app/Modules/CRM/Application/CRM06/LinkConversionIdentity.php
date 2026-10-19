<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM06;

use App\Modules\CRM\Domain\CRM06\ConversionIdentity;

final class LinkConversionIdentity
{
    public function execute(ConversionIdentity $identity): ConversionIdentity
    {
        return $identity;
    }
}
