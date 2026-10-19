<?php

declare(strict_types=1);

namespace Tests\Unit\Demo;

use App\Demo\EgyptianData;
use PHPUnit\Framework\TestCase;

final class EgyptianDataTest extends TestCase
{
    public function test_generation_is_deterministic_for_the_same_seed(): void
    {
        $first = new EgyptianData(20261018);
        $second = new EgyptianData(20261018);

        for ($index = 0; $index < 25; $index++) {
            self::assertSame($first->ulid(), $second->ulid());
            self::assertSame($first->mobile(), $second->mobile());
            self::assertSame($first->organization(), $second->organization());
            self::assertSame($first->personName(), $second->personName());
        }
    }

    public function test_different_seeds_diverge(): void
    {
        $first = new EgyptianData(1);
        $second = new EgyptianData(2);

        $firstIds = array_map(static fn (): string => $first->ulid(), range(1, 20));
        $secondIds = array_map(static fn (): string => $second->ulid(), range(1, 20));

        self::assertNotEmpty(array_diff($firstIds, $secondIds));
    }

    public function test_ulids_are_valid_crockford_identifiers(): void
    {
        $data = new EgyptianData(20261018);

        for ($index = 0; $index < 50; $index++) {
            self::assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/iD', $data->ulid());
        }
    }

    public function test_mobile_numbers_use_real_egyptian_prefixes_and_length(): void
    {
        $data = new EgyptianData(20261018);

        for ($index = 0; $index < 50; $index++) {
            self::assertMatchesRegularExpression('/^01[0125]\d{8}$/', $data->mobile());
        }
    }

    public function test_emails_are_reserved_synthetic_mailboxes(): void
    {
        $data = new EgyptianData(20261018);

        for ($index = 0; $index < 20; $index++) {
            self::assertStringEndsWith('@example.test', $data->email('نور'));
        }
    }

    public function test_locations_keep_cities_inside_their_governorate(): void
    {
        $data = new EgyptianData(20261018);
        $map = $this->governorateMap();

        for ($index = 0; $index < 200; $index++) {
            $location = $data->location();
            self::assertArrayHasKey($location['governorate'], $map);
            self::assertContains($location['city'], $map[$location['governorate']]);
        }
    }

    public function test_weighted_pick_returns_a_configured_key(): void
    {
        $data = new EgyptianData(20261018);

        for ($index = 0; $index < 100; $index++) {
            self::assertContains($data->weighted(['a' => 60, 'b' => 40]), ['a', 'b']);
        }
        self::assertSame('only', $data->weighted(['only' => 1]));
    }

    /** @return array<string, list<string>> */
    private function governorateMap(): array
    {
        return [
            'القاهرة' => ['مدينة نصر', 'المعادي', 'حلوان', 'شبرا', 'الزيتون'],
            'الجيزة' => ['الدقي', 'المهندسين', 'الهرم', '6 أكتوبر', 'الشيخ زايد'],
            'الإسكندرية' => ['سموحة', 'سيدي جابر', 'العجمي', 'برج العرب'],
            'القليوبية' => ['بنها', 'شبرا الخيمة', 'قليوب'],
            'الشرقية' => ['الزقازيق', 'العاشر من رمضان', 'بلبيس'],
            'الدقهلية' => ['المنصورة', 'ميت غمر', 'طلخا'],
            'الغربية' => ['طنطا', 'المحلة الكبرى', 'كفر الزيات'],
            'المنوفية' => ['شبين الكوم', 'منوف', 'قويسنا'],
            'كفر الشيخ' => ['كفر الشيخ', 'دسوق', 'فوة'],
            'البحيرة' => ['دمنهور', 'كفر الدوار', 'إيتاي البارود'],
            'دمياط' => ['دمياط', 'رأس البر', 'فارسكور'],
            'الإسماعيلية' => ['الإسماعيلية', 'فايد'],
            'بورسعيد' => ['بورسعيد', 'بورفؤاد'],
            'الفيوم' => ['الفيوم', 'سنورس'],
            'بني سويف' => ['بني سويف', 'الواسطى'],
            'المنيا' => ['المنيا', 'ملوي'],
            'أسيوط' => ['أسيوط', 'ديروط'],
            'سوهاج' => ['سوهاج', 'طهطا'],
            'قنا' => ['قنا', 'نجع حمادي'],
            'الأقصر' => ['الأقصر', 'إسنا'],
            'أسوان' => ['أسوان', 'كوم أمبو'],
        ];
    }
}
