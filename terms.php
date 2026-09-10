<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Terms of service', '');
?>
<div class="wrap section" style="max-width:860px">
  <h1 style="font-size:1.7rem">Terms of service</h1>
  <p class="muted">Last updated <?= e(date('d M Y')) ?>. These terms govern buying, selling and financing used cars on DRIVE24.</p>
  <div class="card card-pad">
    <h3>1. Marketplace role</h3>
    <p class="muted">DRIVE24 operates an online marketplace connecting buyers with individual sellers and verified dealers. Title transfers directly from seller to buyer; DRIVE24 facilitates inspection, documentation, escrow and logistics.</p>
    <h3>2. Listings &amp; inspections</h3>
    <p class="muted">Sellers warrant that VIN, odometer, ownership and accident disclosures are accurate. Every certified car clears a 280-point inspection; the report on the car page forms part of the sale description.</p>
    <h3>3. Pricing, offers &amp; escrow</h3>
    <p class="muted">The booking amount (Rs 25,000) is held in escrow and adjusted against the final price. Accepted offers are binding for 48 hours. Funds release to the seller after delivery and RC-transfer initiation; failed inspections trigger a full refund.</p>
    <h3>4. Test drives</h3>
    <p class="muted">Home and hub test drives require a valid driving licence. The refundable test-drive fee, where charged, is adjusted on purchase.</p>
    <h3>5. Finance &amp; insurance</h3>
    <p class="muted">Loans and policies are issued by partner banks and insurers subject to their approval. DRIVE24 only routes applications and never guarantees sanction.</p>
    <h3>6. Returns &amp; refunds</h3>
    <p class="muted">Certified cars carry a 5-day / 500 km money-back promise. Refunds return to the source account within 5-7 working days; escrow entries are visible on the order page.</p>
    <h3>7. Conduct</h3>
    <p class="muted">Off-platform payments, odometer tampering, fake documents and chat fraud lead to instant suspension and legal action. Chats are monitored for fraud keywords.</p>
    <h3>8. Liability</h3>
    <p class="muted">To the maximum extent permitted by law, DRIVE24's liability per transaction is limited to the platform fee charged for that order.</p>
  </div>
</div>
<?php renderFooter(); ?>
