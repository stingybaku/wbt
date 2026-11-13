<?php

namespace App\Services\Generation;

/**
 * Tool definitions the generation model must answer with.
 *
 * submit_module's input schema mirrors frontend/src/lib/moduleSchema.ts for
 * the fields the model writes. Server-owned fields (schema_version,
 * meta.created_at, attribution, assessment, review_flags) are filled in by
 * GenerationService::normalize(), so the model never spends tokens on them.
 *
 * The schemas are not `strict`: the interaction union is too large for
 * constrained decoding to be worth the risk, and GenerationService::validate()
 * enforces the rules the frontend relies on.
 */
final class ModuleTools
{
    public const SUBMIT = 'submit_module';
    public const DECLINE = 'decline_content';

    public static function definitions(): array
    {
        return [self::submitModule(), self::declineContent()];
    }

    private static function submitModule(): array
    {
        $image = [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string'],
                'alt_text' => ['type' => 'string'],
            ],
            'required' => ['url', 'alt_text'],
        ];
        $item = [
            'type' => 'object',
            'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string']],
            'required' => ['title', 'body'],
        ];
        $items = fn (string $kind, array $itemSchema) => [
            'type' => 'object',
            'properties' => [
                'kind' => ['enum' => [$kind]],
                'items' => ['type' => 'array', 'items' => $itemSchema, 'minItems' => 2],
            ],
            'required' => ['kind', 'items'],
        ];
        $choiceQuestion = fn (string $kind) => [
            'type' => 'object',
            'properties' => [
                'kind' => ['enum' => [$kind]],
                'prompt' => ['type' => 'string'],
                'choices' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'text' => ['type' => 'string'],
                            'is_correct' => ['type' => 'boolean'],
                        ],
                        'required' => ['id', 'text', 'is_correct'],
                    ],
                ],
                'feedback' => [
                    'type' => 'object',
                    'properties' => ['correct' => ['type' => 'string'], 'incorrect' => ['type' => 'string']],
                    'required' => ['correct', 'incorrect'],
                ],
            ],
            'required' => ['kind', 'prompt', 'choices', 'feedback'],
        ];

        $interaction = ['anyOf' => [
            $choiceQuestion('single_choice'),
            $choiceQuestion('multi_choice'),
            $items('accordion', $item),
            $items('click_reveal', [
                'type' => 'object',
                'properties' => ['title' => ['type' => 'string'], 'body' => ['type' => 'string'], 'image' => $image],
                'required' => ['title', 'body'],
            ]),
            $items('tabs', $item),
            $items('process', $item),
            [
                'type' => 'object',
                'properties' => [
                    'kind' => ['enum' => ['flashcard']],
                    'cards' => [
                        'type' => 'array',
                        'minItems' => 2,
                        'items' => [
                            'type' => 'object',
                            'properties' => ['front' => ['type' => 'string'], 'back' => ['type' => 'string']],
                            'required' => ['front', 'back'],
                        ],
                    ],
                ],
                'required' => ['kind', 'cards'],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'kind' => ['enum' => ['video']],
                    'url' => ['type' => 'string', 'description' => 'Always an empty string.'],
                    'caption' => ['type' => 'string'],
                ],
                'required' => ['kind', 'url'],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'kind' => ['enum' => ['scenario']],
                    'setup' => ['type' => 'string'],
                    'choices' => [
                        'type' => 'array',
                        'minItems' => 2,
                        'maxItems' => 4,
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'consequence' => ['type' => 'string'],
                                'tone' => ['enum' => ['success', 'warning', 'failure']],
                            ],
                            'required' => ['id', 'label', 'consequence', 'tone'],
                        ],
                    ],
                ],
                'required' => ['kind', 'setup', 'choices'],
            ],
        ]];

        $screen = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
                'type' => ['enum' => ['text', 'image_text', 'accordion', 'click_reveal', 'question', 'tabs', 'process', 'flashcard', 'video', 'scenario']],
                'title' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'layout' => ['enum' => ['top', 'bottom', 'left', 'right']],
                'image' => ['anyOf' => [$image, ['type' => 'null']]],
                'interactions' => ['type' => 'array', 'items' => $interaction],
                'accessibility' => [
                    'type' => 'object',
                    'properties' => ['alt_text' => ['type' => ['string', 'null']]],
                    'required' => ['alt_text'],
                ],
            ],
            'required' => ['id', 'type', 'title', 'body', 'image', 'interactions', 'accessibility'],
        ];

        return [
            'name' => self::SUBMIT,
            'description' => 'Submit the finished WBT module. Call exactly once with the complete module.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'audience' => ['type' => 'string'],
                            'duration_minutes' => ['type' => 'integer', 'minimum' => 10, 'maximum' => 30],
                            'summary' => ['type' => 'string'],
                            'source_summary' => ['type' => 'string'],
                        ],
                        'required' => ['title', 'audience', 'duration_minutes', 'summary', 'source_summary'],
                    ],
                    'sources' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1, 'maxItems' => 5],
                    'learning_objectives' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 1],
                    'screens' => ['type' => 'array', 'items' => $screen, 'minItems' => 10],
                ],
                'required' => ['meta', 'sources', 'learning_objectives', 'screens'],
            ],
        ];
    }

    private static function declineContent(): array
    {
        return [
            'name' => self::DECLINE,
            'description' => 'Decline to build a module because the source content violates the content policy.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'description' => 'One sentence explaining why.'],
                ],
                'required' => ['reason'],
            ],
        ];
    }
}
