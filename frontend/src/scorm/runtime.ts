// SCORM 1.2 runtime: API discovery, bounded suspend data, scoring and lifecycle.
export interface ScormApi {
  LMSInitialize(value: string): string;
  LMSGetValue(key: string): string;
  LMSSetValue(key: string, value: string): string;
  LMSCommit(value: string): string;
  LMSFinish(value: string): string;
}
export interface ScormWindow { API?: ScormApi; parent?: ScormWindow; opener?: ScormWindow }
type Choice = { id: string; is_correct?: boolean };
type Screen = { id: string; type: string; interactions: { kind: string; choices?: Choice[] }[] };
type Answer = { selected: string[]; submitted: boolean; isCorrect: boolean };

export function findApi(start: ScormWindow): ScormApi | null {
  const seen = new Set<ScormWindow>();
  const pending = [start];
  while (pending.length && seen.size < 100) {
    const current = pending.shift()!;
    if (seen.has(current)) continue;
    seen.add(current);
    try { if (current.API) return current.API; } catch { /* cross-origin window */ }
    try { if (current.parent) pending.push(current.parent); } catch { /* cross-origin parent */ }
    try { if (current.opener) pending.push(current.opener); } catch { /* cross-origin opener */ }
  }
  return null;
}

export class ScormSession {
  index = 0;
  visited = [0];
  answers: Record<string, Answer> = {};
  private finished = false;
  private completed = false;
  private started = Date.now();

  constructor(private api: ScormApi, private screens: Screen[]) {
    this.check(api.LMSInitialize(''));
    this.completed = ['completed', 'passed'].includes(api.LMSGetValue('cmi.core.lesson_status'));
    if (!this.completed) this.set('cmi.core.lesson_status', 'incomplete');
    const saved = api.LMSGetValue('cmi.suspend_data');
    try {
      const parsed = JSON.parse(saved);
      if (parsed.v === 1 && Number.isInteger(parsed.i) && parsed.i >= 0 && parsed.i < screens.length) {
        this.index = parsed.i;
        this.visited = Array.isArray(parsed.s)
          ? parsed.s.filter((i: number) => Number.isInteger(i) && i >= 0 && i < screens.length) : [0];
        for (const [index, selected] of parsed.a ?? []) {
          const screen = screens[index];
          if (!screen || !Array.isArray(selected)) continue;
          const choices = screen.interactions[0]?.choices ?? [];
          const ids = selected.map((i: number) => choices[i]?.id).filter(Boolean);
          if (ids.length === selected.length) this.answer(screen.id, ids, false);
        }
      }
    } catch { /* Start fresh if an LMS has no usable saved state. */ }
    this.save();
  }

  answer(id: string, selected: string[], persist = true): void {
    const screen = this.screens.find(s => s.id === id);
    if (!screen) throw new Error('Unknown course question.');
    const interaction = screen.interactions[0];
    const choices = interaction?.choices ?? [];
    if (!selected.length || new Set(selected).size !== selected.length
      || selected.some(id => !choices.some(c => c.id === id))
      || (interaction.kind !== 'multi_choice' && selected.length !== 1)) throw new Error('Invalid answer.');
    const correct = choices.filter(c => c.is_correct).map(c => c.id);
    this.answers[id] = { selected, submitted: true,
      isCorrect: screen.type === 'question' && correct.length === selected.length && correct.every(id => selected.includes(id)) };
    if (persist) this.save();
  }

  progress(index: number, visited: number[], complete = false): void {
    if (!Number.isInteger(index) || index < 0 || index >= this.screens.length) throw new Error('Invalid screen.');
    this.index = index;
    this.visited = [...new Set(visited.filter(i => Number.isInteger(i) && i >= 0 && i < this.screens.length))];
    if (complete) {
      const questions = this.screens.filter(s => s.type === 'question');
      if (this.visited.length !== this.screens.length || questions.some(s => !this.answers[s.id]?.submitted)) {
        throw new Error('Visit every screen and answer every question before finishing.');
      }
      this.completed = true;
    }
    this.save();
  }

  save(): void {
    if (this.finished) return;
    const packed = JSON.stringify({ v: 1, i: this.index, s: this.visited,
      a: Object.entries(this.answers).map(([id, answer]) => {
        const index = this.screens.findIndex(s => s.id === id);
        const choices = this.screens[index].interactions[0].choices ?? [];
        return [index, answer.selected.map(id => choices.findIndex(c => c.id === id))];
      }) });
    if (packed.length > 4096) throw new Error('This course exceeds the LMS resume-data limit.');
    this.set('cmi.suspend_data', packed);
    this.set('cmi.core.lesson_location', String(this.index));
    const questions = this.screens.filter(s => s.type === 'question');
    const correct = questions.filter(s => this.answers[s.id]?.isCorrect).length;
    if (questions.length) {
      this.set('cmi.core.score.min', '0');
      this.set('cmi.core.score.max', '100');
      this.set('cmi.core.score.raw', String(Math.round(correct / questions.length * 100)));
    }
    this.set('cmi.core.lesson_status', this.completed ? 'completed' : 'incomplete');
    this.set('cmi.core.exit', this.completed ? '' : 'suspend');
    const seconds = Math.min(35999999, Math.floor((Date.now() - this.started) / 1000));
    this.set('cmi.core.session_time', `${String(Math.floor(seconds / 3600)).padStart(4, '0')}:${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`);
    this.check(this.api.LMSCommit(''));
  }

  finish(): void {
    if (this.finished) return;
    this.save();
    this.check(this.api.LMSFinish(''));
    this.finished = true;
  }

  private set(key: string, value: string): void { this.check(this.api.LMSSetValue(key, value)); }
  private check(result: string): void {
    if (String(result).toLowerCase() !== 'true') throw new Error('The LMS could not save course progress. Please reopen the course from your LMS.');
  }
}
