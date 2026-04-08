import { z } from 'zod';

const choiceSchema = z.object({
  id: z.string(),
  text: z.string(),
  is_correct: z.boolean(),
});

const interactionSchema = z.discriminatedUnion('kind', [
  z.object({
    kind: z.literal('single_choice'),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string(),
    }),
  }),
  z.object({
    kind: z.literal('multi_choice'),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string(),
    }),
  }),
  z.object({
    kind: z.literal('accordion'),
    items: z.array(z.object({ title: z.string(), body: z.string() })).min(2),
  }),
  z.object({
    kind: z.literal('click_reveal'),
    items: z.array(z.object({
      title: z.string(),
      body: z.string(),
      image: z.object({ url: z.string().url(), alt_text: z.string() }).nullable().optional(),
    })).min(2),
  }),
  z.object({
    kind: z.literal('tabs'),
    items: z.array(z.object({ title: z.string(), body: z.string() })).min(2),
  }),
  z.object({
    kind: z.literal('process'),
    items: z.array(z.object({ title: z.string(), body: z.string() })).min(2),
  }),
  z.object({
    kind: z.literal('flashcard'),
    cards: z.array(z.object({ front: z.string(), back: z.string() })).min(2),
  }),
  z.object({
    kind: z.literal('video'),
    url: z.string().url(),
    caption: z.string().optional(),
  }),
]);

export const imageTextLayouts = ['top', 'bottom', 'left', 'right'] as const;
export type ImageTextLayout = typeof imageTextLayouts[number];

const screenSchema = z.object({
  id: z.string(),
  type: z.enum(['text', 'image_text', 'accordion', 'click_reveal', 'question', 'tabs', 'process', 'flashcard', 'video']),
  title: z.string(),
  body: z.string(),
  image: z
    .object({
      url: z.string().url(),
      alt_text: z.string(),
    })
    .nullable(),
  layout: z.enum(imageTextLayouts).optional(),
  interactions: z.array(interactionSchema),
  accessibility: z.object({ alt_text: z.string().nullable() }),
});

export const moduleSchema = z
  .object({
    schema_version: z.string(),
    meta: z.object({
      title: z.string(),
      audience: z.string(),
      duration_minutes: z.number().min(10).max(30),
      source_summary: z.string(),
      created_at: z.string(),
      cover_image: z.string().optional(),
    }),
    learning_objectives: z.array(z.string()).min(1),
    screens: z.array(screenSchema).min(10),
    assessment: z
      .object({
        questions: z.array(z.any()),
        passing_score: z.number().min(0).max(1),
      })
      .optional(),
    attribution: z.object({
      unsplash: z.array(
        z.object({
          image_id: z.string(),
          photographer: z.string(),
          url: z.string().url(),
          license: z.string(),
        })
      ),
      pexels: z.array(
        z.object({
          image_id: z.string(),
          photographer: z.string(),
          url: z.string().url(),
          license: z.string(),
        })
      ).optional(),
    }),
    sources: z.array(z.string()).optional(),
    review_flags: z
      .array(
        z.object({
          target_id: z.string(),
          target_type: z.enum(['screen', 'interaction']),
          reason: z.string(),
          notes: z.string().optional(),
          status: z.enum(['open', 'resolved']),
        })
      )
      .default([]),
  })
  .superRefine((data, ctx) => {
    const ids = data.screens.map((s) => s.id);
    const unique = new Set(ids);
    if (unique.size !== ids.length) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: 'Duplicate screen IDs are not permitted',
        path: ['screens'],
      });
    }

    data.screens.forEach((screen, i) => {
      if (screen.type === 'question' && screen.interactions.length === 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'question screens must include at least 1 interaction',
          path: ['screens', i, 'interactions'],
        });
      }
      if (screen.type === 'image_text' && screen.image === null) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: 'image_text screens must include an image object',
          path: ['screens', i, 'image'],
        });
      }
      if ((screen.type === 'tabs' || screen.type === 'process' || screen.type === 'flashcard') && screen.interactions.length === 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: `${screen.type} screens must include at least 1 interaction`,
          path: ['screens', i, 'interactions'],
        });
      }
    });
  });

export type Module = z.infer<typeof moduleSchema>;
export type Screen = z.infer<typeof screenSchema>;
export type Interaction = z.infer<typeof interactionSchema>;
