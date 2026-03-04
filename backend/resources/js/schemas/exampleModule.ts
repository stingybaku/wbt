import { validateModule, ModuleSchema } from "./moduleSchema";

// Minimal example that satisfies all constraints
// create ten simple text screens programmatically
const screens = Array.from({ length: 10 }, (_, i) => ({
  id: `scr${i + 1}`,
  type: "text" as const,
  title: `Screen ${i + 1}`,
  body: "Sample content",
  image: null,
  interactions: [],
  accessibility: { alt_text: null }
}));

const example: unknown = {
  schema_version: "1.0",
  meta: {
    title: "Intro",
    audience: "Staff",
    duration_minutes: 20,
    source_summary: "Uploaded PPTX",
    created_at: new Date().toISOString()
  },
  learning_objectives: ["Learn policies"],
  screens,
  attribution: { unsplash: [] }
};

try {
  const validated = validateModule(example);
  console.log("Validation successful", validated);
} catch (e) {
  console.error("Validation failed", e.errors ?? e);
}
