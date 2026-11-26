<?php

namespace App\Modules\Ai\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Image search vector of one image of a store's own product (tenant DB).
 *
 * @property int $id
 * @property int $store_product_id
 * @property string $path
 * @property string $source_hash
 * @property string $description
 * @property list<float> $embedding
 * @property int $dims
 * @property string $model
 */
class StoreImageEmbedding extends Model
{
    protected $table = 'store_image_embeddings';

    /**
     * @var list<string>
     */
    protected $fillable = ['store_product_id', 'path', 'source_hash', 'description', 'embedding', 'dims', 'model'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['store_product_id' => 'integer', 'embedding' => 'array', 'dims' => 'integer'];
    }
}
