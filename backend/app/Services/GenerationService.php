<?php

namespace App\Services;

use Anthropic\Client;
use App\Prompts\PromptRegistry;
use App\Services\Generation\ModuleTools;
use App\Services\Generation\ToolCall;
use Illuminate\Support\Facades\Log;

class GenerationService
{
    public const MAX_ATTEMPTS = 3;

    // Source texts longer than this get an extraction pre-pass with the fast model
    private const EXTRACTION_THRESHOLD = 8000;

    // A 20-screen module is roughly 10–15k output tokens; leave headroom so a
    // verbose run is not cut off mid-module. Streaming keeps this within the
    // HTTP timeout.
    private const MAX_OUTPUT_TOKENS = 32000;

    private ?Client $client = null;
    private string $model;
    private string $fastModel;

    public function __construct(private PromptRegistry $prompts)
    {
        $this->model     = config('services.anthropic.model', 'claude-sonnet-4-6');
        $this->fastModel = config('services.anthropic.model_fast', 'claude-haiku-4-5-20251001');
    }

    /** Identifier of the module prompt in use, recorded on generation events. */
    public function promptId(): string
    {
        return $this->prompts->get(PromptRegistry::MODULE_GENERATION)->id();
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * Generate a module JSON payload from source text and generation parameters.
     * Retries up to MAX_ATTEMPTS times on malformed or invalid output.
     *
     * @throws \DomainException  content policy decline or model refusal (not retried)
     * @throws \RuntimeException if all attempts are exhausted
     */
    public function generate(
        string $sourceText,
        int    $durationMinutes,
        string $learningObjectives,
        int    $assessmentCount,
        string $projectTitle,
    ): array {
        // For large source texts, run a cheap extraction pre-pass first
        if (strlen($sourceText) > self::EXTRACTION_THRESHOLD) {
            Log::info('GenerationService: source text is ' . strlen($sourceText) . ' chars — running extraction pre-pass with fast model');
            $sourceText = $this->extractKeyContent($sourceText);
            Log::info('GenerationService: extracted content is ' . strlen($sourceText) . ' chars');
        }

        $prompt = $this->prompts->get(PromptRegistry::MODULE_GENERATION);
        $userPrompt = $prompt->render([
            'project_title'          => $projectTitle,
            'duration_minutes'       => $durationMinutes,
            'screen_target'          => $this->screenTarget($durationMinutes),
            'objectives_instruction' => $learningObjectives
                ? "Use these learning objectives provided by the author:\n{$learningObjectives}"
                : 'Generate 3–5 clear, measurable learning objectives based on the source content.',
            'assessment_instruction' => $assessmentCount > 0
                ? "Include exactly {$assessmentCount} question screens distributed throughout the module."
                : 'Do not include any question screens.',
            'source_text'            => $sourceText,
        ]);

        $lastError = null;
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $call = $this->requestModule($prompt->system, $userPrompt);
                $module = $this->normalize($this->moduleFrom($call), $durationMinutes);
                $this->validate($module);

                return $module;
            } catch (\DomainException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::warning("GenerationService attempt {$attempt} failed ({$prompt->id()}): " . $e->getMessage());
            }
        }

        throw new \RuntimeException('Generation failed after ' . self::MAX_ATTEMPTS . ' attempts: ' . $lastError->getMessage(), 0, $lastError);
    }

    /**
     * Send one generation request and return the model's tool call.
     * Protected so tests can substitute the API call.
     */
    protected function requestModule(string $system, string $userPrompt): ToolCall
    {
        $stream = $this->client()->messages->createStream(
            maxTokens: self::MAX_OUTPUT_TOKENS,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            model: $this->model,
            system: $system,
            temperature: 0.4,
            tools: ModuleTools::definitions(),
            // Forced tool use: the model must answer with submit_module or
            // decline_content. Claude Opus 5.5 / Sonnet 5.5 and later reject
            // forced tool_choice (400); moving ANTHROPIC_MODEL to one of
            // those means switching this to 'auto' (the system prompt
            // already tells the model which tool to call).
            toolChoice: ['type' => 'any', 'disableParallelToolUse' => true],
        );

        return ToolCall::fromStream($stream);
    }

    /** Turn a tool call into module data, or explain why it can't be. */
    private function moduleFrom(ToolCall $call): array
    {
        if ($call->stopReason === 'refusal') {
            throw new \DomainException('Content policy violation: the model declined to process this source.');
        }
        if ($call->stopReason === 'max_tokens') {
            throw new \RuntimeException('Output was cut off at the ' . self::MAX_OUTPUT_TOKENS . '-token limit.');
        }
        if ($call->name === ModuleTools::DECLINE) {
            throw new \DomainException('Content policy violation: ' . ($call->input['reason'] ?? 'unsafe content detected.'));
        }
        if ($call->name !== ModuleTools::SUBMIT) {
            throw new \RuntimeException('Model did not call ' . ModuleTools::SUBMIT . ' (stop reason: ' . ($call->stopReason ?? 'unknown') . ').');
        }

        return $call->input;
    }

    /** Fill the fields the server owns; the model doesn't write them. */
    private function normalize(array $module, int $durationMinutes): array
    {
        $module['schema_version'] = '1.0';
        $module['meta'] = ($module['meta'] ?? []) + ['duration_minutes' => $durationMinutes];
        $module['meta']['created_at'] = now()->toIso8601String();
        $module['attribution'] = ['unsplash' => []];
        $module['assessment'] = ['questions' => [], 'passing_score' => 0.8];
        $module['review_flags'] = [];

        return $module;
    }

    private function screenTarget(int $durationMinutes): int
    {
        return match (true) {
            $durationMinutes <= 10 => 10,
            $durationMinutes <= 20 => 15,
            default                => 20,
        };
    }

    /**
     * Extract and condense key content from a large source text using the fast model.
     * Keeps the important facts, concepts, and structure while discarding filler.
     */
    private function extractKeyContent(string $sourceText): string
    {
        $response = $this->client()->messages->create(
            maxTokens: 4000,
            messages: [['role' => 'user', 'content' => $sourceText]],
            model: $this->fastModel,
            system: $this->prompts->get(PromptRegistry::SOURCE_CONDENSE)->system,
            temperature: 0.2,
        );

        return $response->content[0]->text;
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: config('services.anthropic.api_key'),
            requestOptions: ['timeout' => 300], // 5 minutes per API call
        );
    }

    private function validate(array $module): void
    {
        $missing = array_diff(['meta', 'learning_objectives', 'screens'], array_keys($module));
        if ($missing) {
            throw new \RuntimeException('Module is missing required keys: ' . implode(', ', $missing));
        }

        if (empty($module['learning_objectives'])) {
            throw new \RuntimeException('learning_objectives must have at least 1 item');
        }

        $screens = $module['screens'];
        if (! is_array($screens) || count($screens) < 10) {
            throw new \RuntimeException('Module must have at least 10 screens, got ' . (is_array($screens) ? count($screens) : 0));
        }

        $ids = array_column($screens, 'id');
        if (count($ids) !== count($screens) || count($ids) !== count(array_unique($ids))) {
            throw new \RuntimeException('Every screen needs a unique id');
        }

        foreach ($screens as $i => $screen) {
            $idx = $i + 1;
            $type = $screen['type'] ?? '?';

            if (in_array($type, ['question', 'tabs', 'process', 'flashcard', 'video', 'scenario'], true) && empty($screen['interactions'])) {
                throw new \RuntimeException("Screen {$idx} ({$type}) must have at least 1 interaction");
            }

            // Video screens carry no image (the prompt asks for null); every
            // other screen needs one for image enrichment to fill.
            if ($type !== 'video' && empty($screen['image'])) {
                throw new \RuntimeException("Screen {$idx} ({$type}) must have a non-null image object");
            }
        }
    }
}
