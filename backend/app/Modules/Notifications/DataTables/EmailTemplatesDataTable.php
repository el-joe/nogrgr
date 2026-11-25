<?php

namespace App\Modules\Notifications\DataTables;

use App\Modules\Notifications\Enums\EmailAudience;
use App\Modules\Notifications\Enums\EmailTemplateLocaleState;
use App\Modules\Notifications\Enums\EmailTemplateStatus;
use App\Modules\Notifications\Models\EmailTemplate;
use App\Modules\Notifications\Repositories\Contracts\EmailTemplateRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Settings › Email templates list (server-side). Filters: audience (tab),
 * status, language completeness, search (event key / subject).
 *
 * @extends BaseDataTable<EmailTemplate>
 */
final class EmailTemplatesDataTable extends BaseDataTable
{
    public const FILTERS = ['search', 'audience', 'status', 'language'];

    public function __construct(private readonly EmailTemplateRepositoryContract $templates) {}

    protected function query(): Builder
    {
        return $this->templates->query()->select('email_templates.*');
    }

    protected function filter(Builder $query, array $filters): Builder
    {
        $audience = EmailAudience::tryFrom((string) ($filters['audience'] ?? ''));
        $status = EmailTemplateStatus::tryFrom((string) ($filters['status'] ?? ''));
        $language = EmailTemplateLocaleState::tryFrom((string) ($filters['language'] ?? ''));
        $search = is_string($filters['search'] ?? null) ? trim($filters['search']) : '';

        return $query
            ->when($audience !== null, fn (Builder $query) => $query->where('audience', $audience->value))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status->value))
            ->when($language !== null, function (Builder $query) use ($language): void {
                $missing = $language->missingLocale();

                if ($missing === null) {
                    foreach (EmailTemplate::LOCALES as $locale) {
                        $query->whereNotNull('subject->'.$locale)->where('subject->'.$locale, '!=', '')->whereNotNull('body->'.$locale);
                    }

                    return;
                }

                $query->where(fn (Builder $inner) => $inner->whereNull('subject->'.$missing)->orWhere('subject->'.$missing, '')->orWhereNull('body->'.$missing));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn (Builder $inner) => $inner->where('event', 'like', $term)
                    ->orWhere('subject->en', 'like', $term)
                    ->orWhere('subject->ar', 'like', $term));
            });
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->filter(static fn (): null => null)
            ->orderColumn('name', static fn (Builder $query, string $order) => $query->orderBy('event', $order === 'desc' ? 'desc' : 'asc'))
            ->addColumn('name', fn (EmailTemplate $template): string => view('notifications::admin.email-templates._name-cell', ['template' => $template])->render())
            ->editColumn('audience', fn (EmailTemplate $template): string => view('notifications::admin.email-templates._audience-cell', ['template' => $template])->render())
            ->addColumn('languages', fn (EmailTemplate $template): string => '<span dir="ltr">'.e(strtoupper(implode(', ', $template->completeLocales())) ?: '—').'</span>')
            ->editColumn('updated_at', fn (EmailTemplate $template): string => $template->updated_at !== null
                ? '<time datetime="'.e($template->updated_at->toIso8601String()).'" title="'.e($template->updated_at->translatedFormat('d M Y, H:i')).'">'.e($template->updated_at->diffForHumans()).'</time>'
                : '—')
            ->editColumn('status', fn (EmailTemplate $template): string => view('notifications::admin.email-templates._status-cell', ['template' => $template])->render())
            ->addColumn('actions', fn (EmailTemplate $template): string => view('notifications::admin.email-templates._actions-cell', ['template' => $template])->render())
            ->rawColumns(['name', 'audience', 'languages', 'updated_at', 'status', 'actions']);
    }
}
