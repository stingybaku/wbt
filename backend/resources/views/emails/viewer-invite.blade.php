<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Course Invitation</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: "Segoe UI", sans-serif; color: #1e293b; }
    .wrapper { max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: #080c14; padding: 32px 36px; }
    .header-brand { color: #2dd4bf; font-size: 1.1rem; font-weight: 700; letter-spacing: -0.01em; }
    .body { padding: 32px 36px; }
    .body h1 { font-size: 1.25rem; font-weight: 700; margin: 0 0 8px; color: #0f172a; }
    .body p { margin: 0 0 16px; line-height: 1.6; color: #475569; font-size: 0.95rem; }
    .cta { display: block; width: fit-content; background: #2dd4bf; color: #080c14; font-weight: 700; font-size: 0.95rem; text-decoration: none; padding: 14px 28px; border-radius: 8px; margin: 24px 0; }
    .expiry { font-size: 0.82rem; color: #94a3b8; }
    .fallback { margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #94a3b8; word-break: break-all; }
    .footer { background: #f8fafc; padding: 20px 36px; font-size: 0.78rem; color: #94a3b8; border-top: 1px solid #e2e8f0; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <div class="header-brand">WBT Forge</div>
    </div>
    <div class="body">
      <h1>You've been invited to a course</h1>
      <p>
        <strong>{{ $invite->invitedBy->name ?? $invite->invitedBy->email }}</strong> has invited you to view
        <strong>{{ $invite->project->title }}</strong>.
      </p>
      <p>Click the button below to access the course. No account required.</p>
      <a href="{{ $viewerUrl }}" class="cta">Open Course →</a>
      <p class="expiry">This link expires on {{ $invite->expires_at->format('F j, Y') }}.</p>
      <div class="fallback">
        <p>If the button doesn't work, copy and paste this link into your browser:</p>
        <p>{{ $viewerUrl }}</p>
      </div>
    </div>
    <div class="footer">
      You received this email because someone invited you to view a course on WBT Forge.
      If you weren't expecting this, you can safely ignore it.
    </div>
  </div>
</body>
</html>
