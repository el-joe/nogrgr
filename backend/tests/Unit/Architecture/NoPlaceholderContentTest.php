<?php

use Symfony\Component\Finder\Finder;
use Tests\Support\PlaceholderContent;

/*
| Client rule: every page is fully built. No "coming soon", "under construction",
| lorem ipsum or empty stub pages anywhere in views, translations or front-end code.
*/

it('ships no coming-soon or placeholder content', function (): void {
    $root = dirname(__DIR__, 3);

    $finder = (new Finder)
        ->files()
        ->in(array_filter([
            $root.'/resources/views',
            $root.'/resources/js',
            $root.'/lang',
            $root.'/app/Modules',
        ], 'is_dir'))
        ->name(['*.blade.php', '*.js', '*.php', '*.json'])
        ->notPath(['Database/Factories', 'Database/Seeders']);

    $violations = [];

    foreach ($finder as $file) {
        $pattern = PlaceholderContent::matches($file->getContents());

        if ($pattern !== null) {
            $violations[] = $file->getRelativePathname().' matches '.$pattern;
        }
    }

    expect($violations)->toBeEmpty();
});

it('detects placeholder phrases in English and Arabic', function (string $text): void {
    expect(PlaceholderContent::matches($text))->not->toBeNull();
})->with([
    'coming soon' => '<h1>Coming Soon</h1>',
    'coming-soon class' => '<div class="coming-soon">',
    'under construction' => "'title' => 'Under construction',",
    'lorem ipsum' => 'Lorem ipsum dolor sit amet',
    'not implemented' => 'throw new Exception("Not yet implemented");',
    'ar soon label (tanween after alef)' => "'soon' => 'قريباً',",
    'ar soon label (tanween before alef)' => '<span>قريبًا</span>',
    'ar soon label (plain)' => '"قريبا"',
    'ar soon very' => "'label' => 'قريباً جداً',",
    'ar soon end of line' => 'قريباً',
    'ar coming soon' => "'title' => 'قادم قريباً',",
    'ar available soon' => 'متاح قريبًا',
    'ar will be available soon' => 'سيتوفر قريباً',
    'ar stay tuned' => 'ترقبوا قريبا',
    'ar under construction (taht)' => 'هذه الصفحة تحت الإنشاء',
    'ar under construction (qayd)' => 'قيد الإنشاء',
    'ar under development' => 'هذه الميزة قيد التطوير',
    'ar not implemented yet' => 'لم يتم تنفيذها بعد',
    'ar lorem ipsum' => 'لوريم إيبسوم دولار سيت أميت',
    'ar dummy text' => 'هذا نص تجريبي',
]);

it('allows ordinary sentences that mention "soon" or "near"', function (string $text): void {
    expect(PlaceholderContent::matches($text))->toBeNull();
})->with([
    'order arrives soon' => "'shipped' => 'ستصلك طلبيتك قريبًا',",
    'near you' => 'متاجر قريبة منك',
    'soon in english sentence' => 'Your order will arrive soon.',
    'construction category' => 'Construction tools',
    'implemented' => 'Implemented by the gateway',
]);
