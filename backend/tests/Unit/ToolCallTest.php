<?php

namespace Tests\Unit;

use Anthropic\Core\Conversion;
use Anthropic\Messages\RawMessageStreamEvent;
use App\Services\Generation\ToolCall;
use PHPUnit\Framework\TestCase;

class ToolCallTest extends TestCase
{
    /** Decode a wire-format event exactly as the SDK's SSE stream does. */
    private function event(array $wire): object
    {
        return Conversion::coerce(RawMessageStreamEvent::class, $wire);
    }

    private function toolStart(int $index, string $name): object
    {
        return $this->event(['type' => 'content_block_start', 'index' => $index, 'content_block' => ['type' => 'tool_use', 'id' => "toolu_{$index}", 'name' => $name, 'input' => []]]);
    }

    private function jsonDelta(int $index, string $json): object
    {
        return $this->event(['type' => 'content_block_delta', 'index' => $index, 'delta' => ['type' => 'input_json_delta', 'partial_json' => $json]]);
    }

    private function stop(string $reason): object
    {
        return $this->event(['type' => 'message_delta', 'delta' => ['stop_reason' => $reason, 'stop_sequence' => null], 'usage' => ['output_tokens' => 10]]);
    }

    public function test_assembles_input_from_fragments_and_ignores_text(): void
    {
        $call = ToolCall::fromStream([
            $this->event(['type' => 'content_block_start', 'index' => 0, 'content_block' => ['type' => 'text', 'text' => '', 'citations' => null]]),
            $this->event(['type' => 'content_block_delta', 'index' => 0, 'delta' => ['type' => 'text_delta', 'text' => 'Here you go']]),
            $this->toolStart(1, 'submit_module'),
            $this->jsonDelta(1, '{"meta": {"ti'),
            $this->jsonDelta(1, 'tle": "Fire é"}, "screens": []}'),
            $this->stop('tool_use'),
        ]);

        $this->assertSame('submit_module', $call->name);
        $this->assertSame(['meta' => ['title' => 'Fire é'], 'screens' => []], $call->input);
        $this->assertSame('tool_use', $call->stopReason);
    }

    public function test_only_the_first_tool_call_is_kept(): void
    {
        $call = ToolCall::fromStream([
            $this->toolStart(0, 'decline_content'),
            $this->jsonDelta(0, '{"reason": "harmful"}'),
            $this->toolStart(1, 'submit_module'),
            $this->jsonDelta(1, '{"screens": []}'),
            $this->stop('tool_use'),
        ]);

        $this->assertSame('decline_content', $call->name);
        $this->assertSame(['reason' => 'harmful'], $call->input);
    }

    public function test_truncated_output_reports_stop_reason_instead_of_parse_error(): void
    {
        $call = ToolCall::fromStream([
            $this->toolStart(0, 'submit_module'),
            $this->jsonDelta(0, '{"screens": [{"id": "scr_0'),
            $this->stop('max_tokens'),
        ]);

        $this->assertSame('max_tokens', $call->stopReason);
        $this->assertSame([], $call->input);
    }

    public function test_response_without_a_tool_call(): void
    {
        $call = ToolCall::fromStream([$this->stop('end_turn')]);

        $this->assertNull($call->name);
        $this->assertSame('end_turn', $call->stopReason);
    }

    public function test_malformed_json_throws(): void
    {
        $this->expectException(\JsonException::class);

        ToolCall::fromStream([
            $this->toolStart(0, 'submit_module'),
            $this->jsonDelta(0, '{"screens": ]'),
            $this->stop('tool_use'),
        ]);
    }
}
