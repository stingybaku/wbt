import { z } from "zod";

const choiceSchema = z.object({
  id: z.string(),
  text: z.string(),
  is_correct: z.boolean()
});

const interactionSchema = z.discriminatedUnion("kind", [
  z.object({
    kind: z.literal("single_choice"),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string()
    })
  }),
  z.object({
    kind: z.literal("multi_choice"),
    prompt: z.string(),
    choices: z.array(choiceSchema).min(2),
    feedback: z.object({
      correct: z.string(),
      incorrect: z.string()
    })
  }),
  z.object({
    kind: z.literal("accordion"),
    items: z.array(z.object({ title: z.string(), body: z.string() })).min(2)
  }),
  z.object({
    kind: z.literal("click_reveal"),
    items: z.array(z.object({ title: z.string(), body: z.string() })).min(2)
  })
]);

const screenSchema = z
  .object({
    id: z.string(),
    type: z.enum([
      "text",
      "image_text",
      "accordion",
      "click_reveal",
      "question"
    ]),
    title: z.string(),
    body: z.string(),
    image: z
      .object({
        url: z.string().url(),
        alt_text: z.string()
      })
      .nullable(),
    interactions: z.array(interactionSchema),
    accessibility: z.object({ alt_text: z.string().nullable() })
  })
  .superRefine((screen, ctx) => {
    // question screens need at least one interaction
    if (screen.type === "question" && screen.interactions.length === 0) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "Question screens must include at least one interaction",
        path: ["interactions"]
      });
    }
    // image_text screens should have an image
    if (screen.type === "image_text" && !screen.image) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "image_text screens require an image",
        path: ["image"]
      });
    }
  });

export const moduleSchema = z
  .object({
    schema_version: z.string(),
    meta: z.object({
      title: z.string(),
      audience: z.string(),
      duration_minutes: z.number().min(10).max(30),
      source_summary: z.string(),
      created_at: z.string()
    }),
    learning_objectives: z.array(z.string()).min(1),
    screens: z.array(screenSchema).min(10),
    assessment: z
      .object({
        questions: z.array(z.any()),
        passing_score: z.number().min(0).max(1)
      })
      .optional(),
    attribution: z.object({
      unsplash: z.array(
        z.object({
          image_id: z.string(),
          photographer: z.string(),
          url: z.string().url(),
          license: z.string()
        })
      )
    }),
    review_flags: z
      .array(
        z.object({
          target_id: z.string(),
          target_type: z.enum(["screen", "interaction"]),
          reason: z.string(),
          notes: z.string().optional(),
          status: z.enum(["open", "resolved"])
        })
      )
      .default([])
  })
  .superRefine((mod, ctx) => {
    // Ensure screen ids are unique
    const ids = new Set<string>();
    mod.screens.forEach((scr, idx) => {
      if (ids.has(scr.id)) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          message: `Duplicate screen id '${scr.id}'`,
          path: ["screens", idx, "id"]
        });
      } else {
        ids.add(scr.id);
      }
    });
  });


export type ModuleSchema = z.infer<typeof moduleSchema>;

/**
 * Convenience function for validating arbitrary data against the
 * module schema. Throws on failure, returns typed value on success.
 */
export function validateModule(data: unknown): ModuleSchema {
  return moduleSchema.parse(data);
}
