<?php

namespace App\Modules\Storefront\Support;

use InvalidArgumentException;

/**
 * #rrggbb colour maths for the store's brand colour (Appearance › Colours, VEN-034b):
 * hover shade, subtle tint and a readable text colour on top of it.
 */
final readonly class HexColor
{
    public const PATTERN = '/^#[0-9a-f]{6}$/i';

    private function __construct(public int $red, public int $green, public int $blue) {}

    public static function from(string $hex): self
    {
        if (preg_match(self::PATTERN, $hex) !== 1) {
            throw new InvalidArgumentException("Invalid colour [{$hex}].");
        }

        return new self((int) hexdec(substr($hex, 1, 2)), (int) hexdec(substr($hex, 3, 2)), (int) hexdec(substr($hex, 5, 2)));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    public function hex(): string
    {
        return sprintf('#%02x%02x%02x', $this->red, $this->green, $this->blue);
    }

    /**
     * Mixed towards black (amount 0…1).
     */
    public function darken(float $amount): self
    {
        return $this->mix(0, $amount);
    }

    /**
     * Mixed towards white (amount 0…1).
     */
    public function lighten(float $amount): self
    {
        return $this->mix(255, $amount);
    }

    /**
     * WCAG relative luminance 0…1.
     */
    public function luminance(): float
    {
        $channel = static function (int $value): float {
            $c = $value / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($this->red) + 0.7152 * $channel($this->green) + 0.0722 * $channel($this->blue);
    }

    /**
     * White or near-black text, whichever contrasts more.
     */
    public function readableInk(): string
    {
        $luminance = $this->luminance();
        $onWhite = 1.05 / ($luminance + 0.05);
        $onDark = ($luminance + 0.05) / (0.0137 + 0.05);

        return $onWhite >= $onDark ? '#ffffff' : '#121212';
    }

    private function mix(int $target, float $amount): self
    {
        $amount = max(0.0, min(1.0, $amount));
        $blend = static fn (int $value): int => (int) round($value + ($target - $value) * $amount);

        return new self($blend($this->red), $blend($this->green), $blend($this->blue));
    }
}
