<?php

namespace Tests\Unit;

use App\Prompts\PromptRegistry;
use App\Services\Generation\ModuleTools;
use App\Services\Generation\ToolCall;
use App\Services\GenerationService;
use Tests\TestCase;

class GenerationServiceTest extends TestCase
{
    /**
     * A GenerationService whose API call returns the given responses in order
     * and records the prompts it was sent.
     */
    private function serviceReturning(ToolCall|\Throwable ...$responses): GenerationService
    {
        return new class(new PromptRegistry(), $responses) extends GenerationService
        {
            public array $requests = [];

            public function __construct(PromptRegistry $prompts, private array $responses)
            {
                parent::__construct($prompts);
            }

            protected function requestModule(string $system, string $userPrompt): ToolCall
            {
                $this->requests[] = compact('system', 'userPrompt');
                $next = array_shift($this->responses) ?? throw new \LogicException('No response left');

                return $next instanceof \Throwable ? throw $next : $next;
            }
        };
    }

    private function generate(GenerationService $service): array
    {
        return $service->generate(
            sourceText: 'Short source about fire extinguishers.',
            durationMinutes: 15,
            learningObjectives: '',
            assessmentCount: 2,
            projectTitle: 'Fire safety',
        );
    }

    private function validModule(): array
    {
        $screens = [];
        for ($i = 1; $i <= 9; $i++) {
            $screens[] = [
                'id' => "scr_{$i}", 'type' => 'image_text', 'title' => "Screen {$i}", 'body' => 'Body',
                'image' => ['url' => 'https://placeholder.wbtforge.dev/800x450', 'alt_text' => 'alt'],
                'interactions' => [], 'accessibility' => ['alt_text' => 'alt'],
            ];
        }
        // The prompt asks for image: null on video screens; that must pass.
        $screens[] = [
            'id' => 'scr_10', 'type' => 'video', 'title' => 'Using an extinguisher', 'body' => 'Watch',
            'image' => null, 'interactions' => [['kind' => 'video', 'url' => '', 'caption' => 'PASS']],
            'accessibility' => ['alt_text' => null],
        ];

        return [
            'meta' => ['title' => 'Fire safety', 'audience' => 'Staff', 'duration_minutes' => 15, 'summary' => 'S', 'source_summary' => 'SS'],
            'sources' => ['Provided course material'],
            'learning_objectives' => ['Use an extinguisher'],
            'screens' => $screens,
        ];
    }

    private function submit(array $input): ToolCall
    {
        return new ToolCall(ModuleTools::SUBMIT, $input, 'tool_use');
    }

    public function test_returns_module_with_server_owned_fields_filled(): void
    {
        $service = $this->serviceReturning($this->submit($this->validModule()));

        $module = $this->generate($service);

        $this->assertSame('1.0', $module['schema_version']);
        $this->assertSame(['unsplash' => []], $module['attribution']);
        $this->assertSame([], $module['review_flags']);
        $this->assertSame(0.8, $module['assessment']['passing_score']);
        $this->assertArrayHasKey('created_at', $module['meta']);
        $this->assertCount(10, $module['screens']);
    }

    public function test_renders_the_registry_prompt(): void
    {
        $service = $this->serviceReturning($this->submit($this->validModule()));

        $this->generate($service);

        [$request] = $service->requests;
        $this->assertSame((new PromptRegistry())->get(PromptRegistry::MODULE_GENERATION)->system, $request['system']);
        $this->assertStringContainsString('PROJECT TITLE: Fire safety', $request['userPrompt']);
        $this->assertStringContainsString('TARGET SCREEN COUNT: 15 screens', $request['userPrompt']);
        $this->assertStringContainsString('Include exactly 2 question screens', $request['userPrompt']);
        $this->assertStringContainsString('Short source about fire extinguishers.', $request['userPrompt']);
        $this->assertStringNotContainsString('{{', $request['userPrompt']);
        $this->assertSame('module_generation@2', $service->promptId());
    }

    public function test_invalid_output_is_retried(): void
    {
        $tooShort = $this->validModule();
        $tooShort['screens'] = array_slice($tooShort['screens'], 0, 3);

        $service = $this->serviceReturning(
            $this->submit($tooShort),
            new \JsonException('Syntax error'),
            $this->submit($this->validModule()),
        );

        $module = $this->generate($service);

        $this->assertCount(3, $service->requests);
        $this->assertCount(10, $module['screens']);
    }

    public function test_gives_up_after_max_attempts_with_the_last_error(): void
    {
        $service = $this->serviceReturning(
            new ToolCall(null, [], 'end_turn'),
            new ToolCall(null, [], 'end_turn'),
            new ToolCall(ModuleTools::SUBMIT, [], 'max_tokens'),
        );

        try {
            $this->generate($service);
            $this->fail('Expected generation to fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('after 3 attempts', $e->getMessage());
            $this->assertStringContainsString('cut off', $e->getMessage());
        }
    }

    public function test_content_decline_is_not_retried(): void
    {
        $service = $this->serviceReturning(new ToolCall(ModuleTools::DECLINE, ['reason' => 'Instructions for harm.'], 'tool_use'));

        try {
            $this->generate($service);
            $this->fail('Expected a content policy violation');
        } catch (\DomainException $e) {
            $this->assertSame('Content policy violation: Instructions for harm.', $e->getMessage());
        }
        $this->assertCount(1, $service->requests);
    }

    public function test_model_refusal_is_not_retried(): void
    {
        $service = $this->serviceReturning(new ToolCall(null, [], 'refusal'));

        $this->expectException(\DomainException::class);
        $this->generate($service);
    }

    public function test_non_video_screen_without_image_is_rejected(): void
    {
        $module = $this->validModule();
        $module['screens'][0]['image'] = null;
        $service = $this->serviceReturning($this->submit($module), $this->submit($module), $this->submit($module));

        $this->expectExceptionMessage('Screen 1 (image_text) must have a non-null image object');
        $this->generate($service);
    }

    public function test_tool_schema_covers_every_screen_type_the_frontend_accepts(): void
    {
        $submit = collect(ModuleTools::definitions())->firstWhere('name', ModuleTools::SUBMIT);
        $types = $submit['inputSchema']['properties']['screens']['items']['properties']['type']['enum'];

        $frontend = file_get_contents(base_path('../frontend/src/lib/moduleSchema.ts'));
        preg_match("/type: z\.enum\(\[([^\]]+)\]\)/", $frontend, $m);
        $frontendTypes = array_map(fn ($t) => trim($t, " '"), explode(',', $m[1]));

        $this->assertEqualsCanonicalizing($frontendTypes, $types);
    }
}
