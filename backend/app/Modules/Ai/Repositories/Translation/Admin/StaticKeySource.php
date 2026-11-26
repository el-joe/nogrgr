<?php

namespace App\Modules\Ai\Repositories\Translation\Admin;

use App\Modules\Ai\Contracts\AdminTranslationSource;
use App\Modules\Ai\DTOs\TranslationItemData;
use App\Modules\Localization\Enums\TranslationKeyStatus;
use App\Modules\Localization\Services\Translations\TranslationEditorService;
use App\Modules\Localization\Services\Translations\TranslationFileRegistry;
use App\Modules\Localization\Support\TranslationFile;

/**
 * Static key files of the translation editor (ADM-092a): item key "<file id>|<key>";
 * only keys missing in the target locale, not locked (ADM-093) and with an English
 * source are translated. Writes go through TranslationEditorService::putMany (one cache
 * flush / store sync per chunk and file).
 */
class StaticKeySource implements AdminTranslationSource
{
    public const SEPARATOR = '|';

    public function __construct(
        private readonly TranslationEditorService $editor,
        private readonly TranslationFileRegistry $registry,
        private readonly ?string $file = null,
    ) {}

    public function section(): string
    {
        return 'static_keys';
    }

    public function keys(string $locale): array
    {
        $keys = [];

        foreach ($this->files() as $file) {
            foreach ($this->missing($file, $locale) as $key => $source) {
                $keys[] = $file->id.self::SEPARATOR.$key;
            }
        }

        return $keys;
    }

    public function items(array $keys, string $locale): array
    {
        $items = [];

        foreach ($this->group($keys) as $fileId => $fileKeys) {
            $file = $this->registry->find($fileId);

            if ($file === null) {
                continue;
            }

            $missing = $this->missing($file, $locale);

            foreach ($fileKeys as $key) {
                if (isset($missing[$key])) {
                    $id = $fileId.self::SEPARATOR.$key;
                    $items[$id] = TranslationItemData::make($id, ['value' => ['en', $missing[$key]]], ['value']);
                }
            }
        }

        return $items;
    }

    public function write(string $locale, array $translations): void
    {
        $values = [];

        foreach ($translations as $id => $fields) {
            [$fileId, $key] = array_pad(explode(self::SEPARATOR, (string) $id, 2), 2, '');

            if ($key !== '' && isset($fields['value'])) {
                $values[$fileId][$key] = $fields['value'];
            }
        }

        foreach ($values as $fileId => $lines) {
            $this->editor->putMany((string) $fileId, $locale, $lines);
        }
    }

    /**
     * Missing, unlocked keys of a file with their English source.
     *
     * @return array<string, string> key => English text
     */
    public function missing(TranslationFile $file, string $locale): array
    {
        $missing = [];

        if ($locale === 'en') {
            return $missing;
        }

        foreach ($this->editor->rows($file, $locale) as $row) {
            if ($row['status'] === TranslationKeyStatus::Missing && ! $row['locked'] && is_string($row['source']) && trim($row['source']) !== '') {
                $missing[$row['key']] = $row['source'];
            }
        }

        return $missing;
    }

    /**
     * Number of locked keys of the scope (shown as "skipped" in the modal).
     */
    public function lockedCount(string $locale): int
    {
        $count = 0;

        foreach ($this->files() as $file) {
            $count += $this->editor->rows($file, $locale)->where('locked', true)->count();
        }

        return $count;
    }

    /**
     * @return list<TranslationFile>
     */
    private function files(): array
    {
        if ($this->file !== null) {
            $file = $this->registry->find($this->file);

            return $file !== null ? [$file] : [];
        }

        return array_values($this->registry->files());
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, list<string>>
     */
    private function group(array $keys): array
    {
        $groups = [];

        foreach ($keys as $id) {
            [$fileId, $key] = array_pad(explode(self::SEPARATOR, (string) $id, 2), 2, '');

            if ($key !== '') {
                $groups[$fileId][] = $key;
            }
        }

        return $groups;
    }
}
