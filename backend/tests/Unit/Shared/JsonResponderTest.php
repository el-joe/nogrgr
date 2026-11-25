<?php

use App\Shared\Http\JsonResponder;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

it('renders the success contract', function (): void {
    $response = JsonResponder::success('Saved', ['id' => 1], '/admin');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['success' => true, 'message' => 'Saved', 'data' => ['id' => 1], 'redirect' => '/admin']);
});

it('omits optional keys', function (): void {
    expect(JsonResponder::success('Ok')->getData(true))->toBe(['success' => true, 'message' => 'Ok']);
});

it('renders errors', function (): void {
    $response = JsonResponder::error('Nope', 403);

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getData(true))->toBe(['success' => false, 'message' => 'Nope']);
});

it('renders the 422 validation contract', function (): void {
    $response = JsonResponder::validation(['email' => 'The email is invalid.']);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'message' => 'The email is invalid.',
            'errors' => ['email' => ['The email is invalid.']],
        ]);
});

it('answers created with 201 and the success contract', function (): void {
    $response = JsonResponder::created('Created', ['id' => 7], '/admin/products/7');

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getData(true))->toBe(['success' => true, 'message' => 'Created', 'data' => ['id' => 7], 'redirect' => '/admin/products/7']);
});

it('keeps the success contract for custom statuses and headers', function (): void {
    $response = JsonResponder::success('Queued', status: 202, headers: ['X-Request-Id' => 'abc']);

    expect($response->getStatusCode())->toBe(202)
        ->and($response->headers->get('X-Request-Id'))->toBe('abc')
        ->and($response->getData(true))->toBe(['success' => true, 'message' => 'Queued']);
});

it('defaults errors to 400 and keeps optional data and redirect', function (): void {
    expect(JsonResponder::error('Bad')->getStatusCode())->toBe(400);

    $response = JsonResponder::error('Session expired', 419, ['retry' => true], '/admin/login');

    expect($response->getStatusCode())->toBe(419)
        ->and($response->getData(true))->toBe(['success' => false, 'message' => 'Session expired', 'data' => ['retry' => true], 'redirect' => '/admin/login']);
});

it('renders error statuses the http client handles', function (int $status): void {
    $response = JsonResponder::error('Failed', $status);

    expect($response->getStatusCode())->toBe($status)
        ->and($response->getData(true)['success'])->toBeFalse();
})->with([401, 403, 404, 409, 429, 500, 503]);

it('keeps falsy but present data', function (mixed $data): void {
    expect(JsonResponder::success('Ok', $data)->getData(true))->toHaveKey('data');
})->with([[[]], [0], [false], ['']]);

it('normalises a MessageBag and multiple messages per field', function (): void {
    $bag = new MessageBag([
        'title.en' => ['English title is required.', 'Too short.'],
        'phone' => ['Invalid phone.'],
    ]);

    $response = JsonResponder::validation($bag);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'message' => 'English title is required.',
            'errors' => [
                'title.en' => ['English title is required.', 'Too short.'],
                'phone' => ['Invalid phone.'],
            ],
        ]);
});

it('uses an explicit validation message and falls back to the generic one', function (): void {
    expect(JsonResponder::validation(['x' => 'Bad'], 'Fix the form.')->getData(true)['message'])->toBe('Fix the form.')
        ->and(JsonResponder::validation([])->getData(true))->toBe(['message' => __('The given data was invalid.'), 'errors' => []]);
});

it('matches the shape Laravel renders for a ValidationException', function (): void {
    $validator = validator(['email' => 'nope'], ['email' => ['required', 'email']]);
    $laravel = (new ValidationException($validator))->errors();

    $ours = JsonResponder::validation($validator->errors())->getData(true);

    expect($ours['errors'])->toBe($laravel)
        ->and(array_keys($ours))->toBe(['message', 'errors']);
});
