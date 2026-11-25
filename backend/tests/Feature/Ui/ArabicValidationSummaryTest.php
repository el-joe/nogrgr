<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

it('summarises extra validation errors in Arabic (QA-P0-014)', function (int $fields, string $expected): void {
    app()->setLocale('ar');

    $rules = collect(range(1, $fields))->mapWithKeys(fn (int $i): array => ["field_{$i}" => 'required'])->all();

    try {
        Validator::make([], $rules)->validate();
        $this->fail('Validation should have failed.');
    } catch (ValidationException $exception) {
        expect($exception->getMessage())->toEndWith($expected)
            ->not->toContain('more error');
    }
})->with([
    'one more' => [2, '(وخطأ آخر)'],
    'two more' => [3, '(وخطآن آخران)'],
    'five more' => [6, '(و5 أخطاء أخرى)'],
    'twelve more' => [13, '(و12 خطأً آخر)'],
]);
