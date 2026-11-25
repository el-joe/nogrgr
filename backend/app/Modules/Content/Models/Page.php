<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\PageFactory;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Enums\PageTarget;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Static page (ADM-082, WEB-007 / 010 / 011 / 012). System pages (about, terms,
 * privacy) cannot be deleted or have their slug changed.
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property PageTarget $target
 * @property bool $is_compliance
 * @property bool $is_system
 * @property PageStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Page extends Model
{
    use CentralConnection;

    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * Slugs with their own central routes.
     *
     * @var list<string>
     */
    public const SYSTEM_SLUGS = ['about', 'terms', 'privacy'];

    protected $table = 'pages';

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'body', 'meta_title', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = ['slug', 'title', 'body', 'meta_title', 'meta_description', 'target', 'is_compliance', 'is_system', 'status'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target' => PageTarget::class,
            'status' => PageStatus::class,
            'is_compliance' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function isOnCentral(): bool
    {
        return in_array($this->target, [PageTarget::Central, PageTarget::Both], true);
    }

    public function url(): string
    {
        return in_array($this->slug, self::SYSTEM_SLUGS, true)
            ? route('central.'.$this->slug)
            : route('central.page', $this->slug);
    }

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }
}
