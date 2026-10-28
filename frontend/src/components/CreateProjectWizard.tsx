import { FormEvent, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { createProject, uploadSource } from '../lib/api';

import { useAuth } from '../context/AuthContext';

type Step = 1 | 2 | 3 | 4;

type WizardState = {
  // Step 1
  title: string;
  rightsAttested: boolean;
  // Step 2
  sourceType: 'file' | 'url';
  file: File | null;
  url: string;
  // Step 3
  durationMinutes: number;
  learningObjectives: string;
  assessmentCount: number;
};

const DURATION_OPTIONS = [10, 20, 30];

export function CreateProjectWizard() {
  const { token } = useAuth();
  const navigate = useNavigate();

  const [step, setStep] = useState<Step>(1);
  const [state, setState] = useState<WizardState>({
    title: '',
    rightsAttested: false,
    sourceType: 'file',
    file: null,
    url: '',
    durationMinutes: 20,
    learningObjectives: '',
    assessmentCount: 5,
  });
  const [submitting, setSubmitting] = useState(false);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  function update<K extends keyof WizardState>(key: K, value: WizardState[K]) {
    setState((prev) => ({ ...prev, [key]: value }));
  }

  function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0] ?? null;
    update('file', file);
  }

  function handleDrop(e: React.DragEvent<HTMLDivElement>) {
    e.preventDefault();
    const file = e.dataTransfer.files[0] ?? null;
    if (file) update('file', file);
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!token) return;

    setSubmitting(true);
    setError(null);

    try {
      // Create the project
      const { project } = await createProject(token, {
        title: state.title,
        rights_attestation: state.rightsAttested,
      });

      // Upload the source — returns immediately with status: 'extracting'
      const source =
        state.sourceType === 'file' && state.file
          ? { type: 'file' as const, file: state.file }
          : { type: 'url' as const, url: state.url };

      const genParams = {
        duration_minutes:    state.durationMinutes,
        learning_objectives: state.learningObjectives || undefined,
        assessment_count:    state.assessmentCount,
      };

      await uploadSource(token, project.id, source, genParams, (pct) => setUploadProgress(pct));

      navigate(`/projects/${project.id}`);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Something went wrong');
      setUploadProgress(null);
    } finally {
      setSubmitting(false);
    }
  }

  const canProceedStep1 = state.title.trim().length > 0 && state.rightsAttested;
  const canProceedStep2 =
    state.sourceType === 'file' ? state.file !== null : state.url.trim().length > 0;

  return (
    <main className="app-shell auth-shell">
      <form className="card wizard-card" onSubmit={handleSubmit}>
        <WizardProgress step={step} />

        {error && <div className="error-banner">{error}</div>}

        {step === 1 && (
          <fieldset>
            <legend><h2>New Project</h2></legend>

            <label>
              Project title
              <input
                type="text"
                value={state.title}
                onChange={(e) => update('title', e.target.value)}
                placeholder="e.g. Safety Induction 2026"
                required
                autoFocus
              />
            </label>

            <label className="checkbox-label">
              <input
                type="checkbox"
                checked={state.rightsAttested}
                onChange={(e) => update('rightsAttested', e.target.checked)}
                required
              />
              I confirm I have the right to use this content for training purposes.
            </label>

            <div className="wizard-actions">
              <button
                type="button"
                onClick={() => setStep(2)}
                disabled={!canProceedStep1}
              >
                Next
              </button>
            </div>
          </fieldset>
        )}

        {step === 2 && (
          <fieldset>
            <legend><h2>Content Source</h2></legend>

            <div className="toggle-group">
              <button
                type="button"
                className={state.sourceType === 'file' ? 'active' : 'ghost'}
                onClick={() => update('sourceType', 'file')}
              >
                Upload file
              </button>
              <button
                type="button"
                className={state.sourceType === 'url' ? 'active' : 'ghost'}
                onClick={() => update('sourceType', 'url')}
              >
                Paste URL
              </button>
            </div>

            {state.sourceType === 'file' ? (
              <div
                className="drop-zone"
                onDrop={handleDrop}
                onDragOver={(e) => e.preventDefault()}
                onClick={() => fileInputRef.current?.click()}
              >
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".pdf,.docx,.pptx"
                  onChange={handleFileChange}
                  style={{ display: 'none' }}
                />
                {state.file ? (
                  <p><strong>{state.file.name}</strong> ({(state.file.size / 1024 / 1024).toFixed(1)} MB)</p>
                ) : (
                  <p className="muted">Drop a PDF, DOCX, or PPTX here, or click to browse<br /><small>Max 10 MB</small></p>
                )}
              </div>
            ) : (
              <label>
                URL
                <input
                  type="url"
                  value={state.url}
                  onChange={(e) => update('url', e.target.value)}
                  placeholder="https://example.com/content"
                  required={state.sourceType === 'url'}
                />
              </label>
            )}

            <div className="wizard-actions">
              <button type="button" className="ghost" onClick={() => setStep(1)}>Back</button>
              <button type="button" onClick={() => setStep(3)} disabled={!canProceedStep2}>Next</button>
            </div>
          </fieldset>
        )}

        {step === 3 && (
          <fieldset>
            <legend><h2>Generation Controls</h2></legend>

            <label>
              Target duration
              <div className="toggle-group">
                {DURATION_OPTIONS.map((d) => (
                  <button
                    key={d}
                    type="button"
                    className={state.durationMinutes === d ? 'active' : 'ghost'}
                    onClick={() => update('durationMinutes', d)}
                  >
                    {d} min
                  </button>
                ))}
              </div>
            </label>

            <label>
              Learning objectives <span className="muted">(optional — AI will generate if left blank)</span>
              <textarea
                value={state.learningObjectives}
                onChange={(e) => update('learningObjectives', e.target.value)}
                placeholder="e.g. Understand PPE requirements&#10;Identify hazard reporting steps"
                rows={4}
              />
            </label>

            <label>
              Assessment questions: <strong>{state.assessmentCount}</strong>
              <input
                type="range"
                min={0}
                max={10}
                value={state.assessmentCount}
                onChange={(e) => update('assessmentCount', Number(e.target.value))}
              />
              <span className="range-labels"><span>0</span><span>10</span></span>
            </label>

            <div className="wizard-actions">
              <button type="button" className="ghost" onClick={() => setStep(2)}>Back</button>
              <button type="button" onClick={() => setStep(4)}>Review</button>
            </div>
          </fieldset>
        )}

        {step === 4 && (
          <fieldset>
            <legend><h2>Review & Generate</h2></legend>

            <dl className="summary-list">
              <dt>Title</dt>
              <dd>{state.title}</dd>
              <dt>Source</dt>
              <dd>
                {state.sourceType === 'file' && state.file
                  ? `File: ${state.file.name}`
                  : `URL: ${state.url}`}
              </dd>
              <dt>Duration</dt>
              <dd>{state.durationMinutes} minutes</dd>
              <dt>Learning objectives</dt>
              <dd>{state.learningObjectives.trim() || 'AI-generated'}</dd>
              <dt>Assessment questions</dt>
              <dd>{state.assessmentCount}</dd>
            </dl>

            {uploadProgress !== null && uploadProgress < 100 && (
              <div className="progress-bar">
                <div className="progress-bar-fill" style={{ width: `${uploadProgress}%` }} />
                <span>{uploadProgress}%</span>
              </div>
            )}
            {submitting && uploadProgress === 100 && (
              <p className="muted">Processing content...</p>
            )}

            <div className="wizard-actions">
              <button type="button" className="ghost" onClick={() => setStep(3)} disabled={submitting}>Back</button>
              <button type="submit" disabled={submitting}>
                {submitting ? 'Creating...' : 'Generate Training'}
              </button>
            </div>
          </fieldset>
        )}
      </form>
    </main>
  );
}

function WizardProgress({ step }: { step: Step }) {
  const labels = ['Project', 'Content', 'Controls', 'Generate'];
  return (
    <ol className="wizard-steps">
      {labels.map((label, i) => (
        <li key={label} className={i + 1 === step ? 'active' : i + 1 < step ? 'done' : ''}>
          <span className="step-dot">{i + 1 < step ? '✓' : i + 1}</span>
          <span className="step-label">{label}</span>
        </li>
      ))}
    </ol>
  );
}
