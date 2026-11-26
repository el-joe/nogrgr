<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Enums\DeveloperDocTopic;
use App\Shared\Services\BaseService;
use Carbon\CarbonImmutable;

/**
 * Developer docs (ADM-115 / 115a): the topic list and one topic's content (summary,
 * sections with paragraphs / lists / code, closing note) from the translation file, with
 * the file's last change as "Updated".
 */
class DeveloperDocsService extends BaseService
{
    /**
     * @return list<array{topic: DeveloperDocTopic, title: string, icon: string}>
     */
    public function topics(): array
    {
        return array_map(static fn (DeveloperDocTopic $topic): array => [
            'topic' => $topic,
            'title' => $topic->label(),
            'icon' => $topic->icon(),
        ], DeveloperDocTopic::cases());
    }

    /**
     * @return array{title: string, summary: string, sections: list<array{heading: string, body?: list<string>, items?: list<string>, code?: string}>, note: string|null, updated: CarbonImmutable}
     */
    public function topic(DeveloperDocTopic $topic): array
    {
        /** @var array{title: string, summary: string, sections: list<array{heading: string, body?: list<string>, items?: list<string>, code?: string}>, note?: string} $content */
        $content = __('platform::developer_docs.topics.'.$topic->value);
        $file = dirname(__DIR__).'/resources/lang/'.app()->getLocale().'/developer_docs.php';
        $updated = is_file($file) ? CarbonImmutable::createFromTimestamp((int) filemtime($file)) : CarbonImmutable::now();

        return [
            'title' => $content['title'],
            'summary' => $content['summary'],
            'sections' => $content['sections'],
            'note' => $content['note'] ?? null,
            'updated' => $updated,
        ];
    }
}
