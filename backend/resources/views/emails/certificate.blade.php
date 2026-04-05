<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Certificate of Completion</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: "Segoe UI", sans-serif; color: #1e293b; }
    .wrapper { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: #080c14; padding: 32px 36px; }
    .header-brand { color: #2dd4bf; font-size: 1.1rem; font-weight: 700; letter-spacing: -0.01em; }
    .body { padding: 32px 36px; }
    .body h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 8px; color: #0f172a; }
    .body p { margin: 0 0 16px; line-height: 1.6; color: #475569; font-size: 0.95rem; }
    .footer { background: #f8fafc; padding: 20px 36px; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-brand">WBT Forge</div>
    </div>
    <div class="body">
      <h1>You've completed the course!</h1>
      <p>
        Congratulations, <strong>{{ $progress->viewer_name ?? $progress->invite->email }}</strong>!
        You have successfully completed <strong>{{ $progress->invite->project->title }}</strong>.
      </p>
      <p>Your certificate of completion is attached to this email.</p>
      <p>
        Completion date: <strong>{{ $progress->completed_at->format('F j, Y') }}</strong>
      </p>
    </div>
    <div class="footer">
      You received this email because you completed a course on WBT Forge.
      If you weren't expecting this, you can safely ignore it.
    </div>
  </div>
</body>
</html>
