<?php
require_once __DIR__ . '/includes/layout.php';
renderHeader('Privacy policy', '');
?>
<div class="wrap section" style="max-width:860px">
  <h1 style="font-size:1.7rem">Privacy policy</h1>
  <p class="muted">Last updated <?= e(date('d M Y')) ?>. DRIVE24 is the data controller for marketplace data (GDPR / DPDP aligned).</p>
  <div class="card card-pad">
    <h3>1. Data we collect</h3>
    <p class="muted">Account details (name, e-mail, mobile), KYC documents (PAN, Aadhaar, dealer licences), vehicle documents (RC, insurance), transaction records, chats, reviews and device logs.</p>
    <h3>2. Why we use it</h3>
    <p class="muted">Identity verification, listing publication, escrow and payouts, loan and insurance routing, RC transfer, fraud prevention and support. Marketing messages need separate opt-in consent.</p>
    <h3>3. KYC &amp; document handling</h3>
    <p class="muted">Government IDs are encrypted at rest, visible only to compliance staff, and retained per financial-record law (typically 5 years) before deletion.</p>
    <h3>4. Sharing</h3>
    <p class="muted">We share data only with payment gateways, lending and insurance partners, RTO agents and law enforcement when legally required - never sold to third parties.</p>
    <h3>5. Your rights</h3>
    <p class="muted">Request a copy of your data, correction, or account deletion via the help centre. Deletion honours legal retention holds. Breach notifications follow statutory timelines.</p>
    <h3>6. Security</h3>
    <p class="muted">TLS everywhere, hashed passwords, CSRF protection, prepared statements, role-based access and audit logging of personal-data access.</p>
    <h3>7. Cookies</h3>
    <p class="muted">Essential session cookies keep you signed in; analytics cookies need consent and can be disabled anytime.</p>
  </div>
</div>
<?php renderFooter(); ?>
