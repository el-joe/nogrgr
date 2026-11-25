<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Services\CategoryService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Whole category tree from the drag-and-drop editor (ADM-014 / 014b):
 * tree = [{id, children: [{id, children: […]}, …]}, …].
 */
class CategoryTreeRequest extends FormRequest
{
    public const MAX_NODES = 5000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tree' => ['required', 'array', 'min:1', function (string $attribute, mixed $value, Closure $fail): void {
                $count = 0;

                if (! $this->validNodes($value, 1, $count)) {
                    $fail(__('catalog::categories.tree_invalid'));
                }
            }],
        ];
    }

    /**
     * @return list<array{id: int, children?: list<array<string, mixed>>}>
     */
    public function tree(): array
    {
        /** @var list<array{id: int, children?: list<array<string, mixed>>}> $tree */
        $tree = (array) $this->validated('tree');

        return $tree;
    }

    private function validNodes(mixed $nodes, int $depth, int &$count): bool
    {
        if (! is_array($nodes) || ! array_is_list($nodes) || $depth > CategoryService::MAX_DEPTH + 1) {
            return false;
        }

        foreach ($nodes as $node) {
            if (++$count > self::MAX_NODES || ! is_array($node) || filter_var($node['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                return false;
            }

            if (isset($node['children']) && $node['children'] !== [] && ! $this->validNodes($node['children'], $depth + 1, $count)) {
                return false;
            }
        }

        return true;
    }
}
