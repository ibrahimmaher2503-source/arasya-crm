<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Modules\Identity\Application\SEC04\RuntimeIdentity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class DesignSystemController
{
    public function __invoke(Request $request, RuntimeIdentity $identity): View
    {
        abort_unless(app()->environment(['local', 'testing']) === true, 404);
        $identity->fromRequest($request);

        $screen = $request->query('screen', 'catalog');
        $locale = $request->query('locale', 'ar');
        if (! is_string($screen) || ! in_array($screen, ['catalog', 'dashboard', 'orders', 'order', 'form', 'approvals'], true)) {
            abort(404);
        }
        if (! is_string($locale) || ! in_array($locale, ['ar', 'en'], true)) {
            abort(404);
        }
        app()->setLocale($locale);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:all,success,info,warning,danger'],
            'sort' => ['nullable', 'in:date_desc,amount_desc,customer_asc'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Synthetic presentation fixtures only: no database or application action is invoked.
        $fixtures = [
            ['id' => 'SO-2026-015', 'customer' => 'شركة النصر للتجارة', 'english' => 'Al Nasr Trading', 'amount' => 125000, 'status' => 'success', 'date' => '2026-10-17', 'branch' => 'الفرع الرئيسي'],
            ['id' => 'SO-2026-014', 'customer' => 'مؤسسة الإبداع', 'english' => 'Al Ibdaa', 'amount' => 89500, 'status' => 'info', 'date' => '2026-10-16', 'branch' => 'فرع الرياض'],
            ['id' => 'SO-2026-013', 'customer' => 'شركة الشرق الأوسط', 'english' => 'Middle East Co.', 'amount' => 245300, 'status' => 'warning', 'date' => '2026-10-15', 'branch' => 'فرع جدة'],
            ['id' => 'SO-2026-012', 'customer' => 'مؤسسة الأمل', 'english' => 'Al Amal', 'amount' => 27200, 'status' => 'success', 'date' => '2026-10-15', 'branch' => 'الفرع الرئيسي'],
            ['id' => 'SO-2026-011', 'customer' => 'شركة الرواد', 'english' => 'Al Rowad', 'amount' => 182400, 'status' => 'danger', 'date' => '2026-10-14', 'branch' => 'فرع جدة'],
            ['id' => 'SO-2026-010', 'customer' => 'متجر النخبة', 'english' => 'Elite Store', 'amount' => 96700, 'status' => 'success', 'date' => '2026-10-14', 'branch' => 'الفرع الرئيسي'],
        ];
        $orders = collect($fixtures)->filter(static function (array $row) use ($filters): bool {
            $search = $filters['search'] ?? '';
            $status = $filters['status'] ?? 'all';

            return ($search === '' || mb_stripos($row['customer'].' '.$row['english'].' '.$row['id'], $search) !== false)
                && ($status === 'all' || $row['status'] === $status);
        });
        $orders = match ($filters['sort'] ?? 'date_desc') {
            'amount_desc' => $orders->sortByDesc('amount'),
            'customer_asc' => $orders->sortBy($locale === 'ar' ? 'customer' : 'english'),
            default => $orders->sortByDesc('id'),
        };
        $count = $orders->count();
        $pages = max(1, (int) ceil($count / 4));
        $page = min((int) ($filters['page'] ?? 1), $pages);
        $links = [];
        for ($number = 1; $number <= $pages; $number++) {
            $links[] = ['url' => '/dev/design-system?'.http_build_query(array_merge($filters, ['screen' => 'orders', 'locale' => $locale, 'page' => $number])), 'label' => (string) $number, 'current' => $page === $number];
        }

        return view('design-system.index', [
            'designSystem' => true,
            'active' => $screen,
            'screen' => $screen,
            'locale' => $locale,
            'filters' => $filters,
            'orders' => $orders->forPage($page, 4)->values()->all(),
            'orderCount' => $count,
            'pagination' => $links,
            'title' => $locale === 'ar'
                ? ['catalog' => 'مكتبة المكونات', 'dashboard' => 'لوحة المتابعة', 'orders' => 'الطلبات', 'order' => 'تفاصيل طلب المبيعات', 'form' => 'إنشاء طلب', 'approvals' => 'الموافقات والمهام'][$screen]
                : ['catalog' => 'Component library', 'dashboard' => 'Dashboard', 'orders' => 'Orders', 'order' => 'Sales order details', 'form' => 'Create order', 'approvals' => 'Approvals and tasks'][$screen],
        ]);
    }
}
