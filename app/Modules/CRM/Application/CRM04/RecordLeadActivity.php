<?php

declare(strict_types=1);

namespace App\Modules\CRM\Application\CRM04;

use App\Modules\CRM\Domain\CRM04\LeadActivity;

final class RecordLeadActivity
{
    /** @param array<string,bool> $reminders */
    public function execute(LeadActivity $activity, array &$reminders): ?LeadActivity
    {
        $key = $activity->reminderKey();
        if (isset($reminders[$key])) {
            return null;
        } $reminders[$key] = true;

        return $activity;
    }
}
