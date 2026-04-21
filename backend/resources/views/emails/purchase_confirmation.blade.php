<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f9fafb; margin: 0; padding: 40px 16px; color: #111; }
  .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
  .header { background: #111; padding: 32px; text-align: center; }
  .header h1 { color: #fff; font-size: 20px; font-weight: 700; margin: 0; letter-spacing: -0.3px; }
  .header p { color: #aaa; font-size: 13px; margin: 4px 0 0; }
  .body { padding: 32px; }
  .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
  p { color: #444; line-height: 1.7; margin: 0 0 16px; font-size: 14px; }
  .plan-box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin: 20px 0; }
  .plan-box .plan-name { font-weight: 700; font-size: 15px; color: #111; }
  .plan-box .plan-detail { font-size: 13px; color: #666; margin-top: 4px; }
  .cta { display: block; text-align: center; background: #111; color: #fff; text-decoration: none; font-weight: 600; font-size: 14px; padding: 12px 24px; border-radius: 8px; margin: 24px 0 8px; }
  .footer { padding: 20px 32px; border-top: 1px solid #f3f4f6; text-align: center; font-size: 11px; color: #aaa; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>WBT Forge</h1>
    <p>Training Module Builder</p>
  </div>
  <div class="body">
    <div class="greeting">Thank you, {{ $userName }}!</div>
    <p>Your purchase was successful. Here's what you got:</p>

    <div class="plan-box">
      <div class="plan-name">{{ $planName }}</div>
      <div class="plan-detail">{{ $purchaseDate }} &middot; {{ $amountFormatted }}</div>
    </div>

    <p>You can download your invoice any time from the Billing &amp; Plans page in your account.</p>

    <a class="cta" href="{{ $billingUrl }}">View Billing &amp; Plans</a>

    <p style="font-size:12px;color:#999;text-align:center;">If you have any questions, reply to this email or contact us at support@wbtforge.com</p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} WBT Forge. All rights reserved.
  </div>
</div>
</body>
</html>
