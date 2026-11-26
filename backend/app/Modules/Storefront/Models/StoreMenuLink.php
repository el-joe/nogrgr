<?php

namespace App\Modules\Storefront\Models;

use App\Modules\Storefront\Enums\FooterColumn;
use App\Modules\Storefront\Enums\MenuLinkPlacement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * Store menu link (tenant DB, VEN-034d/e): a link in the store bar above the theme
 * header or in a footer column. URL = a path on the store or an http(s) address.
 *
 * @property int $id
 * @property MenuLinkPlacement $placement
 * @property FooterColumn|null $footer_column
 * @property string $label
 * @property string $url
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class StoreMenuLink extends Model
{
    use HasTranslations;

    protected $table = 'store_menu_links';

    /**
     * @var list<string>
     */
    public array $translatable = ['label'];

    /**
     * @var list<string>
     */
    protected $fillable = ['placement', 'footer_column', 'label', 'url', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'placement' => MenuLinkPlacement::class,
            'footer_column' => FooterColumn::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isExternal(): bool
    {
        return preg_match('#^https?://#i', $this->url) === 1;
    }
}
