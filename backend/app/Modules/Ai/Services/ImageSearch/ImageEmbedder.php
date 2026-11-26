<?php

namespace App\Modules\Ai\Services\ImageSearch;

use App\Modules\Ai\Enums\AiErrorType;
use App\Modules\Ai\Exceptions\AiRequestException;
use App\Modules\Ai\Services\Tools\OpenAiTools;
use App\Modules\Ai\Support\OpenAiCompatibleClient;

/**
 * Image → vector (same approach as the old system's image search): the OpenAI vision
 * model describes the product in the photo in a fixed English shape, then the text is
 * embedded (OpenAI embeddings, reduced dimensions so a store's vectors stay small).
 * Shopper photos and catalogue images go through the same two steps, so cosine
 * similarity compares like with like.
 */
class ImageEmbedder
{
    public const DIMENSIONS = 256;

    private const PROMPT = 'You index product photos for an online shop\'s visual search. Describe ONLY the main product in the image in English, in one paragraph of at most 60 words: product type, category, shape, main colours, material, pattern, notable details and style. No brand guesses, no background, no marketing words. If there is no product, describe the main object.';

    /**
     * @return array{description: string, vector: list<float>}
     */
    public function embed(OpenAiCompatibleClient $client, string $binary, string $mime): array
    {
        if ($binary === '') {
            throw new AiRequestException(AiErrorType::InvalidResponse, 'Empty image.');
        }

        $description = trim($client->complete([
            ['role' => 'system', 'content' => self::PROMPT],
            ['role' => 'user', 'content' => [
                ['type' => 'image_url', 'image_url' => ['url' => 'data:'.$mime.';base64,'.base64_encode($binary), 'detail' => 'low']],
            ]],
        ], ['temperature' => 0, 'max_tokens' => 160]));

        return ['description' => mb_substr($description, 0, 1000), 'vector' => $this->vector($client, $description)];
    }

    /**
     * @return list<float>
     */
    public function vector(OpenAiCompatibleClient $client, string $text): array
    {
        return self::normalize($client->embedding($text, OpenAiTools::embeddingModel(), self::DIMENSIONS));
    }

    /**
     * Unit-length vector rounded to 6 decimals (cosine = dot product).
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(static fn (float $value): float => $value * $value, $vector)));

        return $norm <= 0.0 ? $vector : array_map(static fn (float $value): float => round($value / $norm, 6), $vector);
    }

    /**
     * Cosine similarity of two vectors (0 when the sizes differ or one is empty).
     *
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    public static function cosine(array $a, array $b): float
    {
        $count = count($a);

        if ($count === 0 || $count !== count($b)) {
            return 0.0;
        }

        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }

        return $na <= 0.0 || $nb <= 0.0 ? 0.0 : $dot / (sqrt($na) * sqrt($nb));
    }
}
