<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\BlogPostFactory;
use App\Modules\Content\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central website blog post (ADM-081, WEB-013 / 014). Visible publicly when
 * published and published_at <= now (a future date = scheduled). The cover is
 * stored on the public disk with webp / avif derivatives (ConvertBlogCover).
 *
 * @property int $id
 * @property int|null $blog_category_id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $author_name
 * @property string|null $cover_path
 * @property string|null $cover_webp_path
 * @property string|null $cover_avif_path
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int $reading_minutes
 * @property-read BlogCategory|null $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BlogPost extends Model
{
    use CentralConnection;

    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    use HasTranslations;

    public const COVER_DISK = 'public';

    public const COVER_DIRECTORY = 'content/blog';

    protected $table = 'blog_posts';

    /**
     * @var list<string>
     */
    public array $translatable = ['title', 'excerpt', 'body', 'meta_title', 'meta_description'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'blog_category_id', 'slug', 'title', 'excerpt', 'body', 'meta_title', 'meta_description', 'author_name',
        'cover_path', 'cover_webp_path', 'cover_avif_path', 'status', 'published_at', 'reading_minutes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'reading_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<BlogCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    /**
     * @param  Builder<BlogPost>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', PostStatus::Published->value)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isScheduled(): bool
    {
        return $this->status === PostStatus::Published && $this->published_at !== null && $this->published_at->isFuture();
    }

    public function url(): string
    {
        return route('central.blog.show', $this->slug);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path !== null ? Storage::disk(self::COVER_DISK)->url($this->cover_path) : null;
    }

    /**
     * @return array{avif: string|null, webp: string|null, src: string|null}
     */
    public function coverSources(): array
    {
        $disk = Storage::disk(self::COVER_DISK);

        return [
            'avif' => $this->cover_avif_path !== null ? $disk->url($this->cover_avif_path) : null,
            'webp' => $this->cover_webp_path !== null ? $disk->url($this->cover_webp_path) : null,
            'src' => $this->coverUrl(),
        ];
    }

    protected static function newFactory(): BlogPostFactory
    {
        return BlogPostFactory::new();
    }
}
