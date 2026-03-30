<?php

namespace App\Services;

use Anthropic\Client;
use Illuminate\Support\Facades\Log;

class GenerationService
{
    private Client $client;
    private string $model;

    public function __construct()
    {
        $this->client = new Client(apiKey: config('services.anthropic.api_key'));
        $this->model  = config('services.anthropic.model', 'claude-sonnet-4-6');
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

You MUST respond with a single valid JSON object and nothing else — no explanation, no markdown fences, no commentary before or after. The JSON must strictly follow the schema below.

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

SCREEN TYPES (use all five types, distributed across the module):

1. text screen:
{
  "id": "scr_001",
  "type": "text",
  "title": string,
  "body": string,
  "image": null,
  "interactions": [],
  "accessibility": { "alt_text": null }
}

2. image_text screen (image field MUST NOT be null):
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
  "image": null,
  "interactions": [
    {
      "kind": "accordion",
      "items": [
        { "title": string, "body": string },
        { "title": string, "body": string }
      ]
    }
  ],
  "accessibility": { "alt_text": null }
}

4. click_reveal screen:
{
  "id": "scr_004",
  "type": "click_reveal",
  "title": string,
  "body": string,
  "image": null,
  "interactions": [
    {
      "kind": "click_reveal",
      "items": [
        { "title": string, "body": string },
        { "title": string, "body": string }
      ]
    }
  ],
  "accessibility": { "alt_text": null }
}

5. question screen (MUST have at least 1 interaction):
{
  "id": "scr_005",
  "type": "question",
  "title": string,
  "body": string,
  "image": null,
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
  "accessibility": { "alt_text": null }
}

VALIDATION RULES:
- All screen IDs must be unique
- Minimum 10 screens total
- question screens MUST include at least 1 interaction
- image_text screens MUST have a non-null image object
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

        $response = $this->client->messages->create(
            maxTokens: 8192,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            model: $this->model,
            system: $systemPrompt,
            temperature: 0.4,
        );

        return $response->content[0]->text;
    }

    private function extractJson(string $raw): array
    {
        // Strip any accidental markdown fences
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $decoded = json_decode($cleaned, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("JSON parse failed: " . json_last_error_msg() . "\nRaw (first 500 chars): " . substr($raw, 0, 500));
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

            if ($screen['type'] === 'image_text' && empty($screen['image'])) {
                throw new \RuntimeException("Screen {$idx} (image_text) must have a non-null image object");
            }
        }
    }
}
