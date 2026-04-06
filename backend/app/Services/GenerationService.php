<?php

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

class GenerationService
{
    private Client $client;
    private string $model;
    private string $fastModel;

    // Source texts longer than this get an extraction pre-pass with the fast model
    private const EXTRACTION_THRESHOLD = 8000;

    public function __construct()
    {
        $this->client    = new Client(
            apiKey: config('services.anthropic.api_key'),
            requestOptions: ['timeout' => 300], // 5 minutes per API call
        );
        $this->model     = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->fastModel = config('services.anthropic.model_fast', 'claude-haiku-4-5-20251001');
    }

    /**
     * Generate a module JSON payload from source text and generation parameters.
     * Retries up to $maxAttempts times on JSON parse or schema validation failure.
     *
     * @throws \RuntimeException if all attempts are exhausted
     */
    public function generate(
        string $sourceText,
        int    $durationMinutes,
        string $learningObjectives,
        int    $assessmentCount,
        string $projectTitle,
    ): array {
        $lastError = null;

        // For large source texts, run a cheap extraction pre-pass first
        if (strlen($sourceText) > self::EXTRACTION_THRESHOLD) {
            Log::info('GenerationService: source text is ' . strlen($sourceText) . ' chars — running extraction pre-pass with fast model');
            $sourceText = $this->extractKeyContent($sourceText);
            Log::info('GenerationService: extracted content is ' . strlen($sourceText) . ' chars');
        }

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $raw = $this->callClaude($sourceText, $durationMinutes, $learningObjectives, $assessmentCount, $projectTitle);
                $parsed = $this->extractJson($raw);
                $this->validate($parsed);
                return $parsed;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("GenerationService attempt {$attempt} failed: " . $e->getMessage());
            }
        }

        throw new \RuntimeException("Generation failed after 3 attempts: " . $lastError->getMessage(), 0, $lastError);
    }

    private function callClaude(
        string $sourceText,
        int    $durationMinutes,
        string $learningObjectives,
        int    $assessmentCount,
        string $projectTitle,
    ): string {
        $screenTarget = match (true) {
            $durationMinutes <= 10 => 10,
            $durationMinutes <= 20 => 15,
            default                => 20,
        };

        $objectivesInstruction = $learningObjectives
            ? "Use these learning objectives provided by the author:\n{$learningObjectives}"
            : "Generate 3–5 clear, measurable learning objectives based on the source content.";

        $assessmentInstruction = $assessmentCount > 0
            ? "Include exactly {$assessmentCount} question screens distributed throughout the module."
            : "Do not include any question screens.";

        $systemPrompt = <<<SYSTEM
You are an expert instructional designer. Your task is to convert source content into a structured Web-Based Training (WBT) module.

You MUST respond with a single valid JSON object and nothing else — no explanation, no markdown fences (no ```), no commentary before or after. The JSON must strictly follow the schema below.

CRITICAL: All string values must be valid JSON strings. Do NOT include raw newlines, tabs, or any control characters inside string values — use \\n for line breaks if needed. Do NOT wrap the response in ```json or any code block.

REQUIRED JSON SCHEMA:
{
  "schema_version": "1.0",
  "meta": {
    "title": string,
    "audience": string,
    "duration_minutes": integer (10–30),
    "source_summary": string (1–2 sentences describing the source),
    "created_at": ISO 8601 datetime string
  },
  "learning_objectives": [string, ...],  // minimum 1
  "screens": [...],  // minimum 10, see screen types below
  "assessment": {
    "questions": [],
    "passing_score": 0.8
  },
  "attribution": {
    "unsplash": []
  },
  "review_flags": []
}

IMPORTANT IMAGE RULE: Every screen of every type MUST include an image object. Never use null for the image field. Always set image to { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string } with a descriptive alt_text relevant to the screen content.

SCREEN TYPES (use all five types, distributed across the module):

1. text screen:
{
  "id": "scr_001",
  "type": "text",
  "title": string,
  "body": string,
  "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string },
  "interactions": [],
  "accessibility": { "alt_text": string }
}

2. image_text screen (image is the visual focus):
{
  "id": "scr_002",
  "type": "image_text",
  "title": string,
  "body": string,
  "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string },
  "interactions": [],
  "accessibility": { "alt_text": string }
}

3. accordion screen:
{
  "id": "scr_003",
  "type": "accordion",
  "title": string,
  "body": string,
  "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string },
  "interactions": [
    {
      "kind": "accordion",
      "items": [
        { "title": string, "body": string },
        { "title": string, "body": string }
      ]
    }
  ],
  "accessibility": { "alt_text": string }
}

4. click_reveal screen:
{
  "id": "scr_004",
  "type": "click_reveal",
  "title": string,
  "body": string,
  "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string },
  "interactions": [
    {
      "kind": "click_reveal",
      "items": [
        { "title": string, "body": string, "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string } },
        { "title": string, "body": string, "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string } }
      ]
    }
  ],
  "accessibility": { "alt_text": string }
}

5. question screen (MUST have at least 1 interaction):
{
  "id": "scr_005",
  "type": "question",
  "title": string,
  "body": string,
  "image": { "url": "https://placeholder.wbtforge.dev/800x450", "alt_text": string },
  "interactions": [
    {
      "kind": "single_choice",
      "prompt": string,
      "choices": [
        { "id": "a", "text": string, "is_correct": true },
        { "id": "b", "text": string, "is_correct": false },
        { "id": "c", "text": string, "is_correct": false }
      ],
      "feedback": {
        "correct": string,
        "incorrect": string
      }
    }
  ],
  "accessibility": { "alt_text": string }
}

VALIDATION RULES:
- All screen IDs must be unique
- Minimum 10 screens total
- Every screen MUST have a non-null image object with a descriptive alt_text
- question screens MUST include at least 1 interaction
- accordion and click_reveal interactions MUST have at least 2 items
- single_choice and multi_choice interactions MUST have at least 2 choices
SYSTEM;

        $userPrompt = <<<USER
Generate a WBT module from the following content.

PROJECT TITLE: {$projectTitle}
TARGET DURATION: {$durationMinutes} minutes
TARGET SCREEN COUNT: {$screenTarget} screens
{$objectivesInstruction}
{$assessmentInstruction}

SOURCE CONTENT:
{$sourceText}
USER;

        $stream = $this->client->messages->createStream(
            maxTokens: 16000,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            model: $this->model,
            system: $systemPrompt,
            temperature: 0.4,
        );

        $text = '';
        foreach ($stream as $event) {
            if ($event->type === 'content_block_delta' && $event->delta->type === 'text_delta') {
                $text .= $event->delta->text;
            }
        }

        return $text;
    }

    /**
     * Extract and condense key content from a large source text using the fast model.
     * Keeps the important facts, concepts, and structure while discarding filler.
     */
    private function extractKeyContent(string $sourceText): string
    {
        $response = $this->client->messages->create(
            maxTokens: 4000,
            messages: [['role' => 'user', 'content' => $sourceText]],
            model: $this->fastModel,
            system: <<<'SYSTEM'
You are a content extraction assistant. Your task is to extract and condense the key information from the provided document for use in creating a training course.

Output a structured summary that preserves:
- All important concepts, definitions, and explanations
- Key facts, data points, and statistics
- Processes, steps, or procedures
- Examples that illustrate important points
- Any learning-relevant structure (sections, topics)

Discard: introductory boilerplate, repetition, administrative content, footnotes, and anything not relevant to learning.

Output plain text only — no JSON, no markdown, no commentary. Be thorough but concise. Target around 3000–4000 words.
SYSTEM,
            temperature: 0.2,
        );

        return $response->content[0]->text;
    }

    private function extractJson(string $raw): array
    {
        // Strip BOM if present
        $cleaned = ltrim($raw, "\xEF\xBB\xBF");

        // Strip markdown fences (``` or ```json) at the start and end
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($cleaned));
        $cleaned = preg_replace('/\s*```\s*$/', '', $cleaned);
        $cleaned = trim($cleaned);

        // If the response is wrapped in an outer code block with content after, extract the JSON object/array
        if (!str_starts_with($cleaned, '{') && !str_starts_with($cleaned, '[')) {
            if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])\s*$/', $cleaned, $m)) {
                $cleaned = $m[1];
            }
        }

        // Remove control characters that are illegal in JSON string values
        // (preserve tab 0x09, LF 0x0A, CR 0x0D which are valid JSON whitespace)
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Strip control characters from the preview so it is safe to log / embed in JSON
            $preview = preg_replace('/[\x00-\x1F\x7F]/', ' ', substr($raw, 0, 500));
            throw new \RuntimeException("JSON parse failed: " . json_last_error_msg() . " | Raw (first 500): " . $preview);
        }

        return $decoded;
    }

    private function validate(array $module): void
    {
        $missing = array_diff(['schema_version', 'meta', 'learning_objectives', 'screens', 'attribution'], array_keys($module));
        if ($missing) {
            throw new \RuntimeException("Module JSON missing required keys: " . implode(', ', $missing));
        }

        if (empty($module['learning_objectives'])) {
            throw new \RuntimeException("learning_objectives must have at least 1 item");
        }

        $screens = $module['screens'] ?? [];
        if (count($screens) < 10) {
            throw new \RuntimeException("Module must have at least 10 screens, got " . count($screens));
        }

        $ids = array_column($screens, 'id');
        if (count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException("Duplicate screen IDs detected");
        }

        foreach ($screens as $i => $screen) {
            $idx = $i + 1;

            if ($screen['type'] === 'question' && empty($screen['interactions'])) {
                throw new \RuntimeException("Screen {$idx} (question) must have at least 1 interaction");
            }

            if (empty($screen['image'])) {
                throw new \RuntimeException("Screen {$idx} ({$screen['type']}) must have a non-null image object");
            }
        }
    }
}
