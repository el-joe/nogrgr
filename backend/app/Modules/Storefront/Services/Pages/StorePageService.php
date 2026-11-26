<?php

namespace App\Modules\Storefront\Services\Pages;

use App\Modules\Notifications\Support\EmailHtmlSanitizer;
use App\Modules\Storefront\DTOs\StorePageData;
use App\Modules\Storefront\Enums\StorePageType;
use App\Modules\Storefront\Enums\SystemStorePage;
use App\Modules\Storefront\Models\StorePage;
use App\Modules\Storefront\Repositories\Contracts\StorePageRepositoryContract;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Store CMS pages (tenant DB): the system pages every store starts with (seeded on
 * provisioning, `storefront:sync-store-pages` for existing stores) and the store's own
 * pages — service API for VEN-033 (page built in P8). System pages keep their slug and
 * cannot be deleted, only edited or switched off.
 */
class StorePageService
{
    public const LOCALES = ['en', 'ar'];

    public function __construct(private readonly StorePageRepositoryContract $pages) {}

    /**
     * Creates the missing system pages (titles / bodies from the `pages` static texts in
     * ar + en, ":store" = the store name). Existing pages are never overwritten.
     *
     * @return int pages created
     */
    public function ensureSystemPages(?string $storeName = null): int
    {
        $existing = array_flip($this->pages->slugs());
        $storeName ??= (string) (tenant()?->getAttribute('name') ?? '');
        $created = 0;

        foreach (SystemStorePage::cases() as $page) {
            if (isset($existing[$page->value])) {
                continue;
            }

            $title = [];
            $body = [];

            foreach (self::LOCALES as $locale) {
                $title[$locale] = __st('pages.system.'.$page->textKey().'.title', [], $locale);
                $body[$locale] = EmailHtmlSanitizer::clean(__st('pages.system.'.$page->textKey().'.body', ['store' => e($storeName)], $locale));
            }

            $this->pages->create([
                'slug' => $page->value,
                'type' => StorePageType::System,
                'title' => $title,
                'body' => $body,
                'is_active' => true,
                'show_in_footer' => true,
                'sort_order' => $page->sortOrder(),
            ]);
            $created++;
        }

        return $created;
    }

    public function create(StorePageData $data): StorePage
    {
        $slug = $this->uniqueSlug($data->slug ?? (string) ($data->title['en'] ?? array_values($data->title)[0] ?? 'page'));

        return $this->pages->create($this->attributes($data) + [
            'slug' => $slug,
            'type' => StorePageType::Custom,
            'sort_order' => (int) $this->pages->query()->max('sort_order') + 1,
        ]);
    }

    public function find(int $id): StorePage
    {
        /** @var StorePage $page */
        $page = $this->pages->findOrFail($id);

        return $page;
    }

    public function update(StorePage $page, StorePageData $data): StorePage
    {
        $attributes = $this->attributes($data);

        // Replace (not merge) the translations so a cleared language is really removed.
        foreach (['title', 'body', 'meta_description'] as $field) {
            $page->replaceTranslations($field, $attributes[$field]);
            unset($attributes[$field]);
        }

        if ($page->type === StorePageType::Custom && $data->slug !== null && Str::slug($data->slug) !== $page->slug) {
            $attributes['slug'] = $this->uniqueSlug($data->slug);
        }

        return $this->pages->update($page, $attributes);
    }

    /**
     * Inline switch of the list: published (is_active) or "show in footer".
     */
    public function setFlag(StorePage $page, string $flag, bool $value): StorePage
    {
        if (! in_array($flag, ['is_active', 'show_in_footer'], true)) {
            throw new InvalidArgumentException("Unknown page flag [{$flag}].");
        }

        return $this->pages->update($page, [$flag => $value]);
    }

    public function delete(StorePage $page): void
    {
        if ($page->type === StorePageType::System) {
            throw ValidationException::withMessages(['page' => __st('pages.errors.system_page')]);
        }

        $this->pages->delete($page);
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StorePageData $data): array
    {
        return [
            'title' => array_filter($data->title, static fn (string $value): bool => trim($value) !== ''),
            'body' => array_map(static fn (string $html): string => EmailHtmlSanitizer::clean($html), array_filter($data->body, static fn (string $value): bool => trim(strip_tags($value)) !== '')),
            'meta_description' => array_filter($data->metaDescription, static fn (string $value): bool => trim($value) !== ''),
            'is_active' => $data->isActive,
            'show_in_footer' => $data->showInFooter,
        ];
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'page';
        $slug = $base;
        $taken = array_flip($this->pages->slugs());

        for ($suffix = 2; isset($taken[$slug]); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
