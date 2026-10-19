<?php

declare(strict_types=1);

namespace App\Demo;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic Egyptian locale data for the synthetic demo dataset.
 *
 * Every value is generated from a fixed random seed so the same size preset
 * always produces the same dataset. Contact details are synthetic: reserved
 * `example.test` mailboxes, invented phone numbers, and no national IDs.
 */
final class EgyptianData
{
    private const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** @var list<string> */
    private const MALE_NAMES = [
        'أحمد', 'محمد', 'محمود', 'مصطفى', 'خالد', 'عمر', 'يوسف', 'حسن', 'كريم', 'طارق',
        'إبراهيم', 'شريف', 'عمرو', 'هشام', 'وليد', 'أسامة', 'رامي', 'سامح', 'ياسر', 'منصور',
        'علاء', 'ممدوح', 'عادل', 'جمال', 'فادي', 'بلال', 'زياد', 'ماهر', 'صلاح', 'رمضان',
    ];

    /** @var list<string> */
    private const FEMALE_NAMES = [
        'منى', 'هند', 'سارة', 'دينا', 'شيماء', 'أميرة', 'نورهان', 'ياسمين', 'سلمى', 'رنا',
        'هبة', 'إيمان', 'سمية', 'أسماء', 'نجلاء', 'داليا', 'مروة', 'رانيا', 'هالة', 'غادة',
    ];

    /** @var list<string> */
    private const FAMILY_NAMES = [
        'عبد الرحمن', 'السيد', 'الشريف', 'حسين', 'عبد الله', 'فؤاد', 'رمضان', 'الزناتي', 'الإبياري', 'الفقي',
        'منصور', 'الشناوي', 'عبد العزيز', 'البدري', 'الغزالي', 'سليمان', 'خيري', 'القاضي', 'المليجي', 'حماد',
        'رمزى', 'درويش', 'عطية', 'شلبي', 'المرسي', 'الطوخي', 'سالم', 'بدر', 'النجار', 'الحداد',
    ];

    /**
     * Governorate => real cities/towns inside it. Addresses are always built
     * from a single governorate/city pair so no city is placed in the wrong
     * governorate.
     *
     * @var array<string, list<string>>
     */
    private const GOVERNORATES = [
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

    /**
     * Landline area codes keyed by governorate (Cairo/Giza share code 2).
     *
     * @var array<string, string>
     */
    private const AREA_CODES = [
        'القاهرة' => '2', 'الجيزة' => '2', 'الإسكندرية' => '3', 'القليوبية' => '13',
        'الشرقية' => '55', 'الدقهلية' => '50', 'الغربية' => '40', 'المنوفية' => '48',
        'كفر الشيخ' => '47', 'البحيرة' => '45', 'دمياط' => '57', 'الإسماعيلية' => '64',
        'بورسعيد' => '66', 'الفيوم' => '84', 'بني سويف' => '82', 'المنيا' => '86',
        'أسيوط' => '88', 'سوهاج' => '93', 'قنا' => '95', 'الأقصر' => '95', 'أسوان' => '97',
    ];

    /** @var list<string> */
    private const INDUSTRY_ACTIVITIES = [
        'للثروة السمكية', 'للاستزراع السمكي', 'للمشروعات الزراعية', 'للتنمية الزراعية',
        'للمقاولات العامة', 'للتوريدات الهندسية', 'للبلاستيك والصناعات', 'لتبطين الأحواض',
        'للري الحديث', 'للمستلزمات الزراعية', 'للتصنيع الزراعي', 'للاستصلاح الزراعي',
        'للأعلاف والثروة الحيوانية', 'للملاحات ومزارع الأسماك',
    ];

    /** @var list<string> */
    private const ORG_PREFIXES = [
        'شركة', 'مؤسسة', 'مجموعة', 'مزارع', 'مشروعات', 'مصنع', 'التعاونية', 'الوطنية',
        'هيئة', 'إدارة',
    ];

    /** @var list<string> */
    private const JOB_TITLES = [
        'مدير عام', 'مدير مبيعات', 'مندوب مبيعات', 'مهندس زراعي', 'مهندس تنفيذ',
        'مشرف ميداني', 'فني لحام', 'عامل إنتاج', 'محاسب', 'أخصائي موارد بشرية',
        'مسؤول مشتريات', 'مشرف مخازن', 'أخصائي تسويق رقمي', 'مهندس جودة', 'سكرتير تنفيذي',
    ];

    private const SOURCES = [
        'موقع إلكتروني', 'معرض', 'إحالة عميل', 'واتساب', 'مكالمة مباشرة', 'فيسبوك',
        'معرض زراعي', 'توصية', 'بريد إلكتروني', 'زيارة ميدانية',
    ];

    private const STAGES = [
        'عميل محتمل جديد', 'تم التواصل', 'عرض سعر مُقدم', 'مفاوضات', 'مؤهل للتحويل',
    ];

    private const OUTCOMES = [
        'تم الرد', 'لم يتم الرد', 'مهتم', 'غير مهتم', 'بانتظار القرار', 'تم إرسال العرض',
    ];

    private Randomizer $random;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    /** 26-character Crockford ULID-shaped identifier, deterministic per seed. */
    public function ulid(): string
    {
        $id = '';
        for ($index = 0; $index < 26; $index++) {
            $id .= self::CROCKFORD[$this->random->getInt(0, 31)];
        }

        return $id;
    }

    /** @param list<string> $values */
    public function pick(array $values): string
    {
        return $values[$this->random->getInt(0, count($values) - 1)];
    }

    /** @phpstan-impure */
    public function int(int $min, int $max): int
    {
        return $this->random->getInt($min, $max);
    }

    /** Weighted pick: return the first bucket whose cumulative weight exceeds a roll. */
    /** @param array<int|string, int> $weights */
    public function weighted(array $weights): string
    {
        $total = array_sum($weights);
        $roll = $this->random->getInt(1, max(1, $total));
        $cumulative = 0;
        foreach ($weights as $value => $weight) {
            $cumulative += $weight;
            if ($roll <= $cumulative) {
                return (string) $value;
            }
        }

        return (string) array_key_first($weights);
    }

    public function personName(): string
    {
        $first = $this->random->getInt(0, 1) === 0 ? $this->pick(self::MALE_NAMES) : $this->pick(self::FEMALE_NAMES);
        $family = $this->pick(self::FAMILY_NAMES);

        return $this->random->getInt(0, 2) === 0 ? $first.' '.$family.' '.$this->pick(self::FAMILY_NAMES) : $first.' '.$family;
    }

    public function contactName(): string
    {
        return $this->pick(self::MALE_NAMES).' '.$this->pick(self::FAMILY_NAMES);
    }

    /** @return array{governorate: string, city: string} */
    public function location(): array
    {
        $governorate = $this->pick(array_keys(self::GOVERNORATES));

        return ['governorate' => $governorate, 'city' => $this->pick(self::GOVERNORATES[$governorate])];
    }

    public function addressFragment(string $governorate, string $city): string
    {
        $parts = ['شارع '.$this->pick(['الجمهورية', 'الحرية', 'النيل', 'المحطة', 'الجلاء', 'الثورة', 'المشروع', 'المستشفى'])];
        $parts[] = 'مبنى '.$this->random->getInt(1, 120);
        $parts[] = $city;
        $parts[] = 'محافظة '.$governorate;

        return implode('، ', $parts);
    }

    /** Egyptian mobile number: 11 digits starting with a valid 010/011/012/015 prefix. */
    public function mobile(): string
    {
        $prefix = $this->pick(['010', '011', '012', '015']);
        $digits = '';
        for ($index = 0; $index < 8; $index++) {
            $digits .= (string) $this->random->getInt(0, 9);
        }

        return $prefix.$digits;
    }

    public function landline(string $governorate): string
    {
        $area = self::AREA_CODES[$governorate] ?? '2';
        $length = $area === '2' ? 8 : 7;
        $digits = '';
        for ($index = 0; $index < $length; $index++) {
            $digits .= (string) $this->random->getInt(0, 9);
        }

        return '0'.$area.$digits;
    }

    public function email(string $localHint, string $domain = 'example.test'): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '', $localHint);
        $slug = $slug === null || $slug === '' ? 'contact' : mb_strtolower($slug);

        return $slug.$this->random->getInt(1000, 99999).'@'.$domain;
    }

    public function organization(): string
    {
        $location = $this->location();

        return $this->pick(self::ORG_PREFIXES).' '.$location['city'].' '.$this->pick(self::INDUSTRY_ACTIVITIES);
    }

    public function jobTitle(): string
    {
        return $this->pick(self::JOB_TITLES);
    }

    public function source(): string
    {
        return $this->pick(self::SOURCES);
    }

    /** @return list<string> */
    public function stages(): array
    {
        return self::STAGES;
    }

    /** @return list<string> */
    public function outcomes(): array
    {
        return self::OUTCOMES;
    }
}
