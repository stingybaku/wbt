<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Certificate of Completion</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: Arial, sans-serif;
      background: #ffffff;
      color: #1e293b;
      width: 297mm;
      height: 210mm;
      position: relative;
    }

    .page {
      width: 100%;
      height: 100%;
      position: relative;
      display: flex;
      flex-direction: column;
    }

    /* Decorative border */
    .border-outer {
      position: absolute;
      top: 8px;
      left: 8px;
      right: 8px;
      bottom: 8px;
      border: 2px solid #2dd4bf;
    }

    .border-inner {
      position: absolute;
      top: 14px;
      left: 14px;
      right: 14px;
      bottom: 14px;
      border: 1px solid #cbd5e1;
    }

    /* Header bar */
    .header {
      background: #080c14;
      padding: 18px 36px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .brand {
      color: #2dd4bf;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }

    .header-rule {
      flex: 1;
      height: 1px;
      background: #2dd4bf;
      opacity: 0.3;
      margin: 0 24px;
    }

    .header-label {
      color: #94a3b8;
      font-size: 0.7rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }

    /* Body */
    .body {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 32px 60px;
      text-align: center;
    }

    .certificate-heading {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 2rem;
      font-weight: 700;
      color: #0f172a;
      letter-spacing: -0.01em;
      margin-bottom: 6px;
    }

    .certifies-text {
      font-size: 0.8rem;
      color: #64748b;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 18px;
    }

    .viewer-name {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 2.2rem;
      color: #0f172a;
      font-style: italic;
      border-bottom: 2px solid #2dd4bf;
      padding-bottom: 8px;
      margin-bottom: 18px;
      min-width: 320px;
    }

    .completed-text {
      font-size: 0.85rem;
      color: #475569;
      margin-bottom: 10px;
    }

    .course-title {
      font-family: Georgia, "Times New Roman", serif;
      font-size: 1.2rem;
      font-weight: 700;
      color: #0f172a;
      margin-bottom: 14px;
    }

    .date-text {
      font-size: 0.8rem;
      color: #64748b;
    }

    .date-value {
      font-weight: 700;
      color: #334155;
    }

    /* Footer */
    .footer {
      padding: 12px 36px 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-top: 1px solid #e2e8f0;
    }

    .verification {
      font-size: 0.72rem;
      color: #94a3b8;
      text-align: center;
    }

    .verification-label {
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-size: 0.65rem;
      margin-bottom: 2px;
    }

    .verification-code {
      font-family: "Courier New", Courier, monospace;
      font-size: 0.8rem;
      color: #475569;
      letter-spacing: 0.15em;
    }

    /* Accent line */
    .accent-line {
      width: 60px;
      height: 3px;
      background: #2dd4bf;
      margin: 12px auto 16px;
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="border-outer"></div>
    <div class="border-inner"></div>

    <div class="header">
      <div class="brand">WBT Forge</div>
      <div class="header-rule"></div>
      <div class="header-label">Issued upon successful completion</div>
    </div>

    <div class="body">
      <div class="certificate-heading">Certificate of Completion</div>
      <div class="accent-line"></div>
      <div class="certifies-text">This certifies that</div>
      <div class="viewer-name">{{ $viewer_name }}</div>
      <div class="completed-text">has successfully completed</div>
      <div class="course-title">{{ $course_title }}</div>
      <div class="date-text">
        on <span class="date-value">{{ $completed_at }}</span>
      </div>
    </div>

    <div class="footer">
      <div class="verification">
        <div class="verification-label">Verification Code</div>
        <div class="verification-code">{{ $verification_code }}</div>
      </div>
    </div>
  </div>
</body>
</html>
