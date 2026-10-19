<?php

namespace App\Modules\Shared\Application;

final class ModuleCatalog
{
    /**
     * @var list<string>
     */
    private const MODULES = [
        'Identity',
        'Organization',
        'Workflow',
        'Audit',
        'Documents',
        'CRM',
        'Tasks',
        'Performance',
        'Sales',
        'Projects',
        'Inventory',
        'Delivery',
        'Quality',
        'Production',
        'Finance',
        'Employees',
        'Compensation',
        'Communications',
        'Reporting',
        'AI',
    ];

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return self::MODULES;
    }
}
