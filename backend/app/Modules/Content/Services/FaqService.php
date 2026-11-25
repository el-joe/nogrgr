<?php

namespace App\Modules\Content\Services;

use App\Modules\Content\DTOs\FaqData;
use App\Modules\Content\Models\Faq;
use App\Modules\Content\Repositories\Contracts\FaqRepositoryContract;
use App\Shared\Services\BaseService;

/**
 * FAQs (ADM-083 / 083a): CRUD and drag-sort (new items go last).
 */
class FaqService extends BaseService
{
    public function __construct(private readonly FaqRepositoryContract $faqs) {}

    public function create(FaqData $data): Faq
    {
        /** @var Faq $faq */
        $faq = $this->faqs->create([...$data->attributes(), 'sort_order' => $this->faqs->nextSortOrder()]);

        return $faq;
    }

    public function update(Faq $faq, FaqData $data): Faq
    {
        /** @var Faq $faq */
        $faq = $this->faqs->update($faq, $data->attributes());

        return $faq;
    }

    /**
     * @param  list<int>  $ids
     */
    public function reorder(array $ids): void
    {
        $this->transaction(fn () => $this->faqs->reorder($ids));
    }

    public function delete(Faq $faq): void
    {
        $this->faqs->delete($faq);
    }
}
