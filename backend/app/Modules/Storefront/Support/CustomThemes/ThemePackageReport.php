<?php

namespace App\Modules\Storefront\Support\CustomThemes;

/**
 * Validation result of an uploaded theme package (VEN-036 validation report).
 * `contents` (path => bytes) is only kept in memory to extract a valid package.
 */
final class ThemePackageReport
{
    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, array{size: int, sha256: string}>  $files
     * @param  array<string, string>  $contents
     */
    public function __construct(
        public array $errors = [],
        public array $warnings = [],
        public ?array $manifest = null,
        public array $files = [],
        public array $contents = [],
        public int $size = 0,
    ) {}

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function error(string $message): void
    {
        if (count($this->errors) < 100 && ! in_array($message, $this->errors, true)) {
            $this->errors[] = $message;
        }
    }

    public function warn(string $message): void
    {
        if (count($this->warnings) < 100 && ! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }

    /**
     * Twig template sources keyed by loader name ("home.twig", "partials/card.twig").
     *
     * @return array<string, string>
     */
    public function templates(): array
    {
        return self::templatesOf($this->contents);
    }

    /**
     * @param  array<string, string>  $contents
     * @return array<string, string>
     */
    public static function templatesOf(array $contents): array
    {
        $templates = [];

        foreach ($contents as $path => $bytes) {
            if (str_starts_with($path, 'templates/') && str_ends_with($path, '.twig')) {
                $templates[substr($path, strlen('templates/'))] = $bytes;
            }
        }

        return $templates;
    }

    /**
     * @return array{errors: list<string>, warnings: list<string>, files: array<string, array{size: int, sha256: string}>, size: int}
     */
    public function toArray(): array
    {
        return ['errors' => $this->errors, 'warnings' => $this->warnings, 'files' => $this->files, 'size' => $this->size];
    }
}
