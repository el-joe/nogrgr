<?php

namespace App\Modules\Support\Models;

use App\Shared\Tenancy\Concerns\BelongsToCurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * One store member's "Was this helpful?" vote on a help center article.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $staff_id
 * @property string $article
 * @property bool $helpful
 */
class HelpArticleFeedback extends Model
{
    use BelongsToCurrentTenant;
    use CentralConnection;

    protected $table = 'support_help_feedback';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['helpful' => 'boolean'];
    }
}
