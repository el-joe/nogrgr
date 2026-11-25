<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\DTOs\PageData;
use App\Modules\Content\Enums\PageStatus;
use App\Modules\Content\Exceptions\ContentRuleException;
use App\Modules\Content\Models\Page;
use App\Modules\Content\Repositories\Contracts\PageRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * Static pages (ADM-082 / 082a). System pages (about, terms, privacy) keep their slug,
 * stay on the central site and cannot be deleted.
 */
class PageService extends BaseService
{
    public function __construct(private readonly PageRepositoryContract $pages) {}

    public function create(PageData $data): Page
    {
        /** @var Page $page */
        $page = $this->pages->create($data->attributes());

        return $page;
    }

    public function update(Page $page, PageData $data): Page
    {
        $attributes = $data->attributes();

        if ($page->is_system) {
            $attributes['slug'] = $page->slug;
        }

        /** @var Page $page */
        $page = $this->pages->update($page, $attributes);

        return $page;
    }

    public function setStatus(Page $page, PageStatus $status): Page
    {
        /** @var Page $page */
        $page = $this->pages->update($page, ['status' => $status]);

        return $page;
    }

    public function delete(Page $page): void
    {
        if ($page->is_system) {
            throw ContentRuleException::systemPage();
        }

        $this->pages->delete($page);
    }
}
