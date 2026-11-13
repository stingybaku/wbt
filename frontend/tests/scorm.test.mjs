import { test } from 'node:test';
import assert from 'node:assert/strict';
import { findApi, ScormSession } from '../.scorm-tests/runtime.js';

const screens = [
  { id: 'intro', type: 'text', interactions: [] },
  { id: 'quiz', type: 'question', interactions: [{ kind: 'single_choice', choices: [
    { id: 'yes', is_correct: true }, { id: 'no', is_correct: false },
  ] }] },
];
function lms(values = {}) {
  const calls = [];
  return { values, calls,
    LMSInitialize: () => { calls.push('initialize'); return 'true'; },
    LMSGetValue: key => values[key] ?? '',
    LMSSetValue: (key, value) => { values[key] = value; return 'true'; },
    LMSCommit: () => { calls.push('commit'); return 'true'; },
    LMSFinish: () => { calls.push('finish'); return 'true'; },
  };
}
test('discovers API through parent/opener and terminates cycles', () => {
  const api = lms();
  const parent = { API: api }; parent.parent = parent;
  assert.equal(findApi({ parent: {}, opener: parent }), api);
  const cycle = {}; cycle.parent = cycle;
  assert.equal(findApi(cycle), null);
});
test('initializes, saves, resumes selections and completes with a score', () => {
  const api = lms();
  const first = new ScormSession(api, screens);
  first.progress(1, [0, 1]);
  first.answer('quiz', ['yes']);
  first.finish(); first.finish();
  assert.equal(api.values['cmi.core.exit'], 'suspend');
  assert.equal(api.calls.filter(c => c === 'finish').length, 1);
  const resumed = new ScormSession(api, screens);
  assert.equal(resumed.index, 1);
  assert.deepEqual(resumed.answers.quiz.selected, ['yes']);
  resumed.progress(1, [0, 1], true);
  resumed.finish();
  assert.equal(api.values['cmi.core.lesson_status'], 'completed');
  assert.equal(api.values['cmi.core.score.raw'], '100');
  assert.equal(api.values['cmi.core.exit'], '');
  assert.match(api.values['cmi.core.session_time'], /^\d{4}:\d{2}:\d{2}$/);
  assert.ok(api.values['cmi.suspend_data'].length <= 4096);
});
test('does not complete without all screens and answers; scores wrong answers', () => {
  const api = lms(); const session = new ScormSession(api, screens);
  assert.throws(() => session.progress(1, [0, 1], true));
  session.answer('quiz', ['no']);
  assert.throws(() => session.progress(1, [1], true));
  session.progress(1, [0, 1], true);
  assert.equal(api.values['cmi.core.score.raw'], '0');
});
test('surfaces LMS persistence failures', () => {
  const api = lms(); api.LMSCommit = () => 'false';
  assert.throws(() => new ScormSession(api, screens), /could not save/);
});
