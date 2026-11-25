<?php

namespace App\Modules\Platform\DataTables;

use App\Modules\Platform\Models\DnsRecordTemplate;
use App\Modules\Platform\Repositories\Contracts\DnsRecordTemplateRepositoryContract;
use App\Shared\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\EloquentDataTable;

/**
 * Admin › Domains › DNS record templates (ADM-045b).
 *
 * @extends BaseDataTable<DnsRecordTemplate>
 */
final class DnsRecordTemplatesDataTable extends BaseDataTable
{
    public function __construct(private readonly DnsRecordTemplateRepositoryContract $templates) {}

    protected function query(): Builder
    {
        return $this->templates->query()->orderBy('sort_order')->orderBy('id');
    }

    protected function configure(EloquentDataTable $table): EloquentDataTable
    {
        return $table
            ->editColumn('type', fn (DnsRecordTemplate $template): string => view('platform::admin.compliance._badge-cell', ['tone' => $template->type->tone(), 'label' => $template->type->label()])->render())
            ->editColumn('host', fn (DnsRecordTemplate $template): string => '<bdi dir="ltr" class="font-mono text-[13px]">'.e($template->host).'</bdi>')
            ->editColumn('value', fn (DnsRecordTemplate $template): string => '<bdi dir="ltr" class="break-all font-mono text-[13px]">'.e($template->value).'</bdi>')
            ->editColumn('ttl', fn (DnsRecordTemplate $template): string => '<bdi dir="ltr">'.e((string) $template->ttl).'</bdi>')
            ->editColumn('priority', fn (DnsRecordTemplate $template): string => $template->priority !== null ? '<bdi dir="ltr">'.e((string) $template->priority).'</bdi>' : '<span class="text-ink-secondary">—</span>')
            ->addColumn('actions', fn (DnsRecordTemplate $template): string => view('platform::admin.domains._dns-actions', ['template' => $template])->render())
            ->rawColumns(['type', 'host', 'value', 'ttl', 'priority', 'actions'])
            ->only(['id', 'type', 'host', 'value', 'ttl', 'priority', 'actions']);
    }
}
