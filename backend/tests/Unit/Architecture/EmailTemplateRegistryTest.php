<?php

use App\Modules\Notifications\Contracts\TemplatedMailNotification;
use App\Modules\Notifications\Enums\EmailTemplateEvent;
use App\Modules\Notifications\Mail\StoreTestMail;
use App\Modules\Notifications\Mail\TemplatedMail;
use App\Modules\Notifications\Services\EmailTemplateRenderer;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/*
| Decision #118: every e-mail is an editable registry template. A Notification that can
| send mail (has toMail()) must implement TemplatedMailNotification and declare its
| EmailTemplateEvent keys; every key ships an en + ar default whose placeholders are
| declared variables with descriptions; Mailables are limited to the templated vehicle
| and the SMTP connection tests.
*/

uses(TestCase::class);

/**
 * @return list<class-string>
 */
function registryClassesUnder(string $pattern): array
{
    $classes = [];

    foreach ((new Finder)->files()->in(app_path('Modules'))->path($pattern)->name('*.php') as $file) {
        $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        $class = 'App\\Modules\\'.$relative;

        if (class_exists($class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('requires a registered template key on every notification that sends mail', function (): void {
    $offenders = [];
    $checked = 0;

    foreach (registryClassesUnder('#/Notifications/#') as $class) {
        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Notification::class) || ! $reflection->hasMethod('toMail')) {
            continue;
        }

        $checked++;

        if (! $reflection->implementsInterface(TemplatedMailNotification::class)) {
            $offenders[] = $class.' sends mail without a template key';

            continue;
        }

        $keys = $class::mailTemplates();

        if ($keys === [] || array_filter($keys, static fn (mixed $key): bool => ! $key instanceof EmailTemplateEvent) !== []) {
            $offenders[] = $class.'::mailTemplates() must list EmailTemplateEvent cases';
        }
    }

    expect($checked)->toBeGreaterThan(20)
        ->and($offenders)->toBe([]);
});

it('keeps Mailables to the templated vehicle and the SMTP connection tests', function (): void {
    $allowed = [TemplatedMail::class, StoreTestMail::class, 'App\\Modules\\Integrations\\Mail\\IntegrationTestMail'];
    $mailables = array_values(array_filter(
        registryClassesUnder('#/(Mail|Notifications)/#'),
        static fn (string $class): bool => is_subclass_of($class, Mailable::class),
    ));

    expect(array_values(array_diff($mailables, $allowed)))->toBe([]);
});

it('ships an en and ar default for every template that only uses declared variables', function (EmailTemplateEvent $event): void {
    $declared = array_keys($event->variables());

    foreach (['en', 'ar'] as $locale) {
        expect($event->hasDefault($locale))->toBeTrue("{$event->value} has no {$locale} default");

        preg_match_all(EmailTemplateRenderer::PLACEHOLDER, $event->defaultSubject($locale).' '.$event->defaultBody($locale), $matches);

        expect(array_values(array_diff(array_unique($matches[1]), $declared)))
            ->toBe([], "{$event->value} ({$locale}) uses undeclared variables");
    }
})->with(fn (): array => array_map(static fn (EmailTemplateEvent $event): array => [$event], EmailTemplateEvent::cases()));

it('documents every declared variable in en and ar', function (): void {
    $missing = [];

    foreach (EmailTemplateEvent::cases() as $event) {
        foreach (array_keys($event->variables()) as $name) {
            foreach (['en', 'ar'] as $locale) {
                if (EmailTemplateEvent::describeVariable($name, $locale) === $name) {
                    $missing[] = "{$name} ({$locale})";
                }
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});

it('labels every template key in en and ar', function (): void {
    $missing = [];

    foreach (EmailTemplateEvent::cases() as $event) {
        foreach (['en', 'ar'] as $locale) {
            $key = 'notifications::enums.email_template_event.'.$event->value;

            if (! is_array($labels = trans('notifications::enums.email_template_event', [], $locale)) || ! isset($labels[$event->value])) {
                $missing[] = "{$key} ({$locale})";
            }
        }
    }

    expect($missing)->toBe([]);
});
