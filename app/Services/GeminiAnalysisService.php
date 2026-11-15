<?php

namespace App\Services;

use App\Models\ObservationAnalysis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class GeminiAnalysisService
{
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const CATEGORIES = [
        'air_quality',
        'water_quality',
        'waste',
        'noise',
        'green_space',
        'biodiversity',
        'infrastructure',
        'other',
    ];

    public function analyze(ObservationAnalysis $analysis): array
    {
        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model');

        if (!$apiKey || !$model) {
            throw new RuntimeException('Gemini configuration is missing.');
        }

        $media = $analysis->media;

        if (!$media) {
            throw new RuntimeException('Observation media not found.');
        }

        if (!in_array($media->mime_type, self::ALLOWED_MIME_TYPES, true)) {
            throw new RuntimeException('Unsupported image MIME type.');
        }

        $disk = Storage::disk($media->disk);

        if (!$disk->exists($media->path)) {
            throw new RuntimeException('Image file not found.');
        }

        $image = $disk->get($media->path);

        if (strlen($image) > self::MAX_IMAGE_SIZE) {
            throw new RuntimeException('Image exceeds the maximum allowed size.');
        }

        $imageInfo = @getimagesizefromstring($image);

        if (
            $imageInfo === false ||
            !isset($imageInfo['mime']) ||
            !in_array($imageInfo['mime'], self::ALLOWED_MIME_TYPES, true) ||
            $imageInfo['mime'] !== $media->mime_type
        ) {
            throw new RuntimeException('Image content does not match its MIME type.');
        }

        $prompt = <<<'PROMPT'
You are an environmental observation analysis assistant.

Analyze the provided photograph for environmental conditions.

Return only valid JSON with this structure:
{
  "summary": "A concise visual summary in Turkish.",
  "detected_category": "One category from the allowed list.",
  "environmental_issue": true,
  "confidence": 0.85,
  "findings": [
    "A visible finding"
  ],
  "recommendations": [
    "A practical recommendation"
  ]
}

Allowed categories:
air_quality, water_quality, waste, noise, green_space,
biodiversity, infrastructure, other.

Rules:
- Only describe things visibly supported by the photograph.
- Never invent measurements or invisible facts.
- If the image does not provide enough evidence, explain that in the summary.
- Confidence must be between 0 and 1.
- Do not assign an Eco Score or moderation decision.
- Return Turkish text.
PROMPT;

        $response = Http::withHeaders([
            'x-goog-api-key' => $apiKey,
        ])
            ->connectTimeout(10)
            ->timeout((int) config('services.gemini.timeout', 90))
            ->post(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                                [
                                    'inline_data' => [
                                        'mime_type' => $imageInfo['mime'],
                                        'data' => base64_encode($image),
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]
            );

        $response->throw();

        $text = $response->json('candidates.0.content.parts.0.text');

        if (!is_string($text) || trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        $result = json_decode($text, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($result)) {
            throw new RuntimeException('Gemini returned an invalid analysis format.');
        }

        $this->validateResult($result);

        return $result;
    }

    private function validateResult(array $result): void
    {
        if (
            !isset($result['summary']) ||
            !is_string($result['summary']) ||
            trim($result['summary']) === '' ||
            mb_strlen($result['summary']) > 2000 ||

            !isset($result['detected_category']) ||
            !in_array($result['detected_category'], self::CATEGORIES, true) ||

            !array_key_exists('environmental_issue', $result) ||
            !is_bool($result['environmental_issue']) ||

            !isset($result['confidence']) ||
            !is_numeric($result['confidence']) ||
            $result['confidence'] < 0 ||
            $result['confidence'] > 1 ||

            !$this->isValidTextList($result['findings'] ?? null) ||
            !$this->isValidTextList($result['recommendations'] ?? null)
        ) {
            throw new RuntimeException('Gemini returned an invalid analysis format.');
        }
    }

    private function isValidTextList(mixed $items): bool
    {
        if (!is_array($items) || count($items) > 10) {
            return false;
        }

        foreach ($items as $item) {
            if (
                !is_string($item) ||
                trim($item) === '' ||
                mb_strlen($item) > 500
            ) {
                return false;
            }
        }

        return true;
    }
}
