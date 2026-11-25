<?php

namespace App\Modules\Platform\Support;

use Illuminate\Support\Facades\Vite;

/**
 * Sample catalogue for the storefront motion gallery (/admin/ui-kit/storefront-motion).
 * Images were generated with the local ComfyUI (docs/plan/comfyui.md) and live in
 * resources/images/demo; manifest.json holds their size and a tiny blur-up placeholder.
 * Only the gallery uses this — real storefronts read the Catalog module.
 */
final class MotionDemoSamples
{
    public const FAMILIES = ['ecommet', 'elora', 'souqify'];

    /**
     * @var list<array{slug: string, price: int, compare: ?int, alt: ?string, rating: float}>
     */
    private const PRODUCTS = [
        ['slug' => 'headphones', 'price' => 189, 'compare' => 229, 'alt' => 'headphones-alt', 'rating' => 4.8],
        ['slug' => 'sneakers', 'price' => 129, 'compare' => null, 'alt' => 'sneakers-alt', 'rating' => 4.6],
        ['slug' => 'perfume', 'price' => 96, 'compare' => 120, 'alt' => 'perfume-alt', 'rating' => 4.9],
        ['slug' => 'mug', 'price' => 24, 'compare' => null, 'alt' => 'mug-alt', 'rating' => 4.7],
        ['slug' => 'watch', 'price' => 249, 'compare' => 299, 'alt' => null, 'rating' => 4.5],
        ['slug' => 'bag', 'price' => 159, 'compare' => null, 'alt' => null, 'rating' => 4.8],
        ['slug' => 'sunglasses', 'price' => 79, 'compare' => 99, 'alt' => null, 'rating' => 4.4],
        ['slug' => 'serum', 'price' => 38, 'compare' => null, 'alt' => null, 'rating' => 4.9],
    ];

    /**
     * @var array<string, array{width: int, height: int, placeholder: string}>|null
     */
    private static ?array $manifest = null;

    /**
     * @return array{src: string, thumb: ?string, width: int, height: int, placeholder: string}
     */
    public static function image(string $slug): array
    {
        self::$manifest ??= (array) json_decode((string) file_get_contents(resource_path('images/demo/manifest.json')), true);
        $meta = self::$manifest[$slug] ?? ['width' => 640, 'height' => 800, 'placeholder' => ''];
        $thumb = is_file(resource_path("images/demo/{$slug}-thumb.webp")) ? Vite::asset("resources/images/demo/{$slug}-thumb.webp") : null;

        return [
            'src' => Vite::asset("resources/images/demo/{$slug}.webp"),
            'thumb' => $thumb,
            'width' => (int) $meta['width'],
            'height' => (int) $meta['height'],
            'placeholder' => (string) $meta['placeholder'],
        ];
    }

    /**
     * @return list<array{slug: string, name: string, category: string, price: int, compare: ?int, rating: float, image: array<string, mixed>, alt: ?array<string, mixed>}>
     */
    public static function products(): array
    {
        return array_map(static fn (array $product): array => [
            'slug' => $product['slug'],
            'name' => (string) __('platform::motion-demo.products.'.$product['slug'].'.name'),
            'category' => (string) __('platform::motion-demo.products.'.$product['slug'].'.category'),
            'price' => $product['price'],
            'compare' => $product['compare'],
            'rating' => $product['rating'],
            'image' => self::image($product['slug']),
            'alt' => $product['alt'] !== null ? self::image($product['alt']) : null,
        ], self::PRODUCTS);
    }

    /**
     * @return list<array{key: string, image: array<string, mixed>}>
     */
    public static function heroes(): array
    {
        return array_map(static fn (string $key): array => ['key' => $key, 'image' => self::image($key)], ['hero-1', 'hero-2', 'hero-3']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function gallery(string $slug): array
    {
        $images = [$slug, $slug.'-alt', ...array_filter(['perfume', 'bag', 'mug'], static fn (string $other): bool => $other !== $slug)];

        return array_map(static fn (string $image): array => self::image($image), array_slice(array_values(array_filter($images, static fn (string $image): bool => is_file(resource_path("images/demo/{$image}.webp")))), 0, 4));
    }
}
