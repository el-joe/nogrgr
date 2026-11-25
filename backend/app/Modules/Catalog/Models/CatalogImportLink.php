<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\ImportDriver;
use App\Modules\Catalog\Enums\ImportLinkType;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * External id => local record (product, variant, gallery image) written by the importer,
 * with the checksum of the last imported payload (unchanged items are skipped).
 *
 * @property int $id
 * @property ImportDriver $driver
 * @property ImportLinkType $type
 * @property int $external_id
 * @property int $local_id
 * @property string|null $checksum
 */
class CatalogImportLink extends Model
{
    use CentralConnection;

    protected $table = 'catalog_import_links';

    /**
     * @var list<string>
     */
    protected $fillable = ['driver', 'type', 'external_id', 'local_id', 'checksum'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => ImportDriver::class,
            'type' => ImportLinkType::class,
            'external_id' => 'integer',
            'local_id' => 'integer',
        ];
    }
}
