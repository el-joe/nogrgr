<?php

namespace App\Modules\Content\Models;

use App\Modules\Content\Database\Factories\FaqFactory;
use App\Modules\Content\Enums\FaqGroup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Central website FAQ (ADM-083, WEB-009). Answer is sanitised HTML.
 *
 * @property int $id
 * @property FaqGroup $group
 * @property string $question
 * @property string $answer
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Faq extends Model
{
    use CentralConnection;

    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    use HasTranslations;

    protected $table = 'faqs';

    /**
     * @var list<string>
     */
    public array $translatable = ['question', 'answer'];

    /**
     * @var list<string>
     */
    protected $fillable = ['group', 'question', 'answer', 'is_active', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['group' => FaqGroup::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function newFactory(): FaqFactory
    {
        return FaqFactory::new();
    }
}
