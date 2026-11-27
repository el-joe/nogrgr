<?php

namespace App\Modules\Storefront\Services\CustomThemes;

use App\Modules\Identity\Models\Admin;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Identity\Repositories\Contracts\AdminRepositoryContract;
use App\Modules\Identity\Repositories\Contracts\VendorStaffRepositoryContract;
use App\Modules\Notifications\Notifications\PanelNotification;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Storefront\Enums\CustomThemeVersionStatus;
use App\Modules\Storefront\Models\CustomTheme;
use App\Modules\Storefront\Models\CustomThemeVersion;
use App\Modules\Storefront\Notifications\CustomThemeRenderFailedNotification;
use App\Modules\Storefront\Notifications\CustomThemeReviewedNotification;
use App\Modules\Storefront\Notifications\CustomThemeSubmittedNotification;
use App\Modules\Storefront\Repositories\Contracts\CustomThemeRepositoryContract;
use App\Modules\Storefront\Services\StoreThemeService;
use App\Modules\Storefront\Support\CustomThemes\CustomThemeLimits;
use App\Modules\Storefront\Support\CustomThemes\ThemeManifest;
use App\Modules\Storefront\Support\CustomThemes\ThemePackageValidator;
use App\Shared\Services\BaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Life cycle of a store's custom Twig theme (VEN-036 / ADM-089, decision #121):
 * upload (validate → failed | draft, files extracted only when valid) → submit →
 * platform approve / reject (MAIL-034) → publish (live) / roll back to any approved
 * version → switch the custom theme on / off; vendor settings (VEN-036b) and render
 * failure reporting (SF-ALL-006). Store-side calls run in the store's tenant context;
 * review calls run in central context.
 */
class CustomThemeService extends BaseService
{
    public function __construct(
        private readonly CustomThemeRepositoryContract $themes,
        private readonly CustomThemePackageStore $store,
        private readonly ThemePackageValidator $validator,
        private readonly CustomTwigThemeRenderer $renderer,
        private readonly StoreThemeService $storeThemes,
        private readonly NotificationDispatcher $dispatcher,
        private readonly AdminRepositoryContract $admins,
    ) {}

    public function current(): ?CustomTheme
    {
        return $this->themes->forTenant($this->tenantId());
    }

    /**
     * Validate and store an uploaded ZIP as a new version (failed versions keep only their report).
     */
    public function upload(UploadedFile $file, VendorStaff $staff): CustomThemeVersion
    {
        $report = $this->validator->validate((string) $file->getRealPath());
        $manifest = $report->manifest;
        $name = Str::limit((string) ($manifest['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)), 120, '');
        $theme = $this->themes->firstOrCreateForTenant($this->tenantId(), $name !== '' ? $name : 'Custom theme');

        $version = DB::connection((new CustomThemeVersion)->getConnectionName())->transaction(fn (): CustomThemeVersion => $this->themes->createVersion($theme, [
            'version' => Str::limit((string) ($manifest['version'] ?? '0.0.0'), 32, ''),
            'status' => $report->passes() ? CustomThemeVersionStatus::Draft : CustomThemeVersionStatus::Failed,
            'name' => $name !== '' ? $name : 'Custom theme',
            'manifest' => $manifest,
            'report' => $report->toArray(),
            'checksum' => hash_file('sha256', (string) $file->getRealPath()) ?: str_repeat('0', 64),
            'size_bytes' => (int) $file->getSize(),
            'file_count' => count($report->files),
            'original_filename' => Str::limit($file->getClientOriginalName(), 190, ''),
            'uploaded_by_id' => $staff->id,
            'uploaded_by_name' => $staff->name,
        ]));

        if ($report->passes()) {
            $this->store->put($version, $report->contents);
        }

        $this->prune($theme);

        return $version;
    }

    public function submit(CustomThemeVersion $version, ?string $note): CustomThemeVersion
    {
        if (! $version->status->canSubmit()) {
            throw ValidationException::withMessages(['version' => __('content::custom_themes.errors.cannot_submit')]);
        }

        $this->themes->updateVersion($version, [
            'status' => CustomThemeVersionStatus::PendingReview,
            'submit_note' => $note !== null && trim($note) !== '' ? Str::limit(trim($note), 1000, '') : null,
            'submitted_at' => now(),
            'reviewed_at' => null, 'reviewed_by_id' => null, 'reviewed_by_name' => null, 'review_note' => null,
        ]);

        $notification = new CustomThemeSubmittedNotification('default', [
            'store' => (string) (tenant('name') ?? ''),
            'theme' => $version->name,
            'version' => $version->version,
        ], route('admin.storefront.theme-reviews.show', $version->id));

        // Platform admins and their permissions live in the central database.
        $notifyAdmins = function () use ($notification): void {
            $admins = $this->admins->activeWithPermission('storefront.theme-reviews.manage');

            if ($admins->isNotEmpty()) {
                $this->dispatcher->send($admins, $notification);
            }
        };

        tenancy()->initialized ? tenancy()->central($notifyAdmins) : $notifyAdmins();

        return $version;
    }

    /**
     * Withdraw a pending submission (back to draft).
     */
    public function withdraw(CustomThemeVersion $version): CustomThemeVersion
    {
        if ($version->status !== CustomThemeVersionStatus::PendingReview) {
            throw ValidationException::withMessages(['version' => __('content::custom_themes.errors.not_pending')]);
        }

        return $this->themes->updateVersion($version, ['status' => CustomThemeVersionStatus::Draft, 'submitted_at' => null]);
    }

    public function approve(CustomThemeVersion $version, Admin $admin, ?string $note): CustomThemeVersion
    {
        return $this->review($version, $admin, CustomThemeVersionStatus::Approved, $note);
    }

    public function reject(CustomThemeVersion $version, Admin $admin, string $note): CustomThemeVersion
    {
        return $this->review($version, $admin, CustomThemeVersionStatus::Rejected, $note);
    }

    /**
     * Make an approved version live (also "roll back" to an older approved version) and switch the custom theme on.
     */
    public function publish(CustomThemeVersion $version): CustomTheme
    {
        if ($version->status !== CustomThemeVersionStatus::Approved) {
            throw ValidationException::withMessages(['version' => __('content::custom_themes.errors.not_approved')]);
        }

        $theme = $version->theme;
        $previous = $theme->live_version_id;
        $theme->forceFill(['live_version_id' => $version->id, 'is_active' => true, 'name' => $version->name, 'last_render_error' => null, 'last_render_error_at' => null])->save();
        $this->themes->updateVersion($version, ['published_at' => now()]);

        // A fresh compile of the new live version; the old one's cache is not needed any more.
        $this->renderer->invalidate($version);

        if ($previous !== null && $previous !== $version->id && ($old = $this->themes->findVersion($previous)) !== null) {
            $this->renderer->invalidate($old);
        }

        $this->storeThemes->forget();

        return $theme;
    }

    /**
     * Switch the storefront between the custom theme (live version) and the built-in theme.
     */
    public function setActive(bool $active): CustomTheme
    {
        $theme = $this->current() ?? throw ValidationException::withMessages(['theme' => __('content::custom_themes.errors.no_live')]);

        if ($active && $theme->live_version_id === null) {
            throw ValidationException::withMessages(['theme' => __('content::custom_themes.errors.no_live')]);
        }

        $theme->forceFill(['is_active' => $active])->save();
        $this->storeThemes->forget();

        return $theme;
    }

    public function delete(CustomThemeVersion $version): void
    {
        if ($version->theme->live_version_id === $version->id || $version->status === CustomThemeVersionStatus::PendingReview) {
            throw ValidationException::withMessages(['version' => __('content::custom_themes.errors.cannot_delete')]);
        }

        $this->store->delete($version);
        $this->themes->deleteVersion($version);
    }

    /**
     * Save VEN-036b settings against the live (else newest approved / valid) version's schema.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|int|bool>
     */
    public function saveSettings(CustomTheme $theme, array $input): array
    {
        $version = $this->settingsVersion($theme) ?? throw ValidationException::withMessages(['theme' => __('content::custom_themes.errors.no_settings')]);
        $errors = [];
        $values = [];

        foreach ((array) ($version->manifest['settings'] ?? []) as $setting) {
            $raw = $input[$setting['id']] ?? match ($setting['type']) {
                'toggle' => false,
                'text', 'textarea' => '', // empty fields arrive as null (ConvertEmptyStringsToNull)
                default => null,
            };
            $value = ThemeManifest::coerce($setting, $raw);

            if ($value === null) {
                $errors['settings.'.$setting['id']] = __('content::custom_themes.errors.setting_invalid', ['label' => $setting['label'][app()->getLocale() === 'ar' ? 'ar' : 'en']]);

                continue;
            }

            $values[$setting['id']] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $theme->forceFill(['settings' => $values + (array) $theme->settings])->save();

        return $values;
    }

    public function settingsVersion(CustomTheme $theme): ?CustomThemeVersion
    {
        if ($theme->live_version_id !== null && ($live = $this->themes->findVersion($theme->live_version_id)) !== null) {
            return $live;
        }

        return $this->themes->versions($theme)->first(static fn (CustomThemeVersion $version): bool => $version->status !== CustomThemeVersionStatus::Failed);
    }

    /**
     * A render of the live theme failed (the storefront already fell back): log, remember
     * the error on the theme and tell the store owners at most once an hour.
     */
    public function reportRenderFailure(CustomThemeVersion $version, Throwable $error): void
    {
        $message = Str::limit($error->getMessage(), 480, '…');
        Log::warning('Custom theme render failed', ['tenant' => $version->tenant_id, 'version' => $version->id, 'error' => $message]);

        try {
            $theme = $version->theme;

            if ($theme->live_version_id !== $version->id) {
                return; // a preview: the vendor sees the fallback themselves
            }

            // At most one notification an hour per theme (throttle kept on the theme row: parallel / multi-host safe).
            $notify = $theme->last_render_error_at === null || $theme->last_render_error_at->lt(now()->subHour());
            $theme->forceFill(['last_render_error' => $message, 'last_render_error_at' => $notify ? now() : $theme->last_render_error_at])->saveQuietly();

            if ($notify) {
                $this->notifyOwners($version->tenant_id, static fn (): PanelNotification => new CustomThemeRenderFailedNotification('default', [
                    'theme' => $version->name,
                    'version' => $version->version,
                    'error' => Str::limit($message, 200, '…'),
                ], vendor_route('store.custom-themes.index')));
            }
        } catch (Throwable $notifyError) {
            Log::warning('Custom theme failure report failed', ['error' => $notifyError->getMessage()]);
        }
    }

    private function review(CustomThemeVersion $version, Admin $admin, CustomThemeVersionStatus $status, ?string $note): CustomThemeVersion
    {
        if ($version->status !== CustomThemeVersionStatus::PendingReview) {
            throw ValidationException::withMessages(['version' => __('content::custom_themes.errors.not_pending')]);
        }

        $this->themes->updateVersion($version, [
            'status' => $status,
            'reviewed_by_id' => $admin->id,
            'reviewed_by_name' => $admin->name,
            'review_note' => $note !== null && trim($note) !== '' ? Str::limit(trim($note), 2000, '') : null,
            'reviewed_at' => now(),
        ]);

        $variant = $status === CustomThemeVersionStatus::Approved ? 'approved' : 'rejected';
        $this->notifyOwners($version->tenant_id, static fn (): PanelNotification => new CustomThemeReviewedNotification($variant, [
            'theme' => $version->name,
            'version' => $version->version,
            'note' => (string) ($version->review_note ?? '—'),
        ], vendor_route('store.custom-themes.index')));

        return $version;
    }

    /**
     * Notify the store's active owners, in the store's tenant context.
     *
     * @param  callable(): PanelNotification  $make
     */
    private function notifyOwners(int $tenantId, callable $make): void
    {
        $send = static function () use ($make): void {
            $owners = app(VendorStaffRepositoryContract::class)->activeOwners();

            if ($owners->isNotEmpty()) {
                Notification::send($owners, $make());
            }
        };

        if (tenancy()->initialized && (int) tenant()?->getTenantKey() === $tenantId) {
            $send();

            return;
        }

        Tenant::query()->find($tenantId)?->run($send);
    }

    /**
     * Keep at most MAX_VERSIONS versions: the oldest that are not live / pending go first.
     */
    private function prune(CustomTheme $theme): void
    {
        $versions = $this->themes->versions($theme);

        foreach ($versions->slice(CustomThemeLimits::MAX_VERSIONS) as $version) {
            if ($version->id !== $theme->live_version_id && $version->status !== CustomThemeVersionStatus::PendingReview) {
                $this->store->delete($version);
                $this->themes->deleteVersion($version);
            }
        }
    }

    private function tenantId(): int
    {
        return (int) (tenant()?->getTenantKey() ?? throw ValidationException::withMessages(['store' => 'No store context.']));
    }
}
