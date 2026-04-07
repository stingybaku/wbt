<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Learner completed your course</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: "Segoe UI", sans-serif; color: #1e293b; }
    .wrapper { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: #080c14; padding: 32px 36px; }
    .header-brand { color: #2dd4bf; font-size: 1.1rem; font-weight: 700; letter-spacing: -0.01em; }
    .body { padding: 32px 36px; }
    .body h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 8px; color: #0f172a; }
    .body p { margin: 0 0 16px; line-height: 1.6; color: #475569; font-size: 0.95rem; }
    .cta { display: inline-block; background: #2dd4bf; color: #080c14; font-weight: 700; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 0.95rem; }
    .fallback { margin-top: 16px; font-size: 0.82rem; color: #94a3b8; }
    .fallback a { color: #2dd4bf; }
    .footer { background: #f8fafc; padding: 20px 36px; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-brand">WBT Forge</div>
    </div>
    <div class="body">
      <h1>A learner finished your course!</h1>
      <p>
        <strong>{{ $viewerName }}</strong> just completed
        <strong>{{ $project->title }}</strong>.
        Head over to the report to see their progress and question results.
      </p>
      <a href="{{ $url }}" class="cta">View report</a>
      <p class="fallback">
        Or copy this link: <a href="{{ $url }}">{{ $url }}</a>
      </p>
    </div>
    <div class="footer">
      You received this email because you are the owner of this course on WBT Forge.
    </div>
  </div>
</body>
</html>
