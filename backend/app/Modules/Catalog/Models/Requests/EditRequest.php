<?php

namespace App\Modules\Catalog\Models\Requests;

use App\Modules\Catalog\Database\Factories\EditRequestFactory;
use App\Modules\Catalog\Enums\EditRequestStatus;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\Admin;
use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Product edit request (ADM-018a/b, submitted by vendors in VEN-013). `changes` is the
 * field diff: list of {field, locale, current, requested}; for `images` current/requested
 * are path lists and the new files are listed in `files` (private disk).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $product_id
 * @property int|null $variant_id
 * @property list<array{field: string, locale: string|null, current: mixed, requested: mixed}> $changes
 * @property list<array{path: string, name: string, size: int, mime_type: string|null}>|null $files
 * @property string|null $vendor_note
 * @property string|null $submitted_by
 * @property EditRequestStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 * @property-read ProductVariant|null $variant
 * @property-read Admin|null $reviewer
 */
class EditRequest extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    /** @use HasFactory<EditRequestFactory> */
    use HasFactory;

    protected $table = 'catalog_edit_requests';

    protected $guarded = ['id'];

    protected static function newFactory(): EditRequestFactory
    {
        return EditRequestFactory::new();
    }

    public function reference(): string
    {
        return 'ER-'.str_pad((string) $this->id, 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === EditRequestStatus::Pending;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'files' => 'array',
            'status' => EditRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
