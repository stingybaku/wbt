<?php

namespace App\Services\Generation;

use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\RawContentBlockDeltaEvent;
use Anthropic\Messages\RawContentBlockStartEvent;
use Anthropic\Messages\RawMessageDeltaEvent;
use Anthropic\Messages\ToolUseBlock;

/** The first tool call in a model response, plus why the response stopped. */
final class ToolCall
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public readonly ?string $name,
        public readonly array $input,
        public readonly ?string $stopReason,
    ) {}

    /**
     * Build the tool call from a Messages stream. The SDK has no accumulator,
     * so collect the input_json_delta fragments of the first tool_use block
     * and decode them once the stream ends.
     *
     * @param iterable<object> $events
     */
    public static function fromStream(iterable $events): self
    {
        $index = null;
        $name = null;
        $json = '';
        $stopReason = null;

        foreach ($events as $event) {
            if ($event instanceof RawContentBlockStartEvent
                && $index === null
                && $event->contentBlock instanceof ToolUseBlock) {
                $index = $event->index;
                $name = $event->contentBlock->name;
            } elseif ($event instanceof RawContentBlockDeltaEvent
                && $event->index === $index
                && $event->delta instanceof InputJSONDelta) {
                $json .= $event->delta->partialJSON;
            } elseif ($event instanceof RawMessageDeltaEvent) {
                $stopReason = $event->delta->stopReason ?? $stopReason;
            }
        }

        $input = [];
        // A truncated response leaves half a JSON document; report the stop
        // reason rather than a confusing parse error.
        if ($name !== null && trim($json) !== '' && $stopReason !== 'max_tokens') {
            $input = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($input)) {
                throw new \JsonException('Tool input is not a JSON object.');
            }
        }

        return new self($name, $input, $stopReason);
    }
}
