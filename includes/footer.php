<?php
/**
 * Global Footer & Scripts
 */
?>
      </div> <!-- /.content-body -->
    </main> <!-- /.main-wrapper -->
  </div> <!-- /.app-container -->

  <?php require_once __DIR__ . '/modals.php'; ?>

  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- HTML to PDF Bundle -->
  <script src="assets/js/html2pdf.bundle.min.js"></script>

  <!-- Core App JS -->
  <script src="assets/js/app.js?v=<?= time() ?>"></script>
  <script src="assets/js/charts.js?v=<?= time() ?>"></script>
  <script src="assets/js/invoice.js?v=<?= time() ?>"></script>

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
