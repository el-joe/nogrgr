<?php

namespace App\Modules\Support\Services;

use App\Modules\Support\Enums\HelpTopic;
use App\Modules\Support\Repositories\Contracts\HelpFeedbackRepositoryContract;
use App\Shared\Services\BaseService;
use Illuminate\Support\Str;

/**
 * Vendor help center (VEN-092 / 092a): topics with their articles, one article's content
 * (summary, sections with paragraphs / steps / tips) from support::help in the reader's
 * language, search over titles, summaries and section text, and "Was this helpful?" votes.
 */
class HelpCenterService extends BaseService
{
    /**
     * Average reading speed used for "N min read".
     */
    private const WORDS_PER_MINUTE = 180;

    public function __construct(private readonly HelpFeedbackRepositoryContract $feedback) {}

    /**
     * @return list<array{topic: HelpTopic, title: string, icon: string, count: int}>
     */
    public function topics(): array
    {
        return array_map(static fn (HelpTopic $topic): array => [
            'topic' => $topic,
            'title' => $topic->label(),
            'icon' => $topic->icon(),
            'count' => count($topic->articles()),
        ], HelpTopic::cases());
    }

    /**
     * Article cards of one topic.
     *
     * @return list<array{slug: string, title: string, summary: string, topic: HelpTopic, minutes: int}>
     */
    public function articlesOf(HelpTopic $topic): array
    {
        return array_map(fn (string $slug): array => $this->card($slug, $topic), $topic->articles());
    }

    /**
     * @return array{slug: string, title: string, summary: string, topic: HelpTopic, minutes: int, sections: list<array{heading: string, body?: list<string>, steps?: list<string>, tip?: string}>, related: list<array{slug: string, title: string}>}|null
     */
    public function article(string $slug): ?array
    {
        $topic = HelpTopic::forArticle($slug);

        if ($topic === null) {
            return null;
        }

        $content = $this->content($slug);
        $related = array_values(array_filter($topic->articles(), static fn (string $other): bool => $other !== $slug));

        return [
            ...$this->card($slug, $topic),
            'sections' => $content['sections'],
            'related' => array_map(fn (string $other): array => ['slug' => $other, 'title' => $this->content($other)['title']], $related),
        ];
    }

    /**
     * Articles whose title, summary or section text contains every word of the query.
     *
     * @return list<array{slug: string, title: string, summary: string, topic: HelpTopic, minutes: int}>
     */
    public function search(string $query): array
    {
        $words = array_values(array_filter(preg_split('/\s+/u', mb_strtolower(trim($query))) ?: [], static fn (string $word): bool => mb_strlen($word) > 1));

        if ($words === []) {
            return [];
        }

        $results = [];

        foreach (HelpTopic::cases() as $topic) {
            foreach ($topic->articles() as $slug) {
                $content = $this->content($slug);
                $haystack = mb_strtolower($content['title'].' '.$content['summary'].' '.$this->plainText($content['sections']).' '.$topic->label());
                $titleHits = 0;

                foreach ($words as $word) {
                    if (! str_contains($haystack, $word)) {
                        continue 2;
                    }

                    $titleHits += str_contains(mb_strtolower($content['title']), $word) ? 1 : 0;
                }

                $results[] = ['score' => $titleHits, 'card' => $this->card($slug, $topic)];
            }
        }

        usort($results, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_map(static fn (array $row): array => $row['card'], $results);
    }

    public function vote(int $staffId, string $slug, bool $helpful): void
    {
        $this->feedback->record($staffId, $slug, $helpful);
    }

    public function voteOf(int $staffId, string $slug): ?bool
    {
        return $this->feedback->voteOf($staffId, $slug);
    }

    /**
     * @return array{slug: string, title: string, summary: string, topic: HelpTopic, minutes: int}
     */
    private function card(string $slug, HelpTopic $topic): array
    {
        $content = $this->content($slug);
        $words = Str::wordCount($content['title'].' '.$content['summary'].' '.$this->plainText($content['sections']));

        // Arabic words are not counted by str_word_count: fall back to whitespace splitting.
        $words = max($words, count(preg_split('/\s+/u', trim($content['summary'].' '.$this->plainText($content['sections']))) ?: []));

        return [
            'slug' => $slug,
            'title' => $content['title'],
            'summary' => $content['summary'],
            'topic' => $topic,
            'minutes' => max(1, (int) ceil($words / self::WORDS_PER_MINUTE)),
        ];
    }

    /**
     * @return array{title: string, summary: string, sections: list<array{heading: string, body?: list<string>, steps?: list<string>, tip?: string}>}
     */
    private function content(string $slug): array
    {
        /** @var array{title: string, summary: string, sections: list<array{heading: string, body?: list<string>, steps?: list<string>, tip?: string}>} $content */
        $content = __('support::help.articles.'.$slug);

        return $content;
    }

    /**
     * @param  list<array{heading: string, body?: list<string>, steps?: list<string>, tip?: string}>  $sections
     */
    private function plainText(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            $parts[] = $section['heading'];
            array_push($parts, ...($section['body'] ?? []), ...($section['steps'] ?? []));
            $parts[] = $section['tip'] ?? '';
        }

        return implode(' ', $parts);
    }
}
