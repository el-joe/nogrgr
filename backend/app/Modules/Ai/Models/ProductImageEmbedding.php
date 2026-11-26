<?php

namespace App\Modules\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Image search vector of one central product image (central DB).
 *
 * @property int $id
 * @property int $product_id central products.id
 * @property string $path
 * @property string $source_hash
 * @property string $description
 * @property list<float> $embedding
 * @property int $dims
 * @property string $model
 */
class ProductImageEmbedding extends Model
{
    use CentralConnection;

    protected $table = 'product_image_embeddings';

    /**
     * @var list<string>
     */
    protected $fillable = ['product_id', 'path', 'source_hash', 'description', 'embedding', 'dims', 'model'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['product_id' => 'integer', 'embedding' => 'array', 'dims' => 'integer'];
    }
}
