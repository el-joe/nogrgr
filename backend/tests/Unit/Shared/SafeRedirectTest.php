<?php

use App\Shared\Http\SafeRedirect;
use Illuminate\Http\Request;

/*
| ?redirect= after storefront sign-in: same host only (old open redirect fixed).
*/

it('accepts same-host targets and rejects everything else', function (mixed $candidate, ?string $expected): void {
    $request = Request::create('http://acme.localhost/account/login');

    expect(SafeRedirect::target($candidate, $request))->toBe($expected);
})->with([
    'relative path' => ['/cart', 'http://acme.localhost/cart'],
    'relative with query' => ['/account/orders?page=2#top', 'http://acme.localhost/account/orders?page=2#top'],
    'absolute same host' => ['http://acme.localhost/checkout', 'http://acme.localhost/checkout'],
    'same host other case' => ['http://ACME.localhost/checkout', 'http://ACME.localhost/checkout'],
    'other host' => ['https://evil.test/', null],
    'sub-domain of host' => ['http://evil.acme.localhost/', null],
    'same host other port' => ['http://acme.localhost:8080/', null],
    'other scheme port' => ['https://acme.localhost/', null],
    'protocol relative' => ['//evil.test/path', null],
    'backslash' => ['/\\evil.test', null],
    'encoded newline' => ["/cart\r\nLocation: https://evil.test", null],
    'javascript' => ['javascript:alert(1)', null],
    'data uri' => ['data:text/html,hi', null],
    'userinfo' => ['http://acme.localhost@evil.test/', null],
    'userinfo same host' => ['http://user:pass@acme.localhost/', null],
    'relative without slash' => ['cart', null],
    'empty' => ['', null],
    'array' => [['/cart'], null],
    'null' => [null, null],
]);
