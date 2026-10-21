<?php
/**
 * Global Footer & Scripts
 */
?>
      </div> <!-- /.content-body -->
    </main> <!-- /.main-wrapper -->
  </div> <!-- /.app-container -->

  <?php require_once __DIR__ . '/bottom_nav.php'; ?>
  <?php require_once __DIR__ . '/modals.php'; ?>

  <?php 
  $page = $GLOBALS['currentPage'] ?? '';
  $needsChart = in_array($page, ['owner-dashboard', 'admin-dashboard', 'reports', 'report-deck', '']);
  $needsInvoice = in_array($page, ['invoices', 'invoice-view', 'owner-dashboard', 'admin-dashboard', 'clients']);
  $needsPdf = in_array($page, ['invoice-view', 'salary-view', 'voucher-view', 'ads-voucher-view', 'reports', 'report-deck']);
  ?>

  <script>
    window.__KALA_BASE_URL__ = <?= json_encode(get_base_url()) ?>;
  </script>

  <!-- Conditional Heavy Assets Loading (Performance Boost) -->
  <?php if ($needsChart): ?>
    <script src="assets/js/chart.umd.min.js"></script>
    <script>
      if (typeof Chart === 'undefined') {
        document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
      }
    </script>
    <script src="assets/js/charts.js?v=<?= time() ?>"></script>
  <?php endif; ?>

  <?php if ($needsPdf): ?>
    <script src="assets/js/html2pdf.bundle.min.js"></script>
  <?php endif; ?>

  <!-- Core App JS -->
  <script src="assets/js/app.js?v=<?= time() ?>"></script>

  <?php if ($needsInvoice): ?>
    <script src="assets/js/invoice.js?v=<?= time() ?>"></script>
  <?php endif; ?>

  <?php
  $flash = get_flash();
  if ($flash):
  ?>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
      });
    </script>
  <?php endif; ?>
</body>
</html>
