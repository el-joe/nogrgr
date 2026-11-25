<?php

namespace App\Modules\Catalog\Http\Requests\Vendor\Requests;

use App\Modules\Catalog\DTOs\Requests\VendorRequestSubmissionData;
use App\Modules\Catalog\Enums\RequestKind;
use App\Modules\Catalog\Http\Requests\Admin\Requests\RequestReplyRequest;
use App\Modules\Catalog\Repositories\Contracts\CategoryRepositoryContract;
use App\Modules\Catalog\Services\Requests\VendorRequestService;
use App\Modules\Catalog\Services\Vendor\StoreCatalogService;
use App\Modules\Identity\Models\VendorStaff;
use App\Modules\Localization\Services\CountryService;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * New vendor request (VEN-050b product: name, link, target markets, notes, files;
 * 050e manufacturing: name, quantity, description, optional store product; 050h brand:
 * name, category, countries, notes, files). The kind comes from the route.
 */
class VendorRequestStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function kind(): RequestKind
    {
        return RequestKind::fromVendorRouteName($this->route()?->getName()) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $files = [
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['file', 'max:10240', 'mimes:'.RequestReplyRequest::MIMES],
        ];
        $countries = [
            'country_ids' => ['nullable', 'array', 'max:250', $this->activeCountries(...)],
            'country_ids.*' => ['integer', 'min:1', 'distinct'],
        ];

        return match ($this->kind()) {
            RequestKind::Product => [
                'subject' => ['required', 'string', 'max:255'],
                'url' => ['nullable', 'url:http,https', 'max:2000'],
                'notes' => ['nullable', 'string', 'max:5000'],
                ...$countries,
                ...$files,
            ],
            RequestKind::Manufacturing => [
                'subject' => ['required', 'string', 'max:255'],
                'quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
                'notes' => ['required', 'string', 'max:5000'],
                'product_id' => ['nullable', 'integer', 'min:1', $this->storeProduct(...)],
                ...$files,
            ],
            RequestKind::Brand => [
                'subject' => ['required', 'string', 'max:255'],
                'category_id' => ['nullable', 'integer', 'min:1', $this->category(...)],
                'url' => ['nullable', 'url:http,https', 'max:2000'],
                'notes' => ['nullable', 'string', 'max:5000'],
                ...$countries,
                ...$files,
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $key = 'catalog::vendor_requests.fields.';

        return [
            'subject' => (string) __($key.'subject_'.$this->kind()->value),
            'url' => (string) __($key.'url'),
            'notes' => (string) __($key.'notes'),
            'quantity' => (string) __($key.'quantity'),
            'product_id' => (string) __($key.'product'),
            'category_id' => (string) __($key.'category'),
            'country_ids' => (string) __($key.'countries'),
            'files' => (string) __('catalog::requests.thread.files'),
            'files.*' => (string) __('catalog::requests.thread.file'),
        ];
    }

    public function toData(): VendorRequestSubmissionData
    {
        /** @var VendorStaff $staff */
        $staff = $this->user('vendor');
        $productId = $this->validated('product_id');
        $centralId = null;

        if (is_numeric($productId)) {
            $centralId = app(StoreCatalogService::class)->find((int) $productId)->central_product_id;
        }

        return new VendorRequestSubmissionData(
            kind: $this->kind(),
            tenantId: VendorRequestService::tenantId(),
            subject: trim((string) $this->validated('subject')),
            notes: $this->filled('notes') ? (string) $this->validated('notes') : null,
            url: $this->filled('url') ? (string) $this->validated('url') : null,
            countryIds: array_values(array_map('intval', (array) $this->validated('country_ids', []))),
            quantity: is_numeric($this->validated('quantity')) ? (int) $this->validated('quantity') : null,
            productId: $centralId !== null ? (int) $centralId : null,
            categoryId: is_numeric($this->validated('category_id')) ? (int) $this->validated('category_id') : null,
            files: array_values(array_filter((array) $this->file('files', []), static fn (mixed $file): bool => $file instanceof UploadedFile)),
            submittedBy: (string) $staff->name,
            senderName: (string) (tenant('name') ?? $staff->name),
        );
    }

    private function activeCountries(string $attribute, mixed $value, Closure $fail): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $value))));

        if ($ids !== [] && app(CountryService::class)->active($ids)->count() !== count($ids)) {
            $fail('validation.exists')->translate();
        }
    }

    private function storeProduct(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(StoreCatalogService::class)->find((int) $value);
        } catch (ModelNotFoundException) {
            $fail('validation.exists')->translate();
        }
    }

    private function category(string $attribute, mixed $value, Closure $fail): void
    {
        if (app(CategoryRepositoryContract::class)->find((int) $value) === null) {
            $fail('validation.exists')->translate();
        }
    }
}
