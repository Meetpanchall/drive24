<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$admin = requireLogin('admin');

$fields = [
    'booking_amount' => ['Booking advance (Rs)', 'number', 'Refundable amount collected at car checkout.'],
    'rental_tax_pct' => ['Rental GST (%)', 'number', 'Tax on (rental - discount).'],
    'rental_weekly_off' => ['Rental weekly discount (%)', 'number', 'Applied on 7+ day trips.'],
    'rental_fuel_per_pct' => ['Fuel shortfall rate (Rs per 1%)', 'number', 'Charged from the deposit at return.'],
    'offer_hold_hours' => ['Offer hold (hours)', 'number', 'How long an accepted deal reserves the car.'],
    'helpline' => ['Helpline number', 'text', 'Shown on support + trip SOS screens.'],
    'helpline_hours' => ['Helpline hours', 'text', 'Shown under the helpline number.'],
    'support_email' => ['Support email', 'text', 'Shown on the support page.'],
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    foreach ($fields as $key => [$label, $type]) {
        $val = trim((string) ($_POST[$key] ?? ''));
        if ($type === 'number' && !is_numeric($val)) { continue; }
        if ($val !== '') { saveSetting($key, $val); }
    }
    logActivity((int) $admin['id'], 'settings.updated', 'site settings saved');
    flash('success', 'Settings saved. They apply to new checkouts and quotes immediately.');
    redirect(base('admin/settings.php'));
}

adminHeader('System settings', 'settings');
?>
<p class="muted">Business knobs for the whole marketplace - no code deploys needed. Existing orders and rentals keep the values they were booked with.</p>
<form method="post" class="card card-pad" style="max-width:640px">
  <?= csrfField() ?>
  <?php foreach ($fields as $key => [$label, $type, $hint]): ?>
    <div style="margin-bottom:12px">
      <label class="form-label"><?= e($label) ?></label>
      <input class="form-control <?= $type === 'number' ? 'num' : '' ?>" <?= $type === 'number' ? 'type="number" step="any" min="0"' : 'type="text"' ?> name="<?= e($key) ?>" value="<?= e((string) setting($key)) ?>">
      <small class="muted"><?= e($hint) ?></small>
    </div>
  <?php endforeach; ?>
  <button class="btn btn-primary" type="submit">Save settings</button>
</form>
<?php adminFooter(); ?>
