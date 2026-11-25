<?php

/*
| Store hosts serve their own robots.txt (central's public/robots.txt is gone): the
| storefront is crawlable, panels / APIs / account / address lookup are not.
*/

it('serves a tenant robots.txt on the store host', function (): void {
    $tenant = $this->createTenant('robots-shop');

    $response = $this->get($this->tenantUrl($tenant, '/robots.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    expect($response->getContent())
        ->toContain("User-agent: *\nAllow: /")
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /api/')
        ->toContain('Disallow: /store-api/')
        ->toContain('Disallow: /account')
        ->not->toContain('Sitemap:')
        ->not->toContain('/affiliate');
});

it('keeps the central robots.txt on the central host', function (): void {
    expect($this->get($this->centralUrl('/robots.txt'))->assertOk()->getContent())
        ->toContain('Disallow: /affiliate')
        ->toContain('Sitemap: '.route('central.sitemap'));
});
