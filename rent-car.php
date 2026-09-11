<?php
require_once __DIR__ . '/includes/rentals.php';
require_once __DIR__ . '/includes/layout.php';

$id = (int) ($_GET['id'] ?? 0);
$car = findRentalCar($id);
if (!$car) { flash('error', 'This car is not available for rent.'); redirect(base('rent.php')); }
q('UPDATE listings SET views = views + 1 WHERE id = ?', [$id]);

$gallery = listingGallery($car);
$features = dbReady() ? fetchAll('SELECT category, feature FROM vehicle_features WHERE vehicle_id = ? ORDER BY category, feature', [(int) $car['vehicle_id']]) : [];
$featByCat = [];
foreach ($features as $f) { $featByCat[$f['category']][] = $f['feature']; }
$rate = sellerRating((int) $car['seller_id']);
$history = dbReady() ? fetchOne('SELECT * FROM vehicle_history WHERE vehicle_id = ?', [(int) $car['vehicle_id']]) : null;
$defPickup = date('Y-m-d\TH:i', strtotime('+1 day 10:00'));
$defReturn = date('Y-m-d\TH:i', strtotime('+3 days 10:00'));

renderHeader(vehicleTitle($car) . ' on rent', 'rent');
?>
<div class="wrap section">
  <p class="muted" style="font-size:13px"><a href="<?= e(base('index.php')) ?>">Home</a> / <a href="<?= e(base('rent.php')) ?>">Rentals</a> / <?= e(vehicleTitle($car)) ?></p>
  <div class="split-3">
    <div>
      <div class="card" style="overflow:hidden" id="viewer">
        <div class="spin-view" id="spinView">
          <img id="spinImg" src="<?= e($gallery[0]['src']) ?>" alt="<?= e(vehicleTitle($car)) ?> - 360 view" draggable="false">
          <span class="spin-tag">360&deg; view</span>
          <span class="spin-hint" id="spinHint">&#8646; Drag to rotate</span>
          <button type="button" class="spin-btn prev" id="spinPrev" aria-label="Previous angle">&#8249;</button>
          <button type="button" class="spin-btn next" id="spinNext" aria-label="Next angle">&#8250;</button>
          <div class="spin-bar"><i id="spinDot"></i></div>
        </div>
        <?php if (count($gallery) > 1): ?>
        <div class="thumbs card-pad" style="padding-top:12px">
          <?php foreach ($gallery as $i => $g): ?>
            <button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-frame="<?= $i ?>" title="<?= e($g['label']) ?>"><img src="<?= e($g['src']) ?>" alt="<?= e($g['label']) ?>"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <script>
        (function () {
          var frames = <?= json_encode(array_column($gallery, 'src')) ?>;
          var img = document.getElementById('spinImg'), view = document.getElementById('spinView'),
              dot = document.getElementById('spinDot'), hint = document.getElementById('spinHint'),
              thumbs = document.querySelectorAll('[data-frame]');
          var i = 0, n = frames.length, timer = null, dragging = false, startX = 0, startI = 0;
          function show(k) {
            i = ((k % n) + n) % n;
            img.style.opacity = 0;
            setTimeout(function () { img.src = frames[i]; img.onload = function () { img.style.opacity = 1; }; }, 90);
            dot.style.left = (n === 1 ? 0 : (i / (n - 1)) * 100) + '%';
            thumbs.forEach(function (t) { t.classList.toggle('on', Number(t.getAttribute('data-frame')) === i); });
          }
          function stop() { if (timer) { clearInterval(timer); timer = null; } if (hint) { hint.style.display = 'none'; } }
          if (n > 1) {
            timer = setInterval(function () { show(i + 1); }, 2200);
            document.getElementById('spinPrev').addEventListener('click', function () { stop(); show(i - 1); });
            document.getElementById('spinNext').addEventListener('click', function () { stop(); show(i + 1); });
            thumbs.forEach(function (t) { t.addEventListener('click', function () { stop(); show(Number(t.getAttribute('data-frame'))); }); });
            view.addEventListener('pointerdown', function (ev) { dragging = true; startX = ev.clientX; startI = i; stop(); view.setPointerCapture(ev.pointerId); });
            view.addEventListener('pointermove', function (ev) { if (!dragging) { return; } var d = Math.round((ev.clientX - startX) / 60); if (d !== 0) { show(startI + d); } });
            ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (e) { view.addEventListener(e, function () { dragging = false; }); });
          } else if (hint) { hint.textContent = 'Inspection photos'; }
        })();
        </script>
        <div class="card-pad" style="padding-top:6px">
          <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <h1 style="font-size:1.55rem;margin:0;flex:1;min-width:220px"><?= e(vehicleTitle($car)) ?></h1>
            <?php if ($rate['count'] > 0): ?><span class="badge ok">&#9733; <?= $rate['avg'] ?> host (<?= $rate['count'] ?>)</span><?php endif; ?>
            <span class="badge info"><?= (int) $car['inspection_score'] ?>/100 inspected</span>
          </div>
          <p class="muted num" style="font-size:13.5px;margin:6px 0 0"><?= e((string) ($car['city'] ?? '')) ?> &middot; <?= e((string) ($car['fuel_type'] ?? '')) ?> &middot; <?= e((string) ($car['transmission'] ?? '')) ?> &middot; <?= (int) ($car['seats'] ?? 5) ?> seats</p>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
            <a class="btn btn-ghost btn-sm" href="#specs">Specifications</a>
            <a class="btn btn-ghost btn-sm" href="#features">Features</a>
            <a class="btn btn-ghost btn-sm" href="#policies">Rental policies</a>
          </div>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="specs">
        <h2 style="font-size:1.2rem">Specifications</h2>
        <div class="spec-grid">
          <?php foreach (['Year' => $car['year'], 'Fuel' => $car['fuel_type'], 'Transmission' => $car['transmission'], 'Seats' => $car['seats'], 'Body type' => $car['body_type'], 'KM driven' => number_format((int) $car['km_driven']) . ' km', 'Owners' => $car['owners'], 'Colour' => $car['color'], 'Engine' => $car['engine_cc'] ? $car['engine_cc'] . ' cc' : '-', 'Power' => $car['power_bhp'] ? $car['power_bhp'] . ' bhp' : '-', 'Mileage' => $car['mileage_kmpl'] ? $car['mileage_kmpl'] . ' km/l' : '-', 'City' => $car['city']] as $k => $v): ?>
            <div><small><?= e($k) ?></small><b><?= e((string) $v) ?></b></div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="features">
        <h2 style="font-size:1.2rem">Features (<?= count($features) ?>)</h2>
        <?php if (!$features): ?><p class="muted">The feature list is being verified for this car.</p><?php endif; ?>
        <?php foreach ($featByCat as $cat => $list): ?>
          <h3 style="font-size:.95rem;margin:12px 0 6px"><?= e((string) $cat) ?></h3>
          <div class="meta"><?php foreach ($list as $f): ?><span><?= e((string) $f) ?></span><?php endforeach; ?></div>
        <?php endforeach; ?>
      </div>

      <div class="card card-pad" style="margin-top:18px" id="policies">
        <h2 style="font-size:1.2rem">Rental terms &amp; policies</h2>
        <div class="kv"><span>KM limit</span><span class="num"><?= (int) $car['km_limit_day'] ?> km/day included</span></div>
        <div class="kv"><span>Extra KM</span><span class="num"><?= rupees($car['extra_km_rate']) ?>/km after the limit</span></div>
        <div class="kv"><span>Security deposit</span><span class="num"><?= rupees($car['security_deposit']) ?> (refundable)</span></div>
        <ul class="pol-list"><?php foreach (rentalPolicies() as $pol): ?><li><?= e($pol) ?></li><?php endforeach; ?></ul>
      </div>
    </div>

    <aside>
      <div class="card card-pad sticky" id="bookBox"
        data-ppd="<?= (float) $car['price_per_day'] ?>" data-dep="<?= (float) $car['security_deposit'] ?>"
        data-tax="<?= RENTAL_TAX_PCT ?>" data-off="<?= RENTAL_WEEKLY_OFF_PCT ?>">
        <div class="price num" style="font-size:1.7rem;font-weight:800"><?= rupees($car['price_per_day']) ?><small class="muted" style="font-size:.95rem">/day</small></div>
        <div class="muted num" style="font-size:13px"><?= (int) $car['km_limit_day'] ?> km/day &middot; <?= rupees($car['security_deposit']) ?> refundable deposit</div>
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:10px">
          <div><label class="form-label">Pickup location</label><input class="form-control" id="qLoc" value="<?= e((string) ($_GET['pickup_loc'] ?? ($car['city'] ?? ''))) ?>" placeholder="City, airport, hub..."></div>
          <div><label class="form-label">Pickup</label><input class="form-control" id="qFrom" type="datetime-local" value="<?= e((string) ($_GET['pickup_at'] ?? $defPickup)) ?>"></div>
          <div><label class="form-label">Return</label><input class="form-control" id="qTo" type="datetime-local" value="<?= e((string) ($_GET['return_at'] ?? $defReturn)) ?>"></div>
        </div>
        <div id="qOut" style="margin-top:10px"></div>
        <a class="btn btn-primary btn-block btn-lg btn-shine" style="margin-top:12px" id="qBook" href="#">Book now</a>
        <p class="muted" style="font-size:12.5px;margin-top:8px">Free cancellation before pickup &middot; 10% off on 7+ day trips.</p>
        <script>
        (function () {
          var box = document.getElementById('bookBox');
          var ppd = Number(box.getAttribute('data-ppd')), dep = Number(box.getAttribute('data-dep')),
              taxP = Number(box.getAttribute('data-tax')), offP = Number(box.getAttribute('data-off'));
          var loc = document.getElementById('qLoc'), from = document.getElementById('qFrom'),
              to = document.getElementById('qTo'), out = document.getElementById('qOut'), book = document.getElementById('qBook');
          function inr(n) { return '\u20B9' + Math.round(n).toLocaleString('en-IN'); }
          function refresh() {
            var t1 = new Date(from.value).getTime(), t2 = new Date(to.value).getTime();
            if (!from.value || !to.value || !(t2 > t1)) { out.innerHTML = '<p class="muted" style="font-size:13px">Select valid dates to see the price.</p>'; book.setAttribute('aria-disabled', 'true'); return; }
            var days = Math.max(1, Math.ceil((t2 - t1) / 86400000));
            var base = days * ppd, off = days >= 7 ? base * offP / 100 : 0, tax = (base - off) * taxP / 100;
            out.innerHTML = '<div class="kv"><span>' + days + ' day(s) x ' + inr(ppd) + '</span><span class="num">' + inr(base) + '</span></div>'
              + (off ? '<div class="kv"><span>Weekly discount</span><span class="num">- ' + inr(off) + '</span></div>' : '')
              + '<div class="kv"><span>Taxes &amp; fees</span><span class="num">' + inr(tax) + '</span></div>'
              + '<div class="kv"><span>Refundable deposit</span><span class="num">' + inr(dep) + '</span></div>'
              + '<div class="kv"><span><b>Payable now</b></span><b class="num">' + inr(base - off + tax + dep) + '</b></div>';
            book.href = <?= json_encode(base('rent-checkout.php')) ?> + '?listing=<?= (int) $car['id'] ?>'
              + '&pickup_loc=' + encodeURIComponent(loc.value) + '&pickup_at=' + encodeURIComponent(from.value) + '&return_at=' + encodeURIComponent(to.value);
            book.removeAttribute('aria-disabled');
          }
          [loc, from, to].forEach(function (el) { el.addEventListener('input', refresh); });
          refresh();
        })();
        </script>
        <div class="kv" style="margin-top:12px"><span>Host</span><span><?= e((string) ($car['seller_company'] ?: $car['seller_name'])) ?></span></div>
        <div class="kv"><span>Host rating</span><span class="num"><?= $rate['count'] ? '★ ' . $rate['avg'] . ' (' . $rate['count'] . ')' : 'New host' ?></span></div>
        <div class="kv"><span>Odometer</span><span><?= ($history && (int) ($history['odometer_verified'] ?? 0) === 1) ? statusBadge('verified') : statusBadge('pending') ?></span></div>
      </div>
    </aside>
  </div>
</div>
<?php renderFooter(); ?>
