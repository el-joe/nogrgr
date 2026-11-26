<?php

namespace App\Modules\Storefront\Http\Resources\StoreApi;

use App\Modules\Storefront\Enums\SystemStorePage;
use App\Modules\Storefront\Models\StorePage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published store CMS page (about, terms, privacy, … or the store's own); the body is
 * the sanitised HTML the theme renders.
 *
 * @property StorePage $resource
 */
class StorePageResource extends JsonResource
{
    public function __construct(StorePage $resource, private readonly bool $withBody = true)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $page = $this->resource;

        return [
            'slug' => $page->slug,
            'title' => (string) $page->title,
            'type' => $page->type->value,
            'group' => SystemStorePage::tryFrom($page->slug)?->footerGroup() ?? 'company',
            'show_in_footer' => (bool) $page->show_in_footer,
            'is_contact' => $page->slug === SystemStorePage::Contact->value,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ] + ($this->withBody ? [
            'body' => (string) $page->body,
            'meta_description' => $page->meta_description,
        ] : []);
    }
}
