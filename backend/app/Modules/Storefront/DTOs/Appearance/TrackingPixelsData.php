<?php

namespace App\Modules\Storefront\DTOs\Appearance;

use App\Modules\Storefront\Enums\TrackingPixel;

/**
 * Settings › Tracking pixels (VEN-083): per network the id and an on / off switch.
 */
final readonly class TrackingPixelsData
{
    /**
     * @param  array<string, array{id: string|null, enabled: bool}>  $pixels  TrackingPixel value => config
     */
    public function __construct(public array $pixels = []) {}

    public static function fromSetting(mixed $value): self
    {
        $value = is_array($value) ? $value : [];
        $pixels = [];

        foreach (TrackingPixel::cases() as $pixel) {
            $row = is_array($value[$pixel->value] ?? null) ? $value[$pixel->value] : [];
            $id = $row['id'] ?? null;
            $id = is_string($id) && preg_match($pixel->pattern(), $id) === 1 ? $id : null;
            $pixels[$pixel->value] = ['id' => $id, 'enabled' => $id !== null && (bool) ($row['enabled'] ?? false)];
        }

        return new self($pixels);
    }

    public function id(TrackingPixel $pixel): ?string
    {
        return $this->pixels[$pixel->value]['id'] ?? null;
    }

    public function isEnabled(TrackingPixel $pixel): bool
    {
        return ($this->pixels[$pixel->value]['enabled'] ?? false) && $this->id($pixel) !== null;
    }

    /**
     * Enabled pixels: value => id.
     *
     * @return array<string, string>
     */
    public function active(): array
    {
        $active = [];

        foreach (TrackingPixel::cases() as $pixel) {
            if ($this->isEnabled($pixel)) {
                $active[$pixel->value] = (string) $this->id($pixel);
            }
        }

        return $active;
    }

    /**
     * @return array<string, array{id: string|null, enabled: bool}>
     */
    public function toSetting(): array
    {
        return $this->pixels;
    }
}
