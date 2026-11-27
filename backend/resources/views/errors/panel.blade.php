{{--
    Panel error page (ADM-120…124 Figma Admin Panel 81:97588 / 81:98238 / 81:98888 / 81:98906 /
    81:98931; VEN-096…099 Vendor Panel 82:102128 / 82:102150 / 82:102172 / 82:102194; owner portal).
    Signed-in admin / owner: the panel shell with the error card (code, title, text, actions).
    Otherwise (vendor kit #101, guests, unmatched URLs without a session): the centered card
    under the panel logo. Inputs: App\Shared\Http\PanelErrorPage::data().
--}}
@php
    /** @var array{surface: string, code: string, message: ?string, dashboardUrl: string, loginUrl: string, backUrl: ?string, retryUrl: string, shell: bool} $error */
    $error = \App\Shared\Http\PanelErrorPage::data($surface, $code, $message ?? null);
    $code = $error['code'];
    $title = __('ui.http.errors.'.$code.'_title');
    $text = filled($error['message']) ? $error['message'] : __('ui.http.errors.'.$code.'_text');
    $icons = ['403' => 'lock', '404' => 'alert-triangle', '419' => 'timer', '500' => 'server', '503' => 'settings'];
    $actions = match ($code) {
        '419' => [[$error['loginUrl'], __('ui.http.errors.sign_in'), 'lock'], [$error['retryUrl'], __('ui.http.errors.reload'), null]],
        '500', '503' => [[$error['retryUrl'], __('ui.http.errors.retry'), 'refresh'], [$error['dashboardUrl'], __('ui.http.errors.dashboard'), null]],
        default => array_values(array_filter([
            [$error['dashboardUrl'], __('ui.http.errors.dashboard'), 'dashboard'],
            $error['backUrl'] ? [$error['backUrl'], __('ui.http.errors.back'), null] : null,
        ])),
    };
@endphp
@if ($error['shell'])
    <x-layouts.panel :surface="$error['surface']" :title="$title">
        <section class="rounded-card border border-line bg-surface p-6 sm:p-10" aria-labelledby="error-title" data-error-code="{{ $code }}" data-error-surface="{{ $error['surface'] }}" data-reveal="fade-up">
            <p class="text-4xl font-semibold leading-none text-ink-brand" dir="ltr">{{ $code }}</p>
            <h1 id="error-title" class="mt-4 text-2xl font-semibold text-ink">{{ $title }}</h1>
            <p class="mt-3 max-w-xl text-sm text-ink-secondary">{{ $text }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @foreach ($actions as [$url, $label, $icon])
                    <x-ui::button :href="$url" :variant="$loop->first ? 'primary' : 'secondary'" :icon="$icon">{{ $label }}</x-ui::button>
                @endforeach
            </div>
        </section>
    </x-layouts.panel>
@else
    <x-layouts.auth :surface="$error['surface']" :title="$title">
        <main class="grid min-h-screen place-items-center px-4 py-10">
            <div class="grid w-full max-w-[460px] justify-items-center gap-6" data-reveal="fade-up">
                <x-ui::logo variant="light" :tag="__('ui.tags.'.$error['surface'])" />
                <section class="w-full rounded-card bg-surface p-6 shadow-raised sm:p-6" aria-labelledby="error-title" data-error-code="{{ $code }}" data-error-surface="{{ $error['surface'] }}">
                    <span class="grid size-11 place-items-center rounded-control bg-primary-subtle text-ink-brand">
                        <x-ui::icon :name="$icons[$code] ?? 'info'" :size="22" />
                    </span>
                    <h1 id="error-title" class="mt-5 text-2xl font-semibold text-ink"><span dir="ltr">{{ $code }}</span> · {{ $title }}</h1>
                    <p class="mt-3 text-sm text-ink-secondary">{{ $text }}</p>
                    <div class="mt-6 grid gap-3">
                        @foreach ($actions as [$url, $label, $icon])
                            <x-ui::button :href="$url" :variant="$loop->first ? 'primary' : 'ghost'" :icon="$icon" size="lg" block>{{ $label }}</x-ui::button>
                        @endforeach
                    </div>
                </section>
            </div>
        </main>
    </x-layouts.auth>
@endif
