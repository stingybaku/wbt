<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 13px; color: #111; background: #fff; }
  .page { padding: 48px; }

  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
  .brand { font-size: 22px; font-weight: 700; color: #111; }
  .brand-sub { font-size: 12px; color: #666; margin-top: 2px; }
  .invoice-meta { text-align: right; }
  .invoice-meta h1 { font-size: 28px; font-weight: 700; color: #111; letter-spacing: -0.5px; }
  .invoice-meta .invoice-number { font-size: 12px; color: #666; margin-top: 4px; }
  .invoice-meta .invoice-date { font-size: 12px; color: #666; }

  .divider { border: none; border-top: 1px solid #e5e7eb; margin: 24px 0; }

  .addresses { display: flex; justify-content: space-between; margin-bottom: 40px; }
  .address-block { }
  .address-block .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: #888; margin-bottom: 6px; }
  .address-block .name { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
  .address-block .detail { color: #555; font-size: 12px; line-height: 1.6; }

  table { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
  thead th { background: #f9fafb; padding: 10px 12px; text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #888; border-bottom: 1px solid #e5e7eb; }
  thead th:last-child { text-align: right; }
  tbody td { padding: 14px 12px; border-bottom: 1px solid #f3f4f6; font-size: 13px; vertical-align: top; }
  tbody td:last-child { text-align: right; font-weight: 500; }
  .item-description { font-weight: 500; color: #111; }
  .item-sub { font-size: 11px; color: #888; margin-top: 2px; }

  .totals { margin-left: auto; width: 240px; }
  .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #555; }
  .totals-row.total { border-top: 2px solid #111; padding-top: 10px; margin-top: 4px; font-size: 15px; font-weight: 700; color: #111; }

  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
  .status-completed { background: #d1fae5; color: #065f46; }

  .footer { margin-top: 48px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 11px; color: #999; text-align: center; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div>
      <div class="brand">WBT Forge</div>
      <div class="brand-sub">Training Module Builder</div>
    </div>
    <div class="invoice-meta">
      <h1>Invoice</h1>
      <div class="invoice-number">#{{ $invoiceNumber }}</div>
      <div class="invoice-date">{{ $transaction->created_at->format('F j, Y') }}</div>
    </div>
  </div>

  <hr class="divider">

  <div class="addresses">
    <div class="address-block">
      <div class="label">Billed to</div>
      <div class="name">{{ $transaction->user->name }}</div>
      <div class="detail">{{ $transaction->user->email }}</div>
    </div>
    <div class="address-block" style="text-align:right;">
      <div class="label">Status</div>
      <span class="status-badge status-{{ $transaction->status }}">{{ ucfirst($transaction->status) }}</span>
      @if($transaction->paypal_reference)
      <div class="detail" style="margin-top:6px;">PayPal ref: {{ $transaction->paypal_reference }}</div>
      @endif
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Description</th>
        <th>Type</th>
        <th>Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <div class="item-description">{{ $transaction->plan_name }}</div>
          @if($transaction->description)
          <div class="item-sub">{{ $transaction->description }}</div>
          @endif
        </td>
        <td>{{ $transaction->plan_type === 'one_time' ? 'One-time purchase' : 'Subscription' }}</td>
        <td>{{ $transaction->amountFormatted() }}</td>
      </tr>
    </tbody>
  </table>

  <div class="totals">
    <div class="totals-row total">
      <span>Total</span>
      <span>{{ $transaction->amountFormatted() }}</span>
    </div>
  </div>

  <div class="footer">
    Thank you for your purchase. For questions, contact us at support@wbtforge.com
  </div>

</div>
</body>
</html>
