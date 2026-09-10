<?php
require_once __DIR__ . '/../includes/admin_layout.php';
$u = requireLogin('seller');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verifyCsrf();
    $qid = (int) ($_POST['question_id'] ?? 0);
    $answer = mb_substr(trim((string) ($_POST['answer'] ?? '')), 0, 1000);
    $row = fetchOne('SELECT q.id, q.user_id, l.seller_id, l.vehicle_id FROM questions q JOIN listings l ON l.id = q.listing_id WHERE q.id = ? AND l.seller_id = ?', [$qid, $u['id']]);
    if ($row && $answer !== '') {
        updateRow('questions', ['answer' => $answer, 'answered_by' => $u['id'], 'answered_at' => date('Y-m-d H:i:s')], 'id = ?', [$qid]);
        notify((int) $row['user_id'], 'Seller answered your question', 'See the answer on the car page.', 'car.php?id=' . (int) ($_POST['listing_id'] ?? 0));
        flash('success', 'Answer posted.');
    } else {
        flash('error', 'Please type an answer.');
    }
    redirect(base('seller/questions.php'));
}

$rows = fetchAll("SELECT q.*, u.name AS asker, v.make, v.model, v.year, l.id AS listing_id
    FROM questions q
    JOIN listings l ON l.id = q.listing_id
    JOIN vehicles v ON v.id = l.vehicle_id
    JOIN users u ON u.id = q.user_id
    WHERE l.seller_id = ? ORDER BY (q.answer IS NULL) DESC, q.id DESC", [$u['id']]);
adminHeader('Buyer questions', 'questions', 'seller');
?>
<div class="table-wrap"><table class="data">
  <thead><tr><th>Car</th><th>Asked by</th><th>Question</th><th>Your answer</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="4" class="empty">No questions yet. Questions buyers ask on your listings appear here.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><a href="<?= e(base('car.php?id=' . (int) $r['listing_id'])) ?>"><?= e($r['year'] . ' ' . $r['make'] . ' ' . $r['model']) ?></a></td>
      <td><?= e($r['asker']) ?><div class="muted num" style="font-size:12px"><?= e(date('d M Y', strtotime((string) $r['created_at']))) ?></div></td>
      <td><?= e($r['question']) ?></td>
      <td style="min-width:260px">
        <?php if (!empty($r['answer'])): ?>
          <?= e($r['answer']) ?><div class="muted" style="font-size:12px">Answered <?= e(date('d M Y', strtotime((string) $r['answered_at']))) ?></div>
        <?php else: ?>
          <form method="post" style="display:flex;gap:6px"><?= csrfField() ?>
            <input type="hidden" name="question_id" value="<?= (int) $r['id'] ?>">
            <input type="hidden" name="listing_id" value="<?= (int) $r['listing_id'] ?>">
            <input class="form-control" name="answer" maxlength="1000" placeholder="Type your answer" required>
            <button class="btn btn-primary btn-sm">Post</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php adminFooter(); ?>
