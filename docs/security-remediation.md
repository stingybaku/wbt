# Security remediation and rollout

## Implemented

1. Removed duplicate user notification methods. Fresh PostgreSQL databases enable pgvector before creating vector columns. CI runs syntax checks, database tests, frontend tests, and both frontend builds.
2. A shared project policy protects module reads, event writes, and flags. URL imports and exported media use a bounded HTTP/HTTPS fetcher that rejects nonpublic addresses, rechecks redirects, disables proxies, and pins DNS results for the actual connection.
3. Stripe fulfillment uses an account lock, a unique session ledger, and one transaction for credits, purchase records, and queued mail. Historical nonpending Stripe subscriptions are backfilled into the ledger without adjusting balances. Generation credits are reserved before enqueueing, tied to their original subscription, and consumed or released once. An active-run index prevents two generations for one project. Failed regeneration preserves existing content.
4. Viewer progress uses the stored screen count. Viewers must visit screens in sequence, submit each question, and explicitly finish. Correctness is calculated from stored choices; reports also regrade historical answers. Completion does not require a passing score, matching the existing player's behavior. Completion and certificate queueing are transactional; repeated certificate jobs reuse the existing certificate.
5. The SCORM stub is replaced by an exporter that packages the React player, course JSON, and supported media. The SCORM 1.2 runtime implements initialization, resume data, score, completion, commit, session time, and finish. **Export remains disabled until target-LMS validation.** There was no existing frontend export button to remove.

## Local verification

Requirements: PHP 8.2+ with curl, pdo_pgsql, intl, zip, mbstring, XML/DOM and GD; Composer; Node 20; PostgreSQL 17 with pgvector.

From the repository root:

```sh
docker compose -f compose.test.yaml up -d --wait
cd backend
composer install
vendor/bin/phpunit
```

The `testing` connection uses only `TEST_DB_*` settings. Defaults are localhost:55432 and database/user/password `wbt_test`. Tests refuse other connections and database names not ending in `_test`; never point this connection at application data. Tests migrate and clear the disposable database, and concurrency tests launch separate PHP processes. Transport tests launch a loopback-only HTTP fixture; no real payment, AI, email, or source-site requests are made.

```sh
cd frontend
npm ci
npm test
npm run build
npm run build:scorm
```

The SCORM build writes ignored assets under `backend/storage/app/scorm-player`. The backend Docker build includes this player. CI provisions its own disposable pgvector database.

## Deployment order

1. Back up the application database. Review duplicate payment references and existing certificates separately; this migration does not revoke certificates or subtract historical credits.
2. Stop accepting generation requests and let old generation jobs finish before replacing the worker. Old queued jobs do not have reservation IDs and must not run under the new accounting code. Record any jobs that cannot be drained for manual recovery.
3. Deploy frontend and backend together: the viewer now sends an explicit completion request. Run `php artisan migrate --force` before starting the new application workers. The deployment database role needs permission to enable pgvector on a fresh installation, or an administrator must preinstall it.
4. Restart the database queue worker. Critical job inserts deliberately use the application's database connection so fulfillment/progress writes and queue writes commit together. `DB_QUEUE_CONNECTION` no longer routes these inserts to a separate database. The Railway worker explicitly consumes `database`; other deployments should run `php artisan queue:work database --tries=3 --timeout=720` under a process supervisor. Keep reservation retry visibility at least 780 seconds, longer than the generation timeout. Generation itself has one queue attempt and releases credits on terminal failure.
5. Verify login, generation success/failure, a Stripe test checkout and replay, and viewer completion in staging. Monitor failed jobs and `generation_runs` for stuck work. During rollback, drain new jobs first; do not drop reservation tables or roll back to the vulnerable code while jobs are pending.

## SCORM acceptance still required

`SCORM_EXPORT_ENABLED` defaults to false. Enable it only in an isolated validation environment initially. Export is `POST /api/projects/{id}/scorm`, authenticated as the project owner; it exports the saved module and ignores client-supplied course content.

Import an exported ZIP into the intended LMS and verify:

- The launch page, styles, images, videos, and each interaction type work without access to the application API.
- Closing and reopening resumes position and submitted answers.
- Completing all screens and questions records completion and a score.
- Failed LMS persistence is visible to the learner; closing the course terminates the session.
- The LMS accepts `imsmanifest.xml` and the package's relative asset paths.

Only then enable export in production. Local runtime tests emulate the SCORM API; they are not LMS certification. Media must be downloadable public HTTP/HTTPS resources on standard ports in supported image/video formats, with a 20 MB per-file and 100 MB total limit. Unavailable media fails export instead of producing an incomplete archive. SCORM 1.2 resume data is limited to 4096 characters; oversized state reports an error rather than silently truncating.

Completion records demonstrate the validated interaction sequence, not proof that someone read the material. Existing potentially forged records remain available for a separate review. Runtime reference: [ADL's SCORM developer guide](https://github.com/adlnet/Starting-from-SCORM-A-Developers-Guide).
