<?php
/**
 * Global Modals for Quick Actions & Uploads
 */

// CLIENT ISOLATION: Clients must never receive internal financial/HR/admin creation modals
if (is_client()) {
    $cClientId = current_user_client_id();
    ?>
    <!-- Client Safe Modal: View Payment & Ads Receipt -->
    <div id="modal-view-receipt" class="modal-backdrop">
      <div class="modal-dialog" style="max-width: 680px;">
        <div class="modal-header">
          <h3 class="modal-title" id="view-receipt-title">Bukti Transaksi Saldo Iklan</h3>
          <button type="button" class="modal-close" onclick="closeModal('modal-view-receipt')">&times;</button>
        </div>
        <div class="modal-body" style="text-align: center; padding: 16px;">
          <img id="view-receipt-img" src="" alt="Bukti Transaksi" style="max-width: 100%; max-height: 70vh; border-radius: 8px; object-fit: contain; display: inline-block;">
          <iframe id="view-receipt-iframe" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px; display: none;"></iframe>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
          <a id="view-receipt-download" href="#" target="_blank" download class="btn btn-secondary btn-sm" style="font-weight: 700;">Unduh File Asli</a>
          <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('modal-view-receipt')">Tutup</button>
        </div>
      </div>
    </div>

    <!-- Client Safe Modal: Calendar Export PDF (Strictly Scoped) -->
    <div id="modal-export-content-pdf" class="modal-backdrop">
      <div class="modal-dialog">
        <div class="modal-header">
          <h3 class="modal-title">Ekspor Jadwal Konten (PDF)</h3>
          <button type="button" class="modal-close" onclick="closeModal('modal-export-content-pdf')">&times;</button>
        </div>
        <form id="form-export-content-pdf" action="api/content.php" method="GET" target="_blank">
          <input type="hidden" name="action" value="export_pdf">
          <input type="hidden" name="_slot" value="client">
          <input type="hidden" name="portal" value="client">
          <input type="hidden" name="client_id" value="<?= $cClientId ?>">
          <div class="modal-body">
            <div class="form-group">
              <label class="form-label">Rentang Waktu</label>
              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 4px;">
                <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #FFFFFF; border: 1.5px solid #2563EB; border-radius: 8px; cursor: pointer;" id="label-export-mode-month">
                  <input type="radio" name="range_type" value="month" checked onchange="toggleExportRangeMode(this.value)" style="accent-color: #2563EB;">
                  <span style="font-size: 13px; font-weight: 700; color: #101828;">Bulanan</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #FFFFFF; border: 1.5px solid #D0D5DD; border-radius: 8px; cursor: pointer;" id="label-export-mode-custom">
                  <input type="radio" name="range_type" value="custom" onchange="toggleExportRangeMode(this.value)" style="accent-color: #2563EB;">
                  <span style="font-size: 13px; font-weight: 700; color: #101828;">Kustom Tanggal</span>
                </label>
              </div>
            </div>
            <div class="form-group" id="export-month-wrap">
              <label class="form-label">Bulan *</label>
              <input type="month" name="month" id="export-month-input" class="form-control" value="<?= date('Y-m') ?>">
            </div>
            <div class="form-row" id="export-custom-range-wrap" style="display: none;">
              <div class="form-group">
                <label class="form-label">Dari Tanggal *</label>
                <input type="date" name="start_date" id="export-start-date" class="form-control" value="<?= date('Y-m-01') ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Sampai Tanggal *</label>
                <input type="date" name="end_date" id="export-end-date" class="form-control" value="<?= date('Y-m-t') ?>">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Platform</label>
                <select name="platform" class="form-select">
                  <option value="all">-- Semua Platform --</option>
                  <option value="Instagram">Instagram</option>
                  <option value="TikTok">TikTok</option>
                  <option value="YouTube">YouTube</option>
                  <option value="Meta Ads">Meta Ads (FB/IG)</option>
                  <option value="LinkedIn">LinkedIn</option>
                  <option value="Website">Website & Blog</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="all">-- Semua Status --</option>
                  <option value="Published">Published (Tayang)</option>
                  <option value="Scheduled">Scheduled (Terjadwal)</option>
                  <option value="Approved">Approved</option>
                  <option value="Review">In Review</option>
                  <option value="Draft">Draft</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-export-content-pdf')">Batal</button>
            <button type="submit" class="btn btn-primary">Unduh PDF</button>
          </div>
        </form>
      </div>
    </div>
    <?php
    return;
}

$db = Database::getConnection();
$allClients = $db->query("SELECT id, name, company FROM clients ORDER BY company ASC")->fetchAll();
$allProjects = $db->query("SELECT p.id, p.client_id, p.name, c.company FROM projects p JOIN clients c ON p.client_id = c.id ORDER BY p.id DESC")->fetchAll();
$unpaidInvoices = $db->query("SELECT id, invoice_number, total_amount, (SELECT company FROM clients WHERE id = invoices.client_id) as client_name FROM invoices WHERE status != 'Paid' ORDER BY id DESC")->fetchAll();
$allEmployees = $db->query("SELECT id, name, position, department, bank_name, bank_account, base_salary FROM employees WHERE status = 'Active' ORDER BY name ASC")->fetchAll();
$allCompanyNames = $db->query("
    SELECT DISTINCT name as comp_name FROM (
        SELECT company as name FROM clients WHERE COALESCE(is_deleted, 0) = 0 AND company IS NOT NULL AND company != ''
        UNION
        SELECT project_name as name FROM projects WHERE COALESCE(is_deleted, 0) = 0 AND project_name IS NOT NULL AND project_name != ''
        UNION
        SELECT name FROM projects WHERE COALESCE(is_deleted, 0) = 0 AND name IS NOT NULL AND name != ''
    ) t ORDER BY comp_name ASC
")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- 1. Modal Buat Invoice Baru -->
<div id="modal-create-invoice" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">+ Buat Invoice Baru</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-create-invoice')">&times;</button>
    </div>
    <form id="form-create-invoice" action="<?= url('api/invoices.php?action=create') ?>" method="POST" onsubmit="event.preventDefault(); submitInvoiceForm('form-create-invoice');">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Klien <span style="color:red;">*</span></label>
            <select name="client_id" id="inv-client-select" class="form-select" onchange="handleInvoiceClientChange(this.value)" required>
              <option value="">-- Pilih Klien --</option>
              <option value="__new__" <?= empty($allClients) ? 'selected' : '' ?>>+ Tambah Klien Baru Langsung</option>
              <?php foreach ($allClients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Proyek (Opsional)</label>
            <select name="project_id" id="inv-project-select" class="form-select">
              <option value="">-- Otomatis Terkait Layanan Invoice --</option>
              <?php foreach ($allProjects as $p): ?>
                <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['company']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Inline New Client Box (Active if __new__ selected or when no clients exist) -->
        <div id="box-new-client-fields" style="<?= empty($allClients) ? 'display:block;' : 'display:none;' ?> margin-bottom: 16px; padding: 14px; background: #F8F9FA; border-radius: var(--radius-sm); border: 1px dashed #D0D5DD;">
          <div style="font-size: 12px; font-weight: 700; color: #344054; margin-bottom: 8px;">Informasi Klien Baru</div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 8px;">
              <label class="form-label" style="font-size: 11.5px;">Nama Perusahaan / Brand <span style="color:red;">*</span></label>
              <input type="text" name="new_client_company" id="new-client-company" class="form-control" placeholder="Contoh: PT Surya Cipta" <?= empty($allClients) ? 'required' : '' ?>>
            </div>
            <div class="form-group" style="margin-bottom: 8px;">
              <label class="form-label" style="font-size: 11.5px;">Nama PIC</label>
              <input type="text" name="new_client_pic" id="new-client-pic" class="form-control" placeholder="Contoh: Bpk. Budi Santoso">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" style="font-size: 11.5px;">No. WhatsApp / Telepon</label>
              <input type="text" name="new_client_phone" id="new-client-phone" class="form-control" placeholder="Contoh: 08123456789">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" style="font-size: 11.5px;">Email Klien</label>
              <input type="email" name="new_client_email" id="new-client-email" class="form-control" placeholder="klien@perusahaan.com">
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Terbit *</label>
            <input type="date" name="issue_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Jatuh Tempo *</label>
            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status Tagihan</label>
            <select name="status" class="form-select">
              <option value="Draft" selected>Draft</option>
            </select>
          </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h4 style="font-size: 14px; font-weight: 700; color: #101828;">Daftar Layanan</h4>
          <button type="button" class="btn btn-secondary btn-sm" onclick="addInvoiceItemRow()">+ Tambah Layanan</button>
        </div>

        <div class="table-responsive">
          <table class="table-custom">
            <thead>
              <tr>
                <th>Deskripsi Layanan</th>
                <th style="width: 100px;">Kuantitas</th>
                <th style="width: 180px;">Harga Satuan (Rp)</th>
                <th style="width: 180px;" class="text-right">Subtotal (Rp)</th>
                <th style="width: 50px;"></th>
              </tr>
            </thead>
            <tbody id="invoice-items-container">
              <tr id="item-init-1">
                <td>
                  <input type="text" class="form-control item-name" placeholder="Misal: Retainer Social Media Management Agustus" required>
                </td>
                <td style="width: 100px;">
                  <input type="number" class="form-control item-qty" value="1" min="1" step="1" oninput="calculateInvoiceTotals()" required>
                </td>
                <td style="width: 200px;">
                  <div class="input-group-currency">
                    <span class="currency-addon">Rp</span>
                    <input type="text" class="form-control item-price rupiah-input" placeholder="0" oninput="calculateInvoiceTotals()" required>
                  </div>
                </td>
                <td style="width: 180px;" class="text-right">
                  <span class="item-line-total" style="font-weight: 700; color: #101828;">Rp 0</span>
                </td>
                <td style="width: 50px; text-align: center;">
                  <button type="button" class="btn-sm btn-danger" onclick="removeInvoiceItemRow('item-init-1')" title="Hapus Item">&times;</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals & Calculations -->
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
          <div style="width: 320px; background: #F9FAFB; padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">Subtotal:</span>
              <span id="inv-display-subtotal" style="font-weight: 700; color: #101828;">Rp 0</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">Diskon (%):</span>
              <div style="display: flex; align-items: center; gap: 8px; width: 140px;">
                <input type="number" name="discount_percent" id="inv-discount-percent" class="form-control" style="padding: 4px 8px; height: 28px; width: 60px;" value="0" min="0" max="100" oninput="calculateInvoiceTotals()">
                <span id="inv-display-discount" style="font-size: 12px; color: var(--danger);">- Rp 0</span>
              </div>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">PPN (%):</span>
              <div style="display: flex; align-items: center; gap: 8px; width: 140px;">
                <input type="number" name="tax_percent" id="inv-tax-percent" class="form-control" style="padding: 4px 8px; height: 28px; width: 60px;" value="0" min="0" max="100" oninput="calculateInvoiceTotals()">
                <span id="inv-display-tax" style="font-size: 12px; color: var(--info);">+ Rp 0</span>
              </div>
            </div>
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; color: #FFF;">
              <span>Total Tagihan:</span>
              <span id="inv-display-total" style="color: #34D399;">Rp 0</span>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
          <label class="form-label">Catatan & Syarat Pembayaran</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Pembayaran ditransfer ke rekening BCA PT Kalamedia Kreatif Nusantara"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-invoice')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan & Terbitkan Invoice</button>
      </div>
    </form>
  </div>
</div>

<!-- 1.1 Modal Edit Invoice (Khusus Draft) -->
<div id="modal-edit-invoice" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <h3 class="modal-title" style="margin: 0;">Edit Draft Invoice</h3>
        <span id="edit-inv-number-badge" style="font-size: 13px; font-weight: 700; color: #1570EF; background: #EFF8FF; border: 1px solid #B2DDFF; padding: 2px 8px; border-radius: 6px;"></span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-invoice')">&times;</button>
    </div>
    <form id="form-edit-invoice" action="api/invoices.php?action=update" method="POST" onsubmit="event.preventDefault(); submitEditInvoiceForm('form-edit-invoice');">
      <input type="hidden" name="invoice_id" id="edit-inv-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Klien *</label>
            <select name="client_id" id="edit-inv-client-select" class="form-select" onchange="loadProjectsForClient(this.value, 'edit-inv-project-select')" required>
              <option value="">-- Pilih Klien --</option>
              <?php foreach ($allClients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Proyek</label>
            <select name="project_id" id="edit-inv-project-select" class="form-select">
              <option value="">-- Pilih Proyek --</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Terbit *</label>
            <input type="date" name="issue_date" id="edit-inv-issue-date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Jatuh Tempo *</label>
            <input type="date" name="due_date" id="edit-inv-due-date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Status Tagihan</label>
            <select name="status" id="edit-inv-status" class="form-select">
              <option value="Draft" selected>Draft</option>
            </select>
          </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h4 style="font-size: 14px; font-weight: 700; color: #101828; margin: 0;">Daftar Layanan</h4>
          <button type="button" class="btn btn-secondary btn-sm" onclick="addEditInvoiceItemRow()">+ Tambah Layanan</button>
        </div>

        <div class="table-responsive">
          <table class="table-custom">
            <thead>
              <tr>
                <th>Deskripsi Layanan</th>
                <th style="width: 100px;">Kuantitas</th>
                <th style="width: 180px;">Harga Satuan (Rp)</th>
                <th style="width: 180px;" class="text-right">Subtotal (Rp)</th>
                <th style="width: 50px;"></th>
              </tr>
            </thead>
            <tbody id="edit-invoice-items-container">
            </tbody>
          </table>
        </div>

        <!-- Totals & Calculations -->
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
          <div style="width: 320px; background: #F9FAFB; padding: 16px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">Subtotal:</span>
              <span id="edit-inv-display-subtotal" style="font-weight: 700; color: #101828;">Rp 0</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">Diskon (%):</span>
              <div style="display: flex; align-items: center; gap: 8px; width: 140px;">
                <input type="number" name="discount_percent" id="edit-inv-discount-percent" class="form-control" style="padding: 4px 8px; height: 28px; width: 60px;" value="0" min="0" max="100" oninput="calculateEditInvoiceTotals()">
                <span id="edit-inv-display-discount" style="font-size: 12px; color: var(--danger);">- Rp 0</span>
              </div>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 13px; margin-bottom: 8px;">
              <span style="color: var(--text-secondary);">PPN (%):</span>
              <div style="display: flex; align-items: center; gap: 8px; width: 140px;">
                <input type="number" name="tax_percent" id="edit-inv-tax-percent" class="form-control" style="padding: 4px 8px; height: 28px; width: 60px;" value="0" min="0" max="100" oninput="calculateEditInvoiceTotals()">
                <span id="edit-inv-display-tax" style="font-size: 12px; color: var(--info);">+ Rp 0</span>
              </div>
            </div>
            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; font-size: 16px; font-weight: 800; color: #101828;">
              <span>Total Tagihan:</span>
              <span id="edit-inv-display-total" style="color: #027A48;">Rp 0</span>
            </div>
          </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
          <label class="form-label">Catatan & Syarat Pembayaran</label>
          <textarea name="notes" id="edit-inv-notes" class="form-control" rows="2" placeholder="Catatan atau syarat pembayaran invoice..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-invoice')">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-edit-invoice">Simpan Perubahan Invoice</button>
      </div>
    </form>
  </div>
</div>

<!-- 2. Modal Catat Pelunasan Invoice (Inflow) -->
<div id="modal-record-payment" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">+ Catat Pelunasan Invoice</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-record-payment')">&times;</button>
    </div>
    <form id="form-record-payment" action="api/invoices.php?action=mark_paid" method="POST">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Invoice Terkait *</label>
          <select name="invoice_id" class="form-select" required>
            <option value="">-- Pilih Invoice --</option>
            <?php foreach ($unpaidInvoices as $inv): ?>
              <option value="<?= $inv['id'] ?>">#<?= $inv['invoice_number'] ?> - <?= htmlspecialchars($inv['client_name']) ?> (<?= format_rupiah($inv['total_amount']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Metode Pembayaran *</label>
          <select name="payment_method" class="form-select" required>
            <option value="Bank Transfer BCA">Bank Transfer BCA</option>
            <option value="Bank Transfer Mandiri">Bank Transfer Mandiri</option>
            <option value="Bank Transfer BNI">Bank Transfer BNI</option>
            <option value="QRIS / E-Wallet">QRIS</option>
            <option value="Cash / Tunai">Tunai (Cash)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Tanggal Pembayaran *</label>
          <input type="datetime-local" name="paid_at" class="form-control" value="<?= date('Y-m-d\TH:i') ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-record-payment')">Batal</button>
        <button type="submit" class="btn btn-success">Verifikasi & Catat Lunas</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-record-payment', () => {
    closeModal('modal-record-payment');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 3. Modal Catat Pembayaran Vendor & Freelancer (Outflow) -->
<div id="modal-input-payout" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">+ Catat Pembayaran Vendor & Freelancer</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-input-payout')">&times;</button>
    </div>
    <form id="form-input-payout" action="api/expenses.php?action=create_payout" method="POST">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Penerima *</label>
            <input type="text" name="freelancer_name" class="form-control" placeholder="Nama lengkap penerima" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. WhatsApp / HP</label>
            <input type="text" name="freelancer_phone" class="form-control" placeholder="Contoh: 081234567890">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bank Penerima</label>
            <input type="text" name="freelancer_bank" class="form-control" placeholder="Contoh: BCA / Mandiri" value="BCA">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Rekening</label>
            <input type="text" name="freelancer_account" class="form-control" placeholder="Nomor rekening transfer">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Klien Terkait *</label>
            <select name="client_id" id="payout-client-select" class="form-select" onchange="loadProjectsForClient(this.value, 'payout-project-select')" required>
              <option value="">-- Pilih Klien --</option>
              <?php foreach ($allClients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label class="form-label" style="margin-bottom: 0;">Proyek</label>
              <a href="javascript:void(0)" onclick="openModal('modal-create-project')" style="font-size: 11px; color: var(--primary); text-decoration: none;">+ Proyek Baru</a>
            </div>
            <select name="project_id" id="payout-project-select" class="form-select">
              <option value="">-- Terkait Proyek Klien --</option>
              <?php foreach ($allProjects as $p): ?>
                <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['company']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Uraian Pekerjaan *</label>
          <textarea name="task_description" class="form-control" rows="2" placeholder="Contoh: 3D Animation Rendering 15 Detik" required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Honorarium (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Status Pembayaran</label>
            <select name="status" class="form-select">
              <option value="Pending" selected>Pending (Belum Ditransfer)</option>
              <option value="Paid">Paid (Sudah Ditransfer)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-input-payout')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Pembayaran</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-input-payout', () => {
    closeModal('modal-input-payout');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 4. Modal Catat Pengeluaran Iklan Digital (Ads Spend) -->
<div id="modal-catat-ads" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">+ Catat Pengeluaran Iklan Digital</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-catat-ads')">&times;</button>
    </div>
    <form id="form-catat-ads" action="api/expenses.php?action=create_ads" method="POST">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Klien Terkait *</label>
          <select name="client_id" class="form-select" onchange="loadProjectsForClient(this.value, 'ads-project-select')" required>
            <option value="">-- Pilih Klien --</option>
            <?php foreach ($allClients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
            <label class="form-label" style="margin-bottom: 0;">Proyek</label>
            <a href="javascript:void(0)" onclick="openModal('modal-create-project')" style="font-size: 11px; color: var(--primary); text-decoration: none;">+ Proyek Baru</a>
          </div>
          <select name="project_id" id="ads-project-select" class="form-select">
            <option value="">-- Terkait Proyek Klien --</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Platform Iklan *</label>
            <select name="platform" class="form-select" required>
              <option value="Meta Ads">Meta Ads (Facebook & Instagram)</option>
              <option value="Google Ads">Google Ads (Search & YouTube)</option>
              <option value="TikTok Ads">TikTok Ads</option>
              <option value="LinkedIn Ads">LinkedIn Ads</option>
              <option value="Twitter/X Ads">X (Twitter) Ads</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">ID Akun Iklan</label>
            <input type="text" name="account_id" class="form-control" placeholder="Misal: 2268451143696057">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nominal Anggaran (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="amount" class="form-control rupiah-input" placeholder="0" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <input type="date" name="spent_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Keterangan Kampanye</label>
          <textarea name="notes" class="form-control" rows="2" placeholder="Contoh: Top up campaign promo bulanan"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-catat-ads')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Biaya Iklan</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-catat-ads', () => {
    closeModal('modal-catat-ads');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 3b. Modal Edit Pembayaran Vendor & Freelancer -->
<div id="modal-edit-payout" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">&#9998; Edit Pembayaran Vendor &amp; Freelancer</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-payout')">&times;</button>
    </div>
    <form id="form-edit-payout" action="api/expenses.php?action=update_payout" method="POST">
      <input type="hidden" name="id" id="edit-payout-id">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Penerima *</label>
            <input type="text" name="freelancer_name" id="edit-payout-name" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. WhatsApp / HP</label>
            <input type="text" name="freelancer_phone" id="edit-payout-phone" class="form-control" placeholder="Contoh: 081234567890">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bank Penerima</label>
            <input type="text" name="freelancer_bank" id="edit-payout-bank" class="form-control" placeholder="BCA / Mandiri">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Rekening</label>
            <input type="text" name="freelancer_account" id="edit-payout-account" class="form-control" placeholder="Nomor rekening transfer">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Klien Terkait *</label>
            <select name="client_id" id="edit-payout-client-select" class="form-select" onchange="loadProjectsForClient(this.value, 'edit-payout-project-select')" required>
              <option value="">-- Pilih Klien --</option>
              <?php foreach ($allClients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Proyek</label>
            <select name="project_id" id="edit-payout-project-select" class="form-select">
              <option value="">-- Terkait Proyek Klien --</option>
              <?php foreach ($allProjects as $p): ?>
                <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['company']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Uraian Pekerjaan *</label>
          <textarea name="task_description" id="edit-payout-task" class="form-control" rows="2" required></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Honorarium (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="amount" id="edit-payout-amount" class="form-control rupiah-input" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Status Pembayaran</label>
            <select name="status" id="edit-payout-status" class="form-select">
              <option value="Pending">Pending (Belum Ditransfer)</option>
              <option value="Paid">Paid (Sudah Ditransfer)</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-payout')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-edit-payout', () => {
    closeModal('modal-edit-payout');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 4b. Modal Edit Pengeluaran Iklan Digital (Ads Spend) -->
<div id="modal-edit-ads" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">&#9998; Edit Pengeluaran Iklan Digital</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-ads')">&times;</button>
    </div>
    <form id="form-edit-ads" action="api/expenses.php?action=update_ads" method="POST">
      <input type="hidden" name="id" id="edit-ads-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Klien Terkait *</label>
          <select name="client_id" id="edit-ads-client-select" class="form-select" onchange="loadProjectsForClient(this.value, 'edit-ads-project-select')" required>
            <option value="">-- Pilih Klien --</option>
            <?php foreach ($allClients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Proyek</label>
          <select name="project_id" id="edit-ads-project-select" class="form-select">
            <option value="">-- Terkait Proyek Klien --</option>
            <?php foreach ($allProjects as $p): ?>
              <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['company']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Platform Iklan *</label>
            <select name="platform" id="edit-ads-platform" class="form-select" required>
              <option value="Meta Ads">Meta Ads (Facebook &amp; Instagram)</option>
              <option value="Google Ads">Google Ads (Search &amp; YouTube)</option>
              <option value="TikTok Ads">TikTok Ads</option>
              <option value="LinkedIn Ads">LinkedIn Ads</option>
              <option value="Twitter/X Ads">X (Twitter) Ads</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">ID Akun Iklan</label>
            <input type="text" name="account_id" id="edit-ads-account" class="form-control" placeholder="Misal: 2268451143696057">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nominal Anggaran (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="amount" id="edit-ads-amount" class="form-control rupiah-input" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Transaksi *</label>
            <input type="date" name="spent_date" id="edit-ads-date" class="form-control" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Keterangan Kampanye</label>
          <textarea name="notes" id="edit-ads-notes" class="form-control" rows="2" placeholder="Contoh: Top up campaign promo bulanan"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-ads')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-edit-ads', () => {
    closeModal('modal-edit-ads');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 5. Modal Upload Bukti Transfer (Max 5MB) -->
<div id="modal-upload-receipt" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" id="upload-modal-title">Unggah Bukti Transaksi</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-upload-receipt')">&times;</button>
    </div>
    <form id="form-upload-receipt" action="api/upload.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="target_type" id="upload-target-type">
      <input type="hidden" name="target_id" id="upload-target-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">File Bukti Transaksi (JPG, PNG, WEBP, PDF maks. 5MB) *</label>
          <input type="file" name="receipt_file" id="receipt-file-input" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" required>
          <small style="color: var(--text-muted); font-size: 11px; margin-top: 4px; display: block;">Format yang didukung: JPG, PNG, WEBP, PDF (Ukuran berkas maksimal 5 MB).</small>
        </div>

        <div id="receipt-preview-container" style="display: none; margin-top: 16px; text-align: center; background: rgba(0,0,0,0.3); padding: 12px; border-radius: var(--radius-sm); border: 1px dashed var(--border-color);">
          <p style="font-size: 11px; color: var(--text-secondary); margin-bottom: 8px;">Pratinjau Bukti:</p>
          <img id="receipt-preview-img" src="" style="max-height: 220px; max-width: 100%; border-radius: 6px; box-shadow: 0 4px 10px rgba(0,0,0,0.5);">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-upload-receipt')">Batal</button>
        <button type="submit" class="btn btn-primary">Unggah & Verifikasi</button>
      </div>
    </form>
  </div>
</div>

<!-- 6. Modal View Bukti Struk (Lightbox Zoom) -->
<div id="modal-view-receipt" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title" id="view-receipt-title">Bukti Transaksi</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-view-receipt')">&times;</button>
    </div>
    <div class="modal-body" style="text-align: center; background: #000; padding: 20px;">
      <img id="view-receipt-img" src="" style="max-width: 100%; max-height: 70vh; border-radius: 8px; object-fit: contain;">
      <iframe id="view-receipt-iframe" src="" style="width: 100%; height: 70vh; border: none; border-radius: 8px; display: none;"></iframe>
    </div>
    <div class="modal-footer">
      <a id="view-receipt-download" href="" target="_blank" download class="btn btn-secondary btn-sm">Unduh File Asli</a>
      <button type="button" class="btn btn-primary btn-sm" onclick="closeModal('modal-view-receipt')">Tutup</button>
    </div>
  </div>
</div>

<!-- 7. Modal Tambah Klien Baru -->
<div id="modal-create-client" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">+ Tambah Klien Baru</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-create-client')">&times;</button>
    </div>
    <form id="form-create-client" action="<?= url('api/clients.php?action=create_client') ?>" method="POST">
      <div class="modal-body">
        <!-- 1. Nama Perusahaan / PT (Dropdown dari Proyek / Klien) -->
        <div class="form-group">
          <label class="form-label">Nama Perusahaan / PT <span style="color:red;">*</span></label>
          <select name="company_select" id="create-client-company-select" class="form-select" onchange="toggleClientCompanyInput(this.value)" required>
            <option value="">-- Pilih PT / Perusahaan dari Daftar Proyek --</option>
            <?php foreach ($allCompanyNames as $cName): ?>
              <option value="<?= htmlspecialchars($cName) ?>"><?= htmlspecialchars($cName) ?></option>
            <?php endforeach; ?>
            <option value="__new__" <?= empty($allCompanyNames) ? 'selected' : '' ?>>+ Ketik Nama PT Baru</option>
          </select>
          <div id="box-new-client-company-input" style="<?= empty($allCompanyNames) ? 'display:block;' : 'display:none;' ?> margin-top: 8px;">
            <input type="text" name="company_new" id="create-client-company-new" class="form-control" placeholder="Ketik nama PT / Brand baru (Contoh: PT Sumber Berkah Abadi)" <?= empty($allCompanyNames) ? 'required' : '' ?>>
          </div>
        </div>

        <!-- 2. Nama Orang (Label diubah dari PIC menjadi Nama) -->
        <div class="form-group">
          <label class="form-label">Nama <span style="color:red;">*</span></label>
          <input type="text" name="name" class="form-control" placeholder="Contoh: Budi Gunawan" required>
        </div>

        <!-- 3. Email Login Portal & Password Akses Portal -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Login Portal Klien <span style="color:red;">*</span></label>
            <input type="email" name="email" class="form-control" placeholder="budi@company.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password Akses Portal <span style="color:red;">*</span></label>
            <div style="position: relative; display: flex; align-items: center;">
              <input type="password" name="password" id="client-password-input" class="form-control" placeholder="Password untuk klien login" required style="padding-right: 55px;">
              <button type="button" onclick="togglePasswordVisibility('client-password-input', this)" style="position: absolute; right: 10px; background: none; border: none; font-size: 11px; font-weight: 700; color: var(--primary); cursor: pointer; padding: 4px;">Lihat</button>
            </div>
          </div>
        </div>

        <!-- 4. Nomor WhatsApp -->
        <div class="form-group">
          <label class="form-label">Nomor WhatsApp</label>
          <input type="text" name="phone" class="form-control" placeholder="081234567890">
        </div>

        <!-- 5. Jasa / Layanan Kalamedia (service_type) -->
        <div class="form-group">
          <label class="form-label">Pilih Jasa / Layanan Kalamedia <span style="color:red;">*</span></label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 12px; padding: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Kelola Sosmed" checked>
              <span>Kelola Sosmed</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Desain Eceran / Satuan">
              <span>Desain Eceran / Satuan</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Jasa Buat Web">
              <span>Jasa Buat Web</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Jasa Iklan Meta & Google">
              <span>Jasa Iklan Meta & Google</span>
            </label>
          </div>
          <small style="font-size: 11px; color: var(--text-secondary); margin-top: 4px; display: block;">Dapat memilih satu atau beberapa layanan yang dikontrak klien.</small>
        </div>

        <!-- 6. Masa Kontrak Klien -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mulai Kontrak <span style="color:red;">*</span></label>
            <input type="date" name="contract_start_date" id="create-client-start-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Sampai Tanggal / Akhir Kontrak <span style="color:red;">*</span></label>
            <input type="date" name="contract_end_date" id="create-client-end-date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-client')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Klien & Buat Akses</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleClientCompanyInput(val) {
  const box = document.getElementById('box-new-client-company-input');
  const input = document.getElementById('create-client-company-new');
  if (val === '__new__') {
    if (box) box.style.display = 'block';
    if (input) input.required = true;
  } else {
    if (box) box.style.display = 'none';
    if (input) input.required = false;
  }
}

function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    if (btn) btn.innerText = 'Tutup';
  } else {
    input.type = 'password';
    if (btn) btn.innerText = 'Lihat';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-create-client', () => {
    closeModal('modal-create-client');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 8. Modal Tambah Proyek Baru -->
<div id="modal-create-project" class="modal-backdrop">
  <div class="modal-dialog" style="max-width: 620px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">+ Buat Proyek Baru</h3>
        <span style="font-size: 12px; color: var(--text-secondary);">Pemisahan Otomatis Nominal Jasa Agensi vs Saldo Iklan Klien</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-create-project')">&times;</button>
    </div>
    <form id="form-create-project" action="api/clients.php?action=create_project" method="POST">
      <div class="modal-body">
        <div class="form-group">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
            <label class="form-label" style="margin-bottom: 0;">Pilih Klien Terkait *</label>
            <a href="javascript:void(0)" onclick="toggleInlineNewClient()" id="toggle-new-client-link" style="font-size: 11.5px; font-weight: 700; color: #175CD3; text-decoration: none;">
              + Klien Baru
            </a>
          </div>
          <select name="client_id" id="create-project-client-select" class="form-select" onchange="onProjectClientSelectChange(this.value)" required>
            <option value="">-- Pilih Klien --</option>
            <?php foreach ($allClients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
            <?php endforeach; ?>
            <option value="__new__">+ Klien Baru (Input Cepat Langsung)</option>
          </select>
        </div>

        <!-- Inline New Client Box (Solves 0 clients empty dropdown issue) -->
        <div id="inline-client-fields" style="<?= empty($allClients) ? 'display:block;' : 'display:none;' ?> background: #F8FAFC; border: 1.5px dashed #CBD5E1; border-radius: 10px; padding: 14px; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 700; color: #1E293B; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
            <span>🏢 Data Klien Baru (Otomatis dibuat & terhubung ke Proyek):</span>
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 8px;">
              <label class="form-label" style="font-size: 11.5px;">Nama Perusahaan / Brand *</label>
              <input type="text" name="inline_company" id="inline-company-input" class="form-control" placeholder="PT Sukses Mandiri / Brand X">
            </div>
            <div class="form-group" style="margin-bottom: 8px;">
              <label class="form-label" style="font-size: 11.5px;">Nama PIC *</label>
              <input type="text" name="inline_pic_name" id="inline-pic-input" class="form-control" placeholder="Bpk. Budi Santoso">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" style="font-size: 11.5px;">Nomor WhatsApp</label>
              <input type="text" name="inline_phone" class="form-control" placeholder="081234567890">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label" style="font-size: 11.5px;">Layanan Utama</label>
              <select name="inline_service_type" class="form-select">
                <option value="Jasa Iklan Meta & Google">Jasa Iklan Meta & Google</option>
                <option value="Kelola Sosmed">Kelola Sosmed</option>
                <option value="Desain Eceran / Satuan">Desain Eceran / Satuan</option>
                <option value="Jasa Buat Web">Jasa Buat Web</option>
              </select>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nama Proyek *</label>
          <input type="text" name="name" class="form-control" placeholder="Contoh: Meta & Google Ads Campaign Q4" required>
        </div>

        <!-- Section: Pemisahan Nominal Jasa vs Saldo Ads -->
        <div style="background: #F8F9FC; border: 1px solid #EAECF0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 700; color: #344054; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
            <span>💰 Rincian Komponen Nilai Proyek (Financial Separation):</span>
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #175CD3;">
                Nominal Jasa Agensi / Fee Manajemen (Rp) *
              </label>
              <div class="input-group-currency">
                <span class="currency-addon" style="background: #EFF8FF; color: #175CD3; font-weight: 700;">Rp</span>
                <input type="text" name="service_fee_amount" id="create-service-fee" class="form-control rupiah-input" placeholder="0" oninput="recalcProjectSummary('create')" required>
              </div>
              <span style="font-size: 10.5px; color: var(--text-muted); display: block; margin-top: 3px;">
                Pendapatan murni Kalamedia (dihitung sebagai laba kotor)
              </span>
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #027A48;">
                Saldo Ads Awal Klien (Rp)
              </label>
              <div class="input-group-currency">
                <span class="currency-addon" style="background: #ECFDF3; color: #027A48; font-weight: 700;">Rp</span>
                <input type="text" name="ads_budget_pool" id="create-ads-budget" class="form-control rupiah-input" placeholder="0" oninput="recalcProjectSummary('create')">
              </div>
              <span style="font-size: 10.5px; color: var(--text-muted); display: block; margin-top: 3px;">
                Titipan belanja iklan klien (bukan laba agensi)
              </span>
            </div>
          </div>

          <!-- Live Financial Summary Card -->
          <div id="create-project-summary-box" style="background: #FFFFFF; border: 1px solid #D0D5DD; border-radius: 8px; padding: 12px; margin-top: 6px;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
              <div>
                <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; display: block;">Total Tagihan Klien</span>
                <span id="create-summary-total" style="font-size: 14px; font-weight: 800; color: #101828;">Rp 0</span>
              </div>
              <div style="border-left: 1px solid #EAECF0; border-right: 1px solid #EAECF0;">
                <span style="font-size: 10.5px; color: #175CD3; font-weight: 600; text-transform: uppercase; display: block;">Porsi Jasa Agensi</span>
                <span id="create-summary-service" style="font-size: 14px; font-weight: 800; color: #175CD3;">Rp 0 (0%)</span>
              </div>
              <div>
                <span style="font-size: 10.5px; color: #027A48; font-weight: 600; text-transform: uppercase; display: block;">Porsi Saldo Ads</span>
                <span id="create-summary-ads" style="font-size: 14px; font-weight: 800; color: #027A48;">Rp 0 (0%)</span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Target Margin Profit Jasa (%)</label>
            <input type="number" name="target_margin_percent" class="form-control" value="30.00" min="0" max="100" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Mulai Kontrak</label>
            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Target Selesai / Akhir Kontrak</label>
            <input type="date" name="end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+3 months')) ?>">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-project')">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-create-project">Simpan Proyek Baru</button>
      </div>
    </form>
  </div>
</div>
<script>
function onProjectClientSelectChange(val) {
  const inlineBox = document.getElementById('inline-client-fields');
  const compInput = document.getElementById('inline-company-input');
  const picInput = document.getElementById('inline-pic-input');
  if (val === '__new__') {
    if (inlineBox) inlineBox.style.display = 'block';
    if (compInput) compInput.setAttribute('required', 'required');
    if (picInput) picInput.setAttribute('required', 'required');
  } else {
    if (inlineBox) inlineBox.style.display = 'none';
    if (compInput) compInput.removeAttribute('required');
    if (picInput) picInput.removeAttribute('required');
  }
}

function toggleInlineNewClient() {
  const sel = document.getElementById('create-project-client-select');
  if (sel) {
    sel.value = '__new__';
    onProjectClientSelectChange('__new__');
  }
}

function parseCurrencyVal(str) {
  if (!str) return 0;
  const cleaned = String(str).replace(/[^0-9]/g, '');
  return parseFloat(cleaned) || 0;
}

function formatRupiahJs(num) {
  return 'Rp ' + Math.round(num).toLocaleString('id-ID');
}

function recalcProjectSummary(prefix) {
  const feeInput = document.getElementById(`${prefix}-service-fee`);
  const adsInput = document.getElementById(`${prefix}-ads-budget`);
  const feeVal = feeInput ? parseCurrencyVal(feeInput.value) : 0;
  const adsVal = adsInput ? parseCurrencyVal(adsInput.value) : 0;
  const total = feeVal + adsVal;

  const totalEl = document.getElementById(`${prefix}-summary-total`);
  const serviceEl = document.getElementById(`${prefix}-summary-service`);
  const adsEl = document.getElementById(`${prefix}-summary-ads`);

  const feePct = total > 0 ? Math.round((feeVal / total) * 100) : 0;
  const adsPct = total > 0 ? Math.round((adsVal / total) * 100) : 0;

  if (totalEl) totalEl.innerText = formatRupiahJs(total);
  if (serviceEl) serviceEl.innerText = `${formatRupiahJs(feeVal)} (${feePct}%)`;
  if (adsEl) adsEl.innerText = `${formatRupiahJs(adsVal)} (${adsPct}%)`;
}

document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-create-project', () => {
    closeModal('modal-create-project');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 8b. Modal Edit Data Klien -->
<div id="modal-edit-client" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Edit Data Klien</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-client')">&times;</button>
    </div>
    <form id="form-edit-client" action="api/clients.php?action=update_client" method="POST">
      <input type="hidden" name="id" id="edit-client-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Perusahaan / PT *</label>
          <input type="text" name="company" id="edit-client-company" class="form-control" placeholder="Contoh: PT Sumber Berkah Abadi" required>
        </div>
        <div class="form-group">
          <label class="form-label">Nama *</label>
          <input type="text" name="name" id="edit-client-name" class="form-control" placeholder="Contoh: Budi Gunawan" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Login Portal *</label>
            <input type="email" name="email" id="edit-client-email" class="form-control" placeholder="budi@company.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" name="phone" id="edit-client-phone" class="form-control" placeholder="081234567890">
          </div>
        </div>

        <!-- Jasa / Layanan Kalamedia (service_type) -->
        <div class="form-group">
          <label class="form-label">Pilih Jasa / Layanan Kalamedia <span style="color:red;">*</span></label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 12px; padding: 12px;">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Kelola Sosmed" class="edit-client-service-chk">
              <span>Kelola Sosmed</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Desain Eceran / Satuan" class="edit-client-service-chk">
              <span>Desain Eceran / Satuan</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Jasa Buat Web" class="edit-client-service-chk">
              <span>Jasa Buat Web</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 12.5px; cursor: pointer; font-weight: 600; color: #101828;">
              <input type="checkbox" name="service_type[]" value="Jasa Iklan Meta & Google" class="edit-client-service-chk">
              <span>Jasa Iklan Meta & Google</span>
            </label>
          </div>
        </div>

        <!-- Masa Kontrak Klien -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mulai Kontrak <span style="color:red;">*</span></label>
            <input type="date" name="contract_start_date" id="edit-client-start-date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Sampai Tanggal / Akhir Kontrak <span style="color:red;">*</span></label>
            <input type="date" name="contract_end_date" id="edit-client-end-date" class="form-control" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password Akses Portal Baru (Opsional)</label>
          <div style="position: relative; display: flex; align-items: center;">
            <input type="password" name="password" id="edit-client-password" class="form-control" placeholder="Kosongkan jika tidak ingin mengubah password" style="padding-right: 64px;">
            <button type="button" onclick="togglePasswordVisibility('edit-client-password', this)" style="position: absolute; right: 8px; background: transparent; border: 1px solid var(--border-color, #E4E7EC); border-radius: 6px; padding: 4px 8px; font-size: 11px; font-weight: 600; color: #475467; cursor: pointer;">
              Lihat
            </button>
          </div>
          <div style="font-size: 11px; color: var(--text-secondary, #667085); margin-top: 4px;">Hanya isi jika ingin mereset password login akun klien ke Client Portal.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-client')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-edit-client', () => {
    closeModal('modal-edit-client');
    setTimeout(() => window.location.reload(), 800);
  });
  handleAjaxForm('form-renew-client-contract', () => {
    closeModal('modal-renew-client-contract');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 8b-2. Modal Perpanjang Masa Kontrak Klien -->
<div id="modal-renew-client-contract" class="modal-backdrop">
  <div class="modal-dialog" style="max-width: 520px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Perpanjang Masa Kontrak Klien</h3>
        <span style="font-size: 12px; color: var(--text-secondary);">Aktifkan kembali akses akun portal klien yang habis kontrak</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-renew-client-contract')">&times;</button>
    </div>
    <form id="form-renew-client-contract" action="api/clients.php?action=renew_contract" method="POST">
      <input type="hidden" name="client_id" id="renew-client-id">
      <div class="modal-body">
        <div style="background: #F8F9FC; border: 1px solid #ECEEF5; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6E7485;">Klien / Perusahaan</div>
          <div style="font-size: 16px; font-weight: 800; color: #101828; margin: 2px 0 4px;" id="renew-client-company-label">-</div>
          <div style="font-size: 12px; color: #6E7485;" id="renew-client-current-info">-</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mulai Kontrak Baru <span style="color:red;">*</span></label>
            <input type="date" name="contract_start_date" id="renew-contract-start-date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Sampai Tanggal / Akhir Baru <span style="color:red;">*</span></label>
            <input type="date" name="contract_end_date" id="renew-contract-end-date" class="form-control" required>
          </div>
        </div>

        <div style="margin-top: 8px;">
          <label class="form-label" style="font-size: 11px; color: var(--text-secondary);">Pilihan Cepat Durasi Perpanjangan:</label>
          <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            <button type="button" class="btn btn-secondary btn-sm" onclick="setRenewDurationDays(30)">+1 Bulan (30 Hari)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="setRenewDurationDays(90)">+3 Bulan (90 Hari)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="setRenewDurationDays(180)">+6 Bulan (180 Hari)</button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="setRenewDurationDays(365)">+1 Tahun (365 Hari)</button>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-renew-client-contract')">Batal</button>
        <button type="submit" class="btn btn-primary" style="background: #027A48; border-color: #027A48;">
          Perpanjang &amp; Aktifkan Akun Klien
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 8c. Modal Edit Data Proyek -->
<div id="modal-edit-project" class="modal-backdrop">
  <div class="modal-dialog" style="max-width: 620px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">Edit Data Proyek</h3>
        <span style="font-size: 12px; color: var(--text-secondary);">Perbarui Komponen Nominal Jasa Agensi vs Saldo Ads</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-project')">&times;</button>
    </div>
    <form id="form-edit-project" action="api/clients.php?action=update_project" method="POST">
      <input type="hidden" name="id" id="edit-project-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Klien Terkait *</label>
          <select name="client_id" id="edit-project-client-id" class="form-select" required>
            <option value="">-- Pilih Klien --</option>
            <?php foreach ($allClients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Nama Proyek *</label>
          <input type="text" name="name" id="edit-project-name" class="form-control" placeholder="Contoh: Digital Campaign & Branding Q4" required>
        </div>

        <!-- Section: Pemisahan Nominal Jasa vs Saldo Ads -->
        <div style="background: #F8F9FC; border: 1px solid #EAECF0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 700; color: #344054; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;">
            <span>💰 Komponen Nilai Proyek (Financial Separation):</span>
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #175CD3;">
                Nominal Jasa Agensi / Fee Manajemen (Rp) *
              </label>
              <div class="input-group-currency">
                <span class="currency-addon" style="background: #EFF8FF; color: #175CD3; font-weight: 700;">Rp</span>
                <input type="text" name="service_fee_amount" id="edit-service-fee" class="form-control rupiah-input" placeholder="0" oninput="recalcProjectSummary('edit')" required>
              </div>
              <span style="font-size: 10.5px; color: var(--text-muted); display: block; margin-top: 3px;">
                Pendapatan murni Kalamedia (dihitung sebagai laba kotor)
              </span>
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
              <label class="form-label" style="font-size: 11.5px; font-weight: 700; color: #027A48;">
                Saldo Ads Awal Klien (Rp)
              </label>
              <div class="input-group-currency">
                <span class="currency-addon" style="background: #ECFDF3; color: #027A48; font-weight: 700;">Rp</span>
                <input type="text" name="ads_budget_pool" id="edit-ads-budget" class="form-control rupiah-input" placeholder="0" oninput="recalcProjectSummary('edit')">
              </div>
              <span style="font-size: 10.5px; color: var(--text-muted); display: block; margin-top: 3px;">
                Titipan belanja iklan klien (bukan laba agensi)
              </span>
            </div>
          </div>

          <!-- Live Financial Summary Card -->
          <div id="edit-project-summary-box" style="background: #FFFFFF; border: 1px solid #D0D5DD; border-radius: 8px; padding: 12px; margin-top: 6px;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; text-align: center;">
              <div>
                <span style="font-size: 10.5px; color: var(--text-muted); font-weight: 600; text-transform: uppercase; display: block;">Total Tagihan Klien</span>
                <span id="edit-summary-total" style="font-size: 14px; font-weight: 800; color: #101828;">Rp 0</span>
              </div>
              <div style="border-left: 1px solid #EAECF0; border-right: 1px solid #EAECF0;">
                <span style="font-size: 10.5px; color: #175CD3; font-weight: 600; text-transform: uppercase; display: block;">Porsi Jasa Agensi</span>
                <span id="edit-summary-service" style="font-size: 14px; font-weight: 800; color: #175CD3;">Rp 0 (0%)</span>
              </div>
              <div>
                <span style="font-size: 10.5px; color: #027A48; font-weight: 600; text-transform: uppercase; display: block;">Porsi Saldo Ads</span>
                <span id="edit-summary-ads" style="font-size: 14px; font-weight: 800; color: #027A48;">Rp 0 (0%)</span>
              </div>
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Target Margin Profit (%)</label>
            <input type="number" name="target_margin_percent" id="edit-project-target-margin" class="form-control" value="30.00" min="0" max="100" step="0.5">
          </div>
          <div class="form-group">
            <label class="form-label">Status Proyek</label>
            <select name="status" id="edit-project-status" class="form-select">
              <option value="Planning">Planning</option>
              <option value="In Progress">In Progress</option>
              <option value="Completed">Completed</option>
              <option value="On Hold">On Hold</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Mulai</label>
            <input type="date" name="start_date" id="edit-project-start-date" class="form-control">
          </div>
          <div class="form-group">
            <label class="form-label">Target Selesai / Akhir Kontrak</label>
            <input type="date" name="end_date" id="edit-project-end-date" class="form-control">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-project')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-edit-project', () => {
    closeModal('modal-edit-project');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 8d. Modal Tambah / Top-Up Saldo Ads Proyek Sewaktu-Waktu -->
<div id="modal-topup-project-ads" class="modal-backdrop">
  <div class="modal-dialog" style="max-width: 540px;">
    <div class="modal-header">
      <div>
        <h3 class="modal-title">+ Tambah Saldo Ads Klien</h3>
        <span style="font-size: 12px; color: var(--text-secondary);">Top-Up Saldo Iklan Klien di Tengah Masa Kontrak Aktif</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-topup-project-ads')">&times;</button>
    </div>
    <form id="form-topup-project-ads" action="api/clients.php?action=topup_project_ads" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="project_id" id="topup-ads-project-id">
      <div class="modal-body">
        <!-- Project & Contract Info Banner -->
        <div style="background: #F8F9FC; border: 1px solid #EAECF0; border-radius: 12px; padding: 14px; margin-bottom: 16px;">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
            <div>
              <span style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #6E7485;">Proyek &amp; Klien</span>
              <div style="font-size: 15px; font-weight: 800; color: #101828; margin: 2px 0 2px;" id="topup-ads-project-name">-</div>
              <div style="font-size: 12px; color: #475467;" id="topup-ads-client-company">-</div>
            </div>
            <div style="text-align: right;">
              <span id="topup-ads-contract-badge" class="badge" style="background: #ECFDF3; color: #027A48; font-weight: 700; font-size: 11px;">Kontrak Aktif</span>
              <div style="font-size: 11px; color: var(--text-muted); margin-top: 3px;" id="topup-ads-contract-dates">-</div>
            </div>
          </div>
          <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed #CBD5E1; margin-top: 10px; padding-top: 8px; font-size: 12px;">
            <span style="color: var(--text-secondary);">Saldo Ads Proyek Saat Ini:</span>
            <span id="topup-ads-current-pool" style="font-weight: 800; color: #027A48;">Rp 0</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nominal Tambah Saldo (Rp) <span style="color:red;">*</span></label>
            <div class="input-group-currency">
              <span class="currency-addon" style="background: #ECFDF3; color: #027A48; font-weight: 700;">Rp</span>
              <input type="text" name="topup_amount" id="topup-ads-amount" class="form-control rupiah-input" placeholder="0" required>
            </div>
            <span style="font-size: 11px; color: var(--text-muted); margin-top: 3px; display: block;">
              Saldo ini otomatis menambah alokasi iklan tanpa mempengaruhi laba jasa agensi.
            </span>
          </div>

          <div class="form-group">
            <label class="form-label">Platform Iklan</label>
            <select name="platform" class="form-select">
              <option value="Meta Ads">Meta Ads (Facebook &amp; Instagram)</option>
              <option value="Google Ads">Google Ads</option>
              <option value="TikTok Ads">TikTok Ads</option>
              <option value="Semua Platform">Semua Platform (Pool Bersama)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Upload Bukti Transfer Dana Ads Klien (JPG/PNG/PDF)</label>
          <input type="file" name="receipt_file" class="form-control" accept="image/*,.pdf">
          <span style="font-size: 11px; color: var(--text-muted); margin-top: 3px; display: block;">
            Bukti transfer ini akan dapat dilihat transparan oleh Klien &amp; Tim Ads.
          </span>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan / Referensi Transfer</label>
          <input type="text" name="notes" class="form-control" placeholder="Contoh: Top-up tambahan kampanye Flash Sale 11.11 via BCA">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-topup-project-ads')">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-submit-topup-ads" style="background: #027A48; border-color: #027A48;">
          Simpan &amp; Tambah Saldo Ads
        </button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-topup-project-ads', () => {
    closeModal('modal-topup-project-ads');
    setTimeout(() => window.location.reload(), 800);
  });
});
</script>

<!-- 8e. Modal Preview Bukti Transfer Saldo Ads (Universal Lightbox) -->
<div id="modal-view-transfer-proof" class="modal-backdrop">
  <div class="modal-dialog" style="max-width: 600px;">
    <div class="modal-header">
      <div>
        <h4 class="modal-title" id="transfer-proof-modal-title">Bukti Transfer Saldo Ads</h4>
        <span style="font-size: 12px; color: var(--text-secondary);" id="transfer-proof-modal-subtitle">Lampiran Transaksi Resmi</span>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-view-transfer-proof')">&times;</button>
    </div>
    <div class="modal-body" style="text-align: center; padding: 20px;">
      <div id="transfer-proof-media-container" style="min-height: 220px; display: flex; align-items: center; justify-content: center; background: #F8F9FC; border: 1px solid #EAECF0; border-radius: 12px; overflow: hidden;">
        <span style="color: var(--text-muted); font-size: 13px;">Memuat lampiran...</span>
      </div>
    </div>
    <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
      <a id="transfer-proof-download-link" href="#" target="_blank" download class="btn btn-secondary btn-sm" style="font-weight: 700; gap: 6px; display: inline-flex; align-items: center;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span>Buka / Unduh Dokumen Asli</span>
      </a>
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-view-transfer-proof')">Tutup</button>
    </div>
  </div>
</div>
<script>
function openUniversalTransferProofModal(fileUrl, title, subtitle) {
  if (!fileUrl) {
    alert('Lampiran bukti transfer tidak ditemukan.');
    return;
  }
  const titleEl = document.getElementById('transfer-proof-modal-title');
  const subEl = document.getElementById('transfer-proof-modal-subtitle');
  const container = document.getElementById('transfer-proof-media-container');
  const dlLink = document.getElementById('transfer-proof-download-link');

  if (titleEl) titleEl.innerText = title || 'Bukti Transfer Saldo Ads';
  if (subEl) subEl.innerText = subtitle || 'Lampiran Transaksi Resmi';
  if (dlLink) dlLink.href = fileUrl;

  const isPdf = fileUrl.toLowerCase().endsWith('.pdf');
  if (isPdf) {
    container.innerHTML = `
      <div style="padding: 30px; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 12px;">📄</div>
        <div style="font-size: 14px; font-weight: 700; color: #101828; margin-bottom: 6px;">Dokumen Bukti Transfer Berformat PDF</div>
        <p style="font-size: 12.5px; color: var(--text-secondary); margin-bottom: 16px;">Klik tombol di bawah untuk membuka atau mengunduh file PDF.</p>
        <a href="${fileUrl}" target="_blank" class="btn btn-primary btn-sm" style="font-weight: 700;">Buka Dokumen PDF di Tab Baru</a>
      </div>
    `;
  } else {
    container.innerHTML = `
      <img src="${fileUrl}" alt="Bukti Transfer" style="max-width: 100%; max-height: 520px; object-fit: contain; border-radius: 8px; display: block; margin: 0 auto;">
    `;
  }
  openModal('modal-view-transfer-proof');
}
</script>

<!-- 9. Modal Input Gaji Karyawan (Payroll Outflow) -->
<div id="modal-input-salary" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">+ Catat Penggajian Karyawan</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-input-salary')">&times;</button>
    </div>
    <form id="form-input-salary" action="api/salaries.php?action=create_salary" method="POST">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group" style="flex: 2;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label class="form-label" style="margin-bottom: 0;">Karyawan Terdaftar *</label>
              <a href="javascript:void(0)" onclick="openModal('modal-create-employee')" style="font-size: 11px; color: var(--primary); text-decoration: none;">+ Karyawan Baru</a>
            </div>
            <select name="employee_id" id="salary-employee-select" class="form-select" onchange="onSalaryEmployeeSelected(this)">
              <option value="">-- Pilih Karyawan --</option>
              <?php foreach ($allEmployees as $emp): ?>
                <option value="<?= $emp['id'] ?>"
                        data-name="<?= htmlspecialchars($emp['name']) ?>"
                        data-position="<?= htmlspecialchars($emp['position']) ?>"
                        data-bank="<?= htmlspecialchars($emp['bank_name']) ?>"
                        data-account="<?= htmlspecialchars($emp['bank_account']) ?>"
                        data-salary="<?= $emp['base_salary'] ?>">
                  <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>) - Default <?= format_rupiah($emp['base_salary']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Periode Penggajian *</label>
            <input type="month" name="month_period" id="salary-month-period" class="form-control" value="<?= date('Y-m') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="employee_name" id="salary-emp-name" class="form-control" placeholder="Nama karyawan" required>
          </div>
          <div class="form-group">
            <label class="form-label">Jabatan *</label>
            <input type="text" name="employee_position" id="salary-emp-position" class="form-control" placeholder="Misal: Graphic Designer" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bank Pembayaran</label>
            <input type="text" name="bank_name" id="salary-emp-bank" class="form-control" placeholder="Contoh: BCA" value="BCA">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Rekening</label>
            <input type="text" name="bank_account" id="salary-emp-account" class="form-control" placeholder="Nomor rekening tujuan">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Pembayaran *</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>

        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 16px 0;">

        <div style="font-size: 13px; font-weight: 700; color: #FFF; margin-bottom: 12px;">Rincian Komponen Gaji</div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Gaji Pokok (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="base_salary" id="salary-base-input" class="form-control rupiah-input" placeholder="0" oninput="calculateSalaryNet()" required>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Tunjangan & Bonus (Rp)</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="allowance" id="salary-allowance-input" class="form-control rupiah-input" placeholder="0" value="0" oninput="calculateSalaryNet()">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Potongan Kasbon & BPJS (Rp)</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="deduction" id="salary-deduction-input" class="form-control rupiah-input" placeholder="0" value="0" oninput="calculateSalaryNet()">
            </div>
          </div>
        </div>

        <!-- Net Salary Display Summary -->
        <div style="background: #F9FAFB; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px 18px; margin: 16px 0; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <div style="font-size: 11px; color: var(--text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Gaji Bersih (Take-Home Pay)</div>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">= Gaji Pokok + Tunjangan - Potongan</div>
          </div>
          <div id="salary-display-net" style="font-size: 22px; font-weight: 800; color: #101828;">
            Rp 0
          </div>
        </div>

        <div class="form-row">
          <div class="form-group" style="flex: 1;">
            <label class="form-label">Status Pembayaran</label>
            <select name="status" class="form-select">
              <option value="Pending">Pending (Menunggu Transfer)</option>
              <option value="Paid">Paid (Sudah Ditransfer)</option>
            </select>
          </div>
          <div class="form-group" style="flex: 2;">
            <label class="form-label">Catatan Penggajian</label>
            <input type="text" name="notes" class="form-control" placeholder="Contoh: Penggajian reguler bulanan">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-input-salary')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Penggajian</button>
      </div>
    </form>
  </div>
</div>

<!-- 10. Modal Tambah Karyawan Baru -->
<div id="modal-create-employee" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">+ Tambah Data Karyawan</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-create-employee')">&times;</button>
    </div>
    <form id="form-create-employee" action="api/salaries.php?action=create_employee" method="POST">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="name" class="form-control" placeholder="Nama lengkap karyawan" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jabatan *</label>
            <input type="text" name="position" class="form-control" placeholder="Misal: Graphic Designer" required>
          </div>
          <div class="form-group">
            <label class="form-label">Departemen</label>
            <input type="text" name="department" class="form-control" placeholder="Creative & Production" value="Creative & Production">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Status Kepegawaian</label>
            <select name="employment_type" class="form-select">
              <option value="Full-time" selected>Tetap (Full-time)</option>
              <option value="Contract">Kontrak (PKWT)</option>
              <option value="Part-time">Paruh Waktu (Part-time)</option>
              <option value="Intern">Magang (Internship)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Gaji Pokok (Rp) *</label>
            <div class="input-group-currency">
              <span class="currency-addon">Rp</span>
              <input type="text" name="base_salary" class="form-control rupiah-input" placeholder="0" required>
            </div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Karyawan</label>
            <input type="email" name="email" class="form-control" placeholder="nama@kalamedia.id">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" name="phone" class="form-control" placeholder="08123456789">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bank Penerima</label>
            <input type="text" name="bank_name" class="form-control" value="BCA">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Rekening</label>
            <input type="text" name="bank_account" class="form-control" placeholder="Nomor rekening">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-create-employee')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
      </div>
    </form>
  </div>
</div>

<!-- 10b. Modal Edit Data Karyawan -->
<div id="modal-edit-employee" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">✏️ Edit Data Karyawan</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-edit-employee')">&times;</button>
    </div>
    <form id="form-edit-employee" action="api/salaries.php?action=update_employee" method="POST">
      <input type="hidden" name="id" id="edit-emp-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="name" id="edit-emp-name" class="form-control" placeholder="Nama lengkap karyawan" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Jabatan *</label>
            <input type="text" name="position" id="edit-emp-position" class="form-control" placeholder="Misal: Graphic Designer" required>
          </div>
          <div class="form-group">
            <label class="form-label">Departemen</label>
            <input type="text" name="department" id="edit-emp-department" class="form-control" placeholder="Creative & Production">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Status Kepegawaian</label>
            <select name="employment_type" id="edit-emp-employment-type" class="form-select">
              <option value="Full-time">Tetap (Full-time)</option>
              <option value="Contract">Kontrak (PKWT)</option>
              <option value="Part-time">Paruh Waktu (Part-time)</option>
              <option value="Freelance">Freelance</option>
              <option value="Intern">Magang (Internship)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status Akun / Karyawan</label>
            <select name="status" id="edit-emp-status" class="form-select">
              <option value="Active">Active (Aktif)</option>
              <option value="Inactive">Inactive (Non-aktif)</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Karyawan</label>
            <input type="email" name="email" id="edit-emp-email" class="form-control" placeholder="nama@kalamedia.id">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor WhatsApp</label>
            <input type="text" name="phone" id="edit-emp-phone" class="form-control" placeholder="08123456789">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Bank Penerima</label>
            <input type="text" name="bank_name" id="edit-emp-bank-name" class="form-control" placeholder="BCA / Mandiri / Bank Jago / Seabank">
          </div>
          <div class="form-group">
            <label class="form-label">Nomor Rekening</label>
            <input type="text" name="bank_account" id="edit-emp-bank-account" class="form-control" placeholder="Nomor rekening">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Gaji Pokok Default (Rp) *</label>
          <div class="input-group-currency">
            <span class="currency-addon">Rp</span>
            <input type="text" name="base_salary" id="edit-emp-base-salary" class="form-control rupiah-input" placeholder="0" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit-employee')">Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
      </div>
    </form>
  </div>
</div>

<!-- 11. Modal Preview & Cetak Slip Gaji Resmi -->
<div id="modal-slip-gaji" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Slip Gaji Karyawan - PT Kala Kausa Prima</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-slip-gaji')">&times;</button>
    </div>
    <div class="modal-body" id="slip-print-area" style="background: #FFFFFF; color: #000000; border-radius: 8px; padding: 36px 40px; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;">
      
      <!-- Top To / Date Bar -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Karyawan:</div>
          <div style="font-size: 16px; font-weight: 700; color: #000000;" id="slip-emp-name">-</div>
          <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
            <span id="slip-emp-position">-</span> &bull; <span id="slip-emp-dept">Creative & Production</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 13px; color: #000000; margin-bottom: 3px;" id="slip-display-period">Periode: -</div>
          <div style="font-size: 13px; font-weight: 700; color: #000000; letter-spacing: 0.5px;" id="slip-display-date">Tanggal Bayar: -</div>
        </div>
      </div>

      <!-- Main Header: Logo & Title -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
        <div style="width: 250px;">
          <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 85px; width: auto; max-width: 130px; object-fit: contain; margin-bottom: 12px; display: block;">
          <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
            Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,<br>
            Kel. Cisauk, Tangerang,<br>
            Banten 15341
          </div>
        </div>

        <div style="text-align: right;">
          <div style="font-size: 48px; font-weight: 900; color: #000000; letter-spacing: 3px; line-height: 1; margin-bottom: 10px; text-transform: uppercase;">
            SLIP GAJI
          </div>
          <div style="font-size: 12.5px; color: #000000;">
            Status: <span id="slip-emp-status" style="font-weight: 700; color: #10B981;">LUNAS (PAID)</span>
          </div>
        </div>
      </div>

      <!-- Employee Info Summary Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #ECEEF5; border-radius: 14px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 120px; display: inline-block;">Status Kepegawaian:</span> <strong style="color: #000000;" id="slip-emp-type">Full-time</strong></div>
          <div><span style="color: #6E7485; width: 120px; display: inline-block;">Departemen:</span> <span id="slip-emp-dept-2">Creative & Production</span></div>
        </div>
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 120px; display: inline-block;">Rekening Transfer:</span> <strong style="color: #000000;" id="slip-emp-bank">-</strong></div>
          <div><span style="color: #6E7485; width: 120px; display: inline-block;">Metode:</span> <span>Bank Transfer Otomatis</span></div>
        </div>
      </div>

      <!-- Rincian Penerimaan & Potongan Tables (2 Columns) -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px;">
        <!-- Column A: Penerimaan -->
        <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden;">
          <div style="background: #F9FAFB; padding: 10px 14px; font-weight: 700; font-size: 13px; color: #000000; border-bottom: 1px solid #E5E7EB; letter-spacing: 0.3px;">
            A. PENERIMAAN (EARNINGS)
          </div>
          <div style="padding: 14px; font-size: 12.5px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
              <span style="color: #374151;">Gaji Pokok:</span>
              <strong style="color: #000000;" id="slip-base-val">Rp 0</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
              <span style="color: #374151;">Tunjangan & Bonus:</span>
              <strong style="color: #000000;" id="slip-allowance-val">Rp 0</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0 4px; font-weight: 800; font-size: 13px; color: #000000;">
              <span>Total Penerimaan:</span>
              <span id="slip-total-earnings">Rp 0</span>
            </div>
          </div>
        </div>

        <!-- Column B: Potongan -->
        <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden;">
          <div style="background: #F9FAFB; padding: 10px 14px; font-weight: 700; font-size: 13px; color: #000000; border-bottom: 1px solid #E5E7EB; letter-spacing: 0.3px;">
            B. POTONGAN (DEDUCTIONS)
          </div>
          <div style="padding: 14px; font-size: 12.5px;">
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
              <span style="color: #374151;">Potongan Kasbon & BPJS:</span>
              <strong style="color: #EF4444;" id="slip-deduction-val">Rp 0</strong>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #F3F4F6;">
              <span style="color: #374151;">PPh 21:</span>
              <span style="color: #6B7280;">Rp 0 (Ditanggung Agensi)</span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 10px 0 4px; font-weight: 800; font-size: 13px; color: #EF4444;">
              <span>Total Potongan:</span>
              <span id="slip-total-deductions">Rp 0</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Take Home Pay Highlight -->
      <div style="background: #F9FAFB; border: 1.5px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
            GAJI BERSIH (TAKE-HOME PAY)
          </div>
          <div style="font-size: 11px; color: #4B5563; margin-top: 3px;" id="slip-notes-display">
            Catatan: Pembayaran gaji bulanan PT Kala Kausa Prima.
          </div>
        </div>
        <div style="font-size: 26px; font-weight: 900; color: #000000; letter-spacing: 0.5px;" id="slip-net-val">
          Rp 0
        </div>
      </div>

      <!-- Signatures Footer -->
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
        <div style="width: 200px;">
          <div style="color: #4B5563; margin-bottom: 50px;">Penerima,</div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;" id="slip-sign-emp">(Nama Karyawan)</div>
        </div>
        <div style="width: 220px;">
          <div style="color: #4B5563; margin-bottom: 4px;">Marketing Manager,</div>
          <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
            <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 44px; max-width: 140px; object-fit: contain;">
          </div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Ilham Lanang</div>
        </div>
      </div>

      <!-- Bottom Tagline & Brand Links -->
      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 12px; margin-top: 28px; font-size: 10.5px; color: #6B7280;">
        <div style="font-weight: 700; color: #000000;">Built for What’s Next</div>
        <div style="display: flex; gap: 16px;">
          <span><?= AGENCY_INSTAGRAM ?></span>
          <span><?= AGENCY_EMAIL ?></span>
          <span><?= AGENCY_WEBSITE ?></span>
        </div>
      </div>

    </div>

    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-slip-gaji')">Tutup</button>
      <button type="button" class="btn btn-secondary" onclick="printSlipGaji()" title="Cetak langsung ke printer">
        <span>Cetak</span>
      </button>
      <button type="button" class="btn btn-secondary" onclick="copySlipGajiLink()" title="Salin link slip gaji ke clipboard">
        <span>Salin Link</span>
      </button>
      <button type="button" class="btn btn-primary" id="btn-download-slip-pdf" onclick="downloadSlipPdf()">
        <span>Unduh PDF</span>
      </button>
      <button type="button" class="btn" onclick="shareSlipGajiWa()" title="Kirim rincian slip gaji ke WhatsApp Karyawan" style="background: #25D366; color: #FFFFFF; border: none; font-weight: 700;">
        <span>Share ke WA</span>
      </button>
    </div>
  </div>
</div>

<!-- 11b. Modal Preview & Cetak Voucher Pembayaran / Invoice Fee Freelancer -->
<div id="modal-freelancer-voucher" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Bukti Pembayaran &amp; Invoice Fee Freelancer</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-freelancer-voucher')">&times;</button>
    </div>
    <div class="modal-body" id="freelancer-voucher-print-area" style="background: #FFFFFF; color: #000000; border-radius: 8px; padding: 36px 40px; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;">
      
      <!-- Top To / Date Bar -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Penerima Honor (Talenta / Freelancer):</div>
          <div style="font-size: 16px; font-weight: 700; color: #000000;" id="vch-freelancer-name">-</div>
          <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
            <span id="vch-freelancer-bank">-</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 13px; font-weight: 800; color: #000000; margin-bottom: 3px;" id="vch-display-number">VOUCHER: -</div>
          <div style="font-size: 12.5px; color: #4B5563;" id="vch-display-date">Tanggal Bayar: -</div>
        </div>
      </div>

      <!-- Main Header: Logo & Title -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
        <div style="width: 260px;">
          <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 85px; width: auto; max-width: 130px; object-fit: contain; margin-bottom: 12px; display: block;">
          <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
            Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,<br>
            Kel. Cisauk, Tangerang, Banten 15341 &bull; <?= AGENCY_EMAIL ?>
          </div>
        </div>

        <div style="text-align: right;">
          <div style="font-size: 32px; font-weight: 900; color: #000000; letter-spacing: 1.5px; line-height: 1.1; margin-bottom: 8px; text-transform: uppercase;">
            PAYMENT VOUCHER
          </div>
          <div style="font-size: 12px; font-weight: 800; color: #4F46E5; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px;">
            BUKTI PEMBAYARAN FEE FREELANCER
          </div>
          <div style="font-size: 12px; color: #000000;">
            Status: <span id="vch-status" style="font-weight: 700; color: #10B981;">LUNAS (PAID)</span>
          </div>
        </div>
      </div>

      <!-- Project & Client Metadata -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #ECEEF5; border-radius: 14px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 110px; display: inline-block;">Proyek Terkait:</span> <strong style="color: #000000;" id="vch-project-name">-</strong></div>
          <div><span style="color: #6E7485; width: 110px; display: inline-block;">Klien / Brand:</span> <span id="vch-client-company" style="font-weight: 600; color: #000000;">-</span></div>
        </div>
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 110px; display: inline-block;">Metode Bayar:</span> <strong style="color: #000000;">Bank Transfer</strong></div>
          <div><span style="color: #6E7485; width: 110px; display: inline-block;">Akun Rekening:</span> <span id="vch-account-info" style="font-weight: 600; color: #000000;">-</span></div>
        </div>
      </div>

      <!-- Deliverables & Task Description Table -->
      <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
          <thead>
            <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB; text-align: left;">
              <th style="padding: 10px 14px; font-weight: 700; color: #000000; width: 65%;">URAIAN JASA / TUGAS PEKERJAAN</th>
              <th style="padding: 10px 14px; font-weight: 700; color: #000000; text-align: right; width: 35%;">NOMINAL HONOR (RP)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding: 16px 14px; color: #374151; vertical-align: top;">
                <div style="font-weight: 700; color: #101828; margin-bottom: 4px;" id="vch-task-title">Jasa Pekerjaan Freelance</div>
                <div style="font-size: 11.5px; color: #6B7280; line-height: 1.4;" id="vch-task-desc">-</div>
              </td>
              <td style="padding: 16px 14px; font-weight: 800; font-size: 14px; color: #101828; text-align: right; vertical-align: top;" id="vch-amount-col">
                Rp 0
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Total Paid Highlight -->
      <div style="background: #F9FAFB; border: 1.5px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
            TOTAL DIBAYARKAN (NET PAYOUT)
          </div>
          <div style="font-size: 11px; color: #4B5563; margin-top: 3px;">
            Pembayaran telah ditransfer penuh ke rekening talenta terkait.
          </div>
        </div>
        <div style="font-size: 26px; font-weight: 900; color: #000000; letter-spacing: 0.5px;" id="vch-total-amount">
          Rp 0
        </div>
      </div>

      <!-- Signatures Footer -->
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
        <div style="width: 180px;">
          <div style="color: #4B5563; margin-bottom: 50px;">Penerima Honor,</div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;" id="vch-sign-freelancer">(Nama Freelancer)</div>
        </div>
        <div style="width: 190px;">
          <div style="color: #4B5563; margin-bottom: 4px;">Disiapkan oleh,</div>
          <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
            <img src="assets/Jpg/ttd-fadhli.png" alt="TTD Muhammad Fadhli" style="height: 42px; max-width: 120px; object-fit: contain;">
          </div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Muhammad Fadhli</div>
          <div style="font-size: 10px; color: #6B7280;">Creative Manager</div>
        </div>
        <div style="width: 190px;">
          <div style="color: #4B5563; margin-bottom: 4px;">Disetujui oleh,</div>
          <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
            <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 42px; max-width: 120px; object-fit: contain;">
          </div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Ilham Lanang</div>
          <div style="font-size: 10px; color: #6B7280;">Marketing Manager</div>
        </div>
      </div>

      <!-- Bottom Tagline & Brand Links -->
      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 12px; margin-top: 28px; font-size: 10.5px; color: #6B7280;">
        <div style="font-weight: 700; color: #000000;">Built for What’s Next</div>
        <div style="display: flex; gap: 16px;">
          <span><?= AGENCY_INSTAGRAM ?></span>
          <span><?= AGENCY_EMAIL ?></span>
          <span><?= AGENCY_WEBSITE ?></span>
        </div>
      </div>

    </div>

    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-freelancer-voucher')">Tutup</button>
      <button type="button" class="btn btn-secondary" onclick="printFreelancerVoucher()" title="Cetak langsung ke printer">
        <span>Cetak Voucher</span>
      </button>
      <button type="button" class="btn btn-secondary" onclick="copyFreelancerVoucherLink()" title="Salin link voucher fee ke clipboard">
        <span>Salin Link</span>
      </button>
      <button type="button" class="btn btn-primary" onclick="downloadFreelancerVoucherPdf()">
        <span>Unduh PDF</span>
      </button>
      <button type="button" class="btn" onclick="shareFreelancerVoucherWa()" title="Kirim bukti pembayaran ke WhatsApp Talenta" style="background: #25D366; color: #FFFFFF; border: none; font-weight: 700;">
        <span>Share ke WA</span>
      </button>
    </div>
  </div>
</div>

<!-- 11c. Modal Preview & Cetak Voucher / Invoice Top-Up Ads Resmi -->
<div id="modal-ads-voucher" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <h3 class="modal-title">Bukti Transaksi &amp; Invoice Top-Up Saldo Iklan</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-ads-voucher')">&times;</button>
    </div>
    <div class="modal-body" id="ads-voucher-print-area" style="background: #FFFFFF; color: #000000; border-radius: 8px; padding: 36px 40px; font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, Arial, sans-serif;">
      
      <!-- Top To / Date Bar -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 12px; border-bottom: 1.5px solid #000000; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 500; color: #000000; margin-bottom: 3px;">Klien / Brand Pengiklan:</div>
          <div style="font-size: 17px; font-weight: 700; color: #000000;" id="vch-ads-client-company">-</div>
          <div style="font-size: 12px; color: #4B5563; margin-top: 2px;">
            PIC: <span id="vch-ads-client-pic">-</span> &bull; <span id="vch-ads-project-name">Proyek</span>
          </div>
        </div>
        <div style="text-align: right;">
          <div style="font-size: 13px; font-weight: 800; color: #000000; margin-bottom: 3px;" id="vch-ads-number">VOUCHER: -</div>
          <div style="font-size: 12.5px; color: #4B5563;" id="vch-ads-date">Tanggal Top-Up: -</div>
        </div>
      </div>

      <!-- Main Header: Logo & Title -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 28px;">
        <div style="width: 260px;">
          <img src="assets/img/Logo-1.webp" alt="PT Kala Kausa Prima" style="height: 85px; width: auto; max-width: 130px; object-fit: contain; margin-bottom: 12px; display: block;">
          <div style="font-size: 10.5px; line-height: 1.35; color: #6B7280;">
            Jl. Padat Karya, Cisauk Girang, RT 001/RW 005,<br>
            Kel. Cisauk, Tangerang, Banten 15341 &bull; <?= AGENCY_EMAIL ?>
          </div>
        </div>

        <div style="text-align: right;">
          <div style="font-size: 28px; font-weight: 900; color: #000000; letter-spacing: 1.5px; line-height: 1.1; margin-bottom: 6px; text-transform: uppercase;">
            ADS TOP-UP VOUCHER
          </div>
          <div style="font-size: 12px; font-weight: 800; color: #2563EB; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 4px;">
            BUKTI TRANSAKSI PENGELUARAN IKLAN DIGITAL
          </div>
          <div style="font-size: 12px; color: #000000;">
            Status Transaksi: <span style="font-weight: 700; color: #10B981;">BERHASIL (PAID / SUCCESS)</span>
          </div>
        </div>
      </div>

      <!-- Ads Account Metadata Grid -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; background: #F8FAFC; border: 1px solid #ECEEF5; border-radius: 14px; padding: 14px 18px; margin-bottom: 24px; font-size: 12px;">
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 110px; display: inline-block;">Platform Iklan:</span> <strong style="color: #000000;" id="vch-ads-platform">Meta Ads</strong></div>
          <div><span style="color: #6E7485; width: 110px; display: inline-block;">ID Akun Iklan:</span> <span id="vch-ads-account-id" style="font-weight: 600; color: #000000;">-</span></div>
        </div>
        <div>
          <div style="margin-bottom: 6px;"><span style="color: #6E7485; width: 110px; display: inline-block;">Kategori Biaya:</span> <strong style="color: #000000;">Media Spend Outflow</strong></div>
          <div><span style="color: #6E7485; width: 110px; display: inline-block;">Akun Pengiklan:</span> <span style="font-weight: 600; color: #000000;">PT Kala Kausa Prima Business Manager</span></div>
        </div>
      </div>

      <!-- Deliverables & Task Description Table -->
      <div style="border: 1px solid #E5E7EB; border-radius: 6px; overflow: hidden; margin-bottom: 24px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
          <thead>
            <tr style="background: #F9FAFB; border-bottom: 1px solid #E5E7EB; text-align: left;">
              <th style="padding: 10px 14px; font-weight: 700; color: #000000; width: 65%;">RINCIAN PENGALOKASIAN ANGGARAN IKLAN</th>
              <th style="padding: 10px 14px; font-weight: 700; color: #000000; text-align: right; width: 35%;">NOMINAL TOP-UP (RP)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="padding: 16px 14px; color: #374151; vertical-align: top;">
                <div style="font-weight: 700; color: #101828; margin-bottom: 4px;" id="vch-ads-item-title">Top-Up Saldo Iklan</div>
                <div style="font-size: 11.5px; color: #6B7280; line-height: 1.4;" id="vch-ads-item-desc">
                  Keterangan campaign promosi.
                </div>
              </td>
              <td style="padding: 16px 14px; font-weight: 800; font-size: 14px; color: #101828; text-align: right; vertical-align: top;" id="vch-ads-amount-col">
                Rp 0
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Total Paid Highlight -->
      <div style="background: #F9FAFB; border: 1.5px solid #000000; border-radius: 6px; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
          <div style="font-size: 13px; font-weight: 800; color: #000000; letter-spacing: 0.5px; text-transform: uppercase;">
            TOTAL TOP-UP ANGGARAN IKLAN
          </div>
          <div style="font-size: 11px; color: #4B5563; margin-top: 3px;">
            Saldo iklan telah berhasil dialokasikan langsung ke akun iklan resmi.
          </div>
        </div>
        <div style="font-size: 24px; font-weight: 900; color: #000000; letter-spacing: 0.5px;" id="vch-ads-total-amount">
          Rp 0
        </div>
      </div>

      <!-- Signatures Footer -->
      <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 36px; padding-top: 10px; font-size: 12px; text-align: center;">
        <div style="width: 190px;">
          <div style="color: #4B5563; margin-bottom: 4px;">Disiapkan oleh,</div>
          <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
            <img src="assets/Jpg/ttd-fadhli.png" alt="TTD Muhammad Fadhli" style="height: 42px; max-width: 120px; object-fit: contain;">
          </div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Muhammad Fadhli</div>
          <div style="font-size: 10px; color: #6B7280;">Creative Manager</div>
        </div>
        <div style="width: 190px;">
          <div style="color: #4B5563; margin-bottom: 4px;">Disetujui oleh,</div>
          <div style="height: 48px; display: flex; align-items: center; justify-content: center; margin-bottom: 2px;">
            <img src="assets/Jpg/ttd-ilham.png" alt="TTD Ilham Lanang" style="height: 42px; max-width: 120px; object-fit: contain;">
          </div>
          <div style="font-weight: 700; color: #000000; border-top: 1px solid #000000; padding-top: 4px;">Ilham Lanang</div>
          <div style="font-size: 10px; color: #6B7280;">Marketing Manager</div>
        </div>
      </div>

      <!-- Bottom Tagline & Brand Links -->
      <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #E5E7EB; padding-top: 12px; margin-top: 28px; font-size: 10.5px; color: #6B7280;">
        <div style="font-weight: 700; color: #000000;">Built for What’s Next</div>
        <div style="display: flex; gap: 16px;">
          <span><?= AGENCY_INSTAGRAM ?></span>
          <span><?= AGENCY_EMAIL ?></span>
          <span><?= AGENCY_WEBSITE ?></span>
        </div>
      </div>

    </div>

    <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-ads-voucher')">Tutup</button>
      <button type="button" class="btn btn-secondary" onclick="printAdsVoucher()" title="Cetak langsung ke printer">
        <span>Cetak Voucher</span>
      </button>
      <button type="button" class="btn btn-secondary" onclick="copyAdsVoucherLink()" title="Salin link voucher ads ke clipboard">
        <span>Salin Link</span>
      </button>
      <button type="button" class="btn btn-primary" onclick="downloadAdsVoucherPdf()">
        <span>Unduh PDF</span>
      </button>
      <button type="button" class="btn" onclick="shareAdsVoucherWa()" title="Kirim bukti top-up ads ke WhatsApp Klien" style="background: #25D366; color: #FFFFFF; border: none; font-weight: 700;">
        <span>Share ke WA</span>
      </button>
    </div>
  </div>
</div>

<!-- 12. Modal Tambah / Edit Jadwal Konten (Content Calendar) -->
<div id="modal-content-planner" class="modal-backdrop">
  <div class="modal-dialog modal-lg">
    <div class="modal-header">
      <div style="display: flex; align-items: center; gap: 8px;">
        <span id="modal-content-color-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #3B82F6; display: inline-block;"></span>
        <h3 class="modal-title" id="modal-content-title">+ Tambah Jadwal Konten</h3>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-content-planner')">&times;</button>
    </div>
    <form id="form-content-planner" onsubmit="event.preventDefault(); submitContentForm();">
      <input type="hidden" name="id" id="cp-id" value="">
      
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Judul Konten *</label>
          <input type="text" name="title" id="cp-title" class="form-control" placeholder="Contoh: Short Video Review Pasir Cor & Truk Armada" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Klien *</label>
            <select name="client_id" id="cp-client-id" class="form-select" onchange="loadProjectsForClient(this.value, 'cp-project-id')" required>
              <option value="">-- Pilih Klien --</option>
              <?php foreach ($allClients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Proyek</label>
            <select name="project_id" id="cp-project-id" class="form-select">
              <option value="">-- Pilih Proyek --</option>
              <?php foreach ($allProjects as $p): ?>
                <option value="<?= $p['id'] ?>" data-client="<?= $p['client_id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Platform *</label>
            <select name="platform" id="cp-platform" class="form-select" required>
              <option value="Instagram">Instagram</option>
              <option value="TikTok">TikTok</option>
              <option value="YouTube">YouTube</option>
              <option value="Meta Ads">Meta Ads (FB/IG)</option>
              <option value="LinkedIn">LinkedIn</option>
              <option value="Website">Website & Blog</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tipe Konten *</label>
            <select name="content_type" id="cp-content-type" class="form-select" required>
              <option value="Reels / Video">Short Video (Reels & TikTok)</option>
              <option value="Carousel">Carousel Feeds</option>
              <option value="Single Post">Single Image Post</option>
              <option value="Story">Story</option>
              <option value="Article / Copy">Artikel & Copywriting</option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal Publikasi *</label>
            <input type="date" name="publish_date" id="cp-publish-date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Waktu Publikasi</label>
            <input type="time" name="publish_time" id="cp-publish-time" class="form-control" value="10:00">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Status *</label>
            <select name="status" id="cp-status" class="form-select" required>
              <option value="Draft">Draft</option>
              <option value="Review">In Review</option>
              <option value="Approved">Approved</option>
              <option value="Scheduled">Scheduled</option>
              <option value="Published">Published</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Penanggung Jawab</label>
            <select name="assignee_id" id="cp-assignee-id" class="form-select">
              <option value="">-- Belum Ditugaskan --</option>
              <?php foreach ($allEmployees as $emp): ?>
                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['position']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tautan Aset Digital</label>
            <div style="display: flex; gap: 8px;">
              <input type="url" name="asset_url" id="cp-asset-url" class="form-control" placeholder="https://drive.google.com/... atau https://canva.com/..." oninput="updateAssetUrlButton(this.value)">
              <a href="#" id="cp-btn-open-asset" target="_blank" class="btn btn-secondary btn-sm" style="display: none; align-items: center; justify-content: center; padding: 0 12px; height: 38px; font-weight: 600;" title="Buka Tautan Aset">
                Buka
              </a>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Warna Label</label>
            <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px;">
              <input type="color" name="color_hex" id="cp-color-hex" value="#3B82F6" onchange="setModalContentColor(this.value)" style="width: 44px; height: 38px; padding: 2px; border: 1px solid #D0D5DD; border-radius: 6px; cursor: pointer;">
              <div style="display: flex; gap: 6px;" id="cp-preset-colors">
                <button type="button" class="color-preset-dot" style="background: #3B82F6;" onclick="setModalContentColor('#3B82F6')"></button>
                <button type="button" class="color-preset-dot" style="background: #EC4899;" onclick="setModalContentColor('#EC4899')"></button>
                <button type="button" class="color-preset-dot" style="background: #10B981;" onclick="setModalContentColor('#10B981')"></button>
                <button type="button" class="color-preset-dot" style="background: #F59E0B;" onclick="setModalContentColor('#F59E0B')"></button>
                <button type="button" class="color-preset-dot" style="background: #8B5CF6;" onclick="setModalContentColor('#8B5CF6')"></button>
                <button type="button" class="color-preset-dot" style="background: #06B6D4;" onclick="setModalContentColor('#06B6D4')"></button>
              </div>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Catatan Brief Produksi</label>
          <textarea name="notes" id="cp-notes" class="form-control" rows="3" placeholder="Tulis brief konsep, angle video, hook teks, atau instruksi produksi..."></textarea>
        </div>
      </div>

      <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
          <button type="button" id="cp-btn-delete" class="btn btn-secondary" style="color: #D92D20; border-color: #FDA29B; background: #FEF3F2; display: none;" onclick="deleteContentFromModal()">
            <span>Hapus Konten</span>
          </button>
        </div>
        <div style="display: flex; gap: 10px;">
          <button type="button" class="btn btn-secondary" onclick="closeModal('modal-content-planner')">Batal</button>
          <button type="submit" class="btn btn-primary" id="cp-btn-submit">
            <span>Simpan Konten</span>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- 14. Modal Export Jadwal Konten PDF dengan Filter Lengkap (Bulan / Custom Range) -->
<div id="modal-export-content-pdf" class="modal-backdrop">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 class="modal-title">Ekspor Jadwal Konten (PDF)</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-export-content-pdf')">&times;</button>
    </div>
    <form id="form-export-content-pdf" action="api/content.php" method="GET" target="_blank">
      <input type="hidden" name="action" value="export_pdf">
      <div class="modal-body">
        
        <!-- Pilihan Mode Rentang Waktu -->
        <div class="form-group">
          <label class="form-label">Rentang Waktu</label>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 4px;">
            <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #FFFFFF; border: 1.5px solid #2563EB; border-radius: 8px; cursor: pointer;" id="label-export-mode-month">
              <input type="radio" name="range_type" value="month" checked onchange="toggleExportRangeMode(this.value)" style="accent-color: #2563EB;">
              <span style="font-size: 13px; font-weight: 700; color: #101828;">Bulanan</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #FFFFFF; border: 1.5px solid #D0D5DD; border-radius: 8px; cursor: pointer;" id="label-export-mode-custom">
              <input type="radio" name="range_type" value="custom" onchange="toggleExportRangeMode(this.value)" style="accent-color: #2563EB;">
              <span style="font-size: 13px; font-weight: 700; color: #101828;">Kustom Tanggal</span>
            </label>
          </div>
        </div>

        <!-- Section 1: Month Picker -->
        <div class="form-group" id="export-month-wrap">
          <label class="form-label">Bulan *</label>
          <input type="month" name="month" id="export-month-input" class="form-control" value="<?= date('Y-m') ?>">
        </div>

        <!-- Section 2: Custom Date Range -->
        <div class="form-row" id="export-custom-date-wrap" style="display: none;">
          <div class="form-group">
            <label class="form-label">Tanggal Mulai *</label>
            <input type="date" name="start_date" id="export-start-date" class="form-control" value="<?= date('Y-m-01') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Selesai *</label>
            <input type="date" name="end_date" id="export-end-date" class="form-control" value="<?= date('Y-m-t') ?>">
          </div>
        </div>

        <!-- Filter Klien -->
        <div class="form-group">
          <label class="form-label">Klien</label>
          <select name="client_id" id="export-client-select" class="form-select">
            <option value="all">-- Semua Klien Agensi --</option>
            <?php foreach ($allClients as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company']) ?> (<?= htmlspecialchars($c['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Filter Platform & Status -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Platform</label>
            <select name="platform" class="form-select">
              <option value="all">-- Semua Platform --</option>
              <option value="Instagram">Instagram</option>
              <option value="TikTok">TikTok</option>
              <option value="YouTube">YouTube</option>
              <option value="Meta Ads">Meta Ads (FB/IG)</option>
              <option value="LinkedIn">LinkedIn</option>
              <option value="Website">Website & Blog</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="all">-- Semua Status --</option>
              <option value="Published">Published (Tayang)</option>
              <option value="Scheduled">Scheduled (Terjadwal)</option>
              <option value="Approved">Approved</option>
              <option value="Review">In Review</option>
              <option value="Draft">Draft</option>
            </select>
          </div>
        </div>

      </div>
      <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-export-content-pdf')">Batal</button>
        <button type="submit" class="btn btn-primary">
          <span>Unduh Laporan PDF</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- 13. Custom Destructive Confirmation Modal (Untitled UI Style) -->
<div id="modal-confirm-delete" class="modal-backdrop">
  <div class="modal-confirm-dialog">
    <h3 class="confirm-modal-title" id="confirm-modal-title">Hapus Data?</h3>
    <p class="confirm-modal-desc" id="confirm-modal-desc">
      Apakah Anda yakin ingin menghapus data ini? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.
    </p>
    <div class="confirm-modal-actions">
      <button type="button" class="btn-cancel-modal" onclick="closeModal('modal-confirm-delete')">
        Batal
      </button>
      <button type="button" class="btn-confirm-delete" id="btn-confirm-delete-action" onclick="executePendingDelete()">
        <span>Hapus</span>
      </button>
    </div>
  </div>
</div>

<!-- 14. Modal Detail Alokasi Keuangan & Profitabilitas Proyek/Klien -->
<div id="modal-project-financial-breakdown" class="modal-backdrop">
  <div class="modal-dialog modal-xl" style="max-width: 980px; max-height: 90vh; display: flex; flex-direction: column;">
    <div class="modal-header" style="padding: 18px 24px; border-bottom: 1px solid #EAECF0;">
      <div>
        <div style="display: flex; align-items: center; gap: 8px;">
          <h3 class="modal-title" style="font-size: 17px; font-weight: 800; color: #101828;">Detail Alokasi Keuangan & Profitabilitas</h3>
          <span id="pfb-badge-status" class="badge-status badge-paid">Active</span>
        </div>
        <p id="pfb-subtitle" style="font-size: 12.5px; color: var(--text-secondary); margin: 3px 0 0 0;">
          Memuat data alokasi pengeluaran dan profit proyek...
        </p>
      </div>
      <button type="button" class="modal-close" onclick="closeModal('modal-project-financial-breakdown')">&times;</button>
    </div>

    <div class="modal-body" style="padding: 24px; overflow-y: auto; flex: 1;">
      
      <!-- Top 4 KPI Metrics -->
      <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px;">
        <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 14px;">
          <div style="font-size: 11.5px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase;">Nilai Kontrak</div>
          <div id="pfb-contract-value" style="font-size: 18px; font-weight: 800; color: #101828; margin-top: 4px;">Rp0</div>
          <div id="pfb-invoiced-meta" style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Invoiced: Rp0</div>
        </div>

        <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; padding: 14px;">
          <div style="font-size: 11.5px; font-weight: 600; color: #991B1B; text-transform: uppercase;">Biaya Produksi</div>
          <div id="pfb-prod-cost" style="font-size: 18px; font-weight: 800; color: #B91C1C; margin-top: 4px;">Rp0</div>
          <div id="pfb-cost-breakdown-meta" style="font-size: 11px; color: #991B1B; margin-top: 2px;">Fee: Rp0 | Ads: Rp0</div>
        </div>

        <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 10px; padding: 14px;">
          <div style="font-size: 11.5px; font-weight: 600; color: #065F46; text-transform: uppercase;">Net Profit Bersih</div>
          <div id="pfb-net-profit" style="font-size: 18px; font-weight: 800; color: #047857; margin-top: 4px;">Rp0</div>
          <div id="pfb-profit-meta" style="font-size: 11px; color: #065F46; margin-top: 2px;">Keuntungan Riil Agensi</div>
        </div>

        <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 10px; padding: 14px;">
          <div style="font-size: 11.5px; font-weight: 600; color: #1E40AF; text-transform: uppercase;">Profit Margin (%)</div>
          <div id="pfb-profit-margin" style="font-size: 18px; font-weight: 800; color: #1D4ED8; margin-top: 4px;">0%</div>
          <div id="pfb-target-margin-meta" style="font-size: 11px; color: #1E40AF; margin-top: 2px;">Target: 40%</div>
        </div>
      </div>

      <!-- Financial Allocation Progress Bar -->
      <div style="background: #FFFFFF; border: 1px solid #EAECF0; border-radius: 10px; padding: 16px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
          <span style="font-size: 13px; font-weight: 700; color: #101828;">Alokasi Realisasi Pengeluaran & Profit</span>
          <span id="pfb-allocation-summary" style="font-size: 12px; color: var(--text-secondary);">Rincian Beban Biaya</span>
        </div>
        
        <!-- Multi-segment visual bar -->
        <div style="height: 12px; border-radius: 6px; background: #EAECF0; display: flex; overflow: hidden; margin-bottom: 12px;">
          <div id="pfb-bar-freelancer" style="width: 0%; background: #F59E0B; transition: width 0.4s ease;" title="Fee Freelancer"></div>
          <div id="pfb-bar-ads" style="width: 0%; background: #EF4444; transition: width 0.4s ease;" title="Biaya Iklan (Ads)"></div>
          <div id="pfb-bar-profit" style="width: 0%; background: #10B981; transition: width 0.4s ease;" title="Net Profit"></div>
        </div>

        <!-- Legend Items -->
        <div style="display: flex; gap: 18px; flex-wrap: wrap; font-size: 12px;">
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="width: 10px; height: 10px; border-radius: 3px; background: #F59E0B;"></span>
            <span style="color: var(--text-secondary);">Fee Freelancer:</span>
            <strong id="pfb-legend-freelancer" style="color: #101828;">Rp0 (0%)</strong>
          </div>
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="width: 10px; height: 10px; border-radius: 3px; background: #EF4444;"></span>
            <span style="color: var(--text-secondary);">Digital Ads:</span>
            <strong id="pfb-legend-ads" style="color: #101828;">Rp0 (0%)</strong>
          </div>
          <div style="display: flex; align-items: center; gap: 6px;">
            <span style="width: 10px; height: 10px; border-radius: 3px; background: #10B981;"></span>
            <span style="color: var(--text-secondary);">Laba Bersih:</span>
            <strong id="pfb-legend-profit" style="color: #101828;">Rp0 (0%)</strong>
          </div>
        </div>
      </div>

      <!-- Segmented Tabs for Deep Breakdown -->
      <div style="margin-bottom: 16px;">
        <div class="date-filter-group" style="width: 100%; display: flex; justify-content: flex-start;">
          <button type="button" class="filter-btn active" id="pfb-tab-btn-invoices" onclick="switchPfbTab('invoices')">💳 Invoice & Tagihan (<span id="pfb-count-invoices">0</span>)</button>
          <button type="button" class="filter-btn" id="pfb-tab-btn-freelancers" onclick="switchPfbTab('freelancers')">👨‍💻 Fee Freelancer (<span id="pfb-count-freelancers">0</span>)</button>
          <button type="button" class="filter-btn" id="pfb-tab-btn-ads" onclick="switchPfbTab('ads')">📢 Digital Ads (<span id="pfb-count-ads">0</span>)</button>
          <button type="button" class="filter-btn" id="pfb-tab-btn-contents" onclick="switchPfbTab('contents')">📅 Jadwal Konten (<span id="pfb-count-contents">0</span>)</button>
        </div>
      </div>

      <!-- Tab 1: Invoices -->
      <div id="pfb-pane-invoices" class="table-responsive" style="display: block;">
        <table class="table-custom" style="font-size: 12.5px;">
          <thead>
            <tr>
              <th>No. Invoice</th>
              <th>Tanggal Terbit</th>
              <th>Jatuh Tempo</th>
              <th>Nominal</th>
              <th>Status</th>
              <th style="text-align: right;">Aksi</th>
            </tr>
          </thead>
          <tbody id="pfb-table-invoices-body">
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 18px;">Memuat data invoice...</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Tab 2: Freelancers -->
      <div id="pfb-pane-freelancers" class="table-responsive" style="display: none;">
        <table class="table-custom" style="font-size: 12.5px;">
          <thead>
            <tr>
              <th>Nama Freelancer</th>
              <th>Uraian Pekerjaan</th>
              <th>Rekening Bank</th>
              <th>Nominal Fee</th>
              <th>Status</th>
              <th style="text-align: right;">Bukti Struk</th>
            </tr>
          </thead>
          <tbody id="pfb-table-freelancers-body">
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 18px;">Tidak ada data fee freelancer.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Tab 3: Ads Spend -->
      <div id="pfb-pane-ads" class="table-responsive" style="display: none;">
        <table class="table-custom" style="font-size: 12.5px;">
          <thead>
            <tr>
              <th>Platform & Akun</th>
              <th>Nama Campaign</th>
              <th>Tanggal Top-Up</th>
              <th>Nominal</th>
              <th style="text-align: right;">Bukti Struk</th>
            </tr>
          </thead>
          <tbody id="pfb-table-ads-body">
            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 18px;">Tidak ada data iklan digital.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Tab 4: Content Deliverables -->
      <div id="pfb-pane-contents" class="table-responsive" style="display: none;">
        <table class="table-custom" style="font-size: 12.5px;">
          <thead>
            <tr>
              <th>Judul Konten</th>
              <th>Platform & Format</th>
              <th>Tanggal Publikasi</th>
              <th>Penanggung Jawab</th>
              <th>Status</th>
              <th style="text-align: right;">Aset</th>
            </tr>
          </thead>
          <tbody id="pfb-table-contents-body">
            <tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 18px;">Tidak ada konten terencana.</td></tr>
          </tbody>
        </table>
      </div>

    </div>

    <div class="modal-footer" style="padding: 14px 24px; border-top: 1px solid #EAECF0; display: flex; justify-content: space-between; align-items: center;">
      <div style="font-size: 12px; color: var(--text-secondary);">
        Klik bukti transfer atau nomor invoice untuk melihat rincian dokumen terkait.
      </div>
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-project-financial-breakdown')">
        Tutup
      </button>
    </div>
  </div>
</div>

<!-- Modal Tambah Akun Pengguna (Karyawan, Freelance, Client, Admin) -->
<div id="modal-add-user" class="modal-backdrop">
  <div class="modal-dialog modal-md">
    <div class="modal-header">
      <h3 class="modal-title">+ Tambah Akun Pengguna Baru</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-add-user')">&times;</button>
    </div>
    <form id="form-add-user" onsubmit="event.preventDefault(); submitCreateUser('form-add-user');">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Peran Akun (Role) *</label>
          <select name="role" id="new-user-role" class="form-select" onchange="toggleUserRoleFields(this.value)" required>
            <option value="karyawan">Karyawan (In-House Creative &amp; Tech)</option>
            <option value="freelancer">Freelancer (Talenta Eksternal)</option>
            <option value="client">Klien (Client Portal)</option>
            <option value="admin">Admin / PM (Manajemen Operasional)</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label" id="label-user-name">Nama Lengkap *</label>
          <input type="text" name="name" id="new-user-name" class="form-control" placeholder="Contoh: Dimas Wahyudi atau Nabila Putri" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Alamat Email (Login) *</label>
            <input type="email" name="email" id="new-user-email" class="form-control" placeholder="nama@kalamedia.id" required>
          </div>
          <div class="form-group">
            <label class="form-label">Kata Sandi Awal *</label>
            <input type="password" name="password" id="new-user-password" class="form-control" value="password123" required>
            <small style="color: var(--text-muted); font-size: 11px;">Default: <code>password123</code></small>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Nomor Telepon / WhatsApp</label>
          <input type="text" name="phone_wa" id="new-user-phone" class="form-control" placeholder="0812xxxxxxxx">
        </div>

        <!-- Field Khusus Klien: Hubungkan ke Perusahaan Klien -->
        <div class="form-group" id="group-client-select" style="display: none; background: #F8F9FC; border: 1px solid #D0D5DD; padding: 12px 14px; border-radius: 8px;">
          <label class="form-label" style="font-weight: 700; color: #101828;">Pilih Perusahaan / Brand Klien *</label>
          <select name="client_id" id="new-user-client-id" class="form-select">
            <option value="">-- Hubungkan ke Perusahaan Klien --</option>
            <?php foreach ($allClients as $cl): ?>
              <option value="<?= $cl['id'] ?>"><?= htmlspecialchars($cl['company']) ?> (PIC: <?= htmlspecialchars($cl['name']) ?>)</option>
            <?php endforeach; ?>
          </select>
          <small style="color: var(--text-secondary); font-size: 11px; margin-top: 4px; display: block;">
            Pengguna dengan akun ini akan diarahkan langsung ke Client Portal brand yang dipilih saat login.
          </small>
        </div>

        <!-- Field Khusus Karyawan / Freelancer: Posisi -->
        <div class="form-group" id="group-position-input">
          <label class="form-label" id="label-user-position">Posisi / Spesialisasi</label>
          <input type="text" name="position" id="new-user-position" class="form-control" placeholder="Contoh: Graphic Designer, Video Editor, Copywriter">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add-user')">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-save-new-user">Simpan &amp; Buat Akun</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Reset Password Pengguna -->
<div id="modal-reset-user-password" class="modal-backdrop">
  <div class="modal-dialog modal-sm">
    <div class="modal-header">
      <h3 class="modal-title">Ganti Sandi Akun</h3>
      <button type="button" class="modal-close" onclick="closeModal('modal-reset-user-password')">&times;</button>
    </div>
    <form id="form-reset-user-password" onsubmit="event.preventDefault(); submitResetPassword('form-reset-user-password');">
      <input type="hidden" name="id" id="reset-user-id" value="">
      <div class="modal-body">
        <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 14px;">
          Atur ulang kata sandi baru untuk pengguna <strong id="reset-user-name" style="color: #101828;"></strong>:
        </p>
        <div class="form-group">
          <label class="form-label">Kata Sandi Baru *</label>
          <input type="password" name="password" id="reset-user-new-password" class="form-control" placeholder="Minimal 4 karakter" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-reset-user-password')">Batal</button>
        <button type="submit" class="btn btn-primary" id="btn-save-reset-pw">Simpan Sandi</button>
      </div>
    </form>
  </div>
</div>

<script>
// Auto fill employee details on select
function onSalaryEmployeeSelected(select) {
  const selected = select.options[select.selectedIndex];
  if (!selected || !selected.value) return;

  const name = selected.dataset.name || '';
  const position = selected.dataset.position || '';
  const bank = selected.dataset.bank || 'BCA';
  const account = selected.dataset.account || '';
  const salary = parseFloat(selected.dataset.salary) || 0;

  document.getElementById('salary-emp-name').value = name;
  document.getElementById('salary-emp-position').value = position;
  document.getElementById('salary-emp-bank').value = bank;
  document.getElementById('salary-emp-account').value = account;

  const baseInput = document.getElementById('salary-base-input');
  baseInput.value = formatRupiahDisplay(salary);
  calculateSalaryNet();
}

// Calculate Net Salary (Take Home Pay)
function calculateSalaryNet() {
  const base = unformatRupiah(document.getElementById('salary-base-input')?.value || 0);
  const allowance = unformatRupiah(document.getElementById('salary-allowance-input')?.value || 0);
  const deduction = unformatRupiah(document.getElementById('salary-deduction-input')?.value || 0);

  const net = Math.max(0, base + allowance - deduction);
  const display = document.getElementById('salary-display-net');
  if (display) {
    display.innerText = 'Rp ' + Number(net).toLocaleString('id-ID');
  }
}

let currentSlipSalaryId = 0;
window.currentSlipDataCache = null;

// Open Slip Gaji Modal & Load Data
async function openSlipGajiModal(salaryId) {
  currentSlipSalaryId = salaryId;
  try {
    const res = await fetch(`api/salaries.php?action=get_slip_data&id=${salaryId}`);
    const data = await res.json();
    if (!data.success || !data.salary) {
      showToast(data.message || 'Gagal memuat data slip gaji', 'danger');
      return;
    }

    window.currentSlipDataCache = data;

    const s = data.salary;
    const f = data.formatted;

    document.getElementById('slip-display-period').innerText = 'Periode: ' + (s.month_period || '-');
    document.getElementById('slip-display-date').innerText = 'Tanggal Bayar: ' + f.payment_date;
    document.getElementById('slip-emp-name').innerText = s.employee_name;
    document.getElementById('slip-emp-position').innerText = s.employee_position || '-';
    
    const dept = s.emp_dept || 'Creative & Production';
    const deptEl = document.getElementById('slip-emp-dept');
    if (deptEl) deptEl.innerText = dept;
    const deptEl2 = document.getElementById('slip-emp-dept-2');
    if (deptEl2) deptEl2.innerText = dept;

    const empTypeEl = document.getElementById('slip-emp-type');
    if (empTypeEl) empTypeEl.innerText = s.employment_type || 'Full-time';

    document.getElementById('slip-emp-bank').innerText = (s.bank_name || 'BCA') + ' - ' + (s.bank_account || '-');
    document.getElementById('slip-emp-status').innerText = s.status === 'Paid' ? 'LUNAS (PAID)' : 'PENDING (MENUNGGU TRANSFER)';
    document.getElementById('slip-emp-status').style.color = s.status === 'Paid' ? '#10B981' : '#F59E0B';

    document.getElementById('slip-base-val').innerText = f.base_salary;
    document.getElementById('slip-allowance-val').innerText = f.allowance;
    const totalEarnings = parseFloat(s.base_salary) + parseFloat(s.allowance);
    document.getElementById('slip-total-earnings').innerText = 'Rp ' + Number(totalEarnings).toLocaleString('id-ID');

    document.getElementById('slip-deduction-val').innerText = f.deduction;
    document.getElementById('slip-total-deductions').innerText = f.deduction;

    document.getElementById('slip-net-val').innerText = f.net_salary;
    document.getElementById('slip-notes-display').innerText = 'Catatan: ' + (s.notes || 'Pembayaran gaji bulanan PT Kala Kausa Prima.');
    document.getElementById('slip-sign-emp').innerText = s.employee_name;

    openModal('modal-slip-gaji');
  } catch (err) {
    showToast('Gagal memuat slip gaji', 'danger');
  }
}

// Helper to make IP-based URLs clickable in WhatsApp Web/Mobile
window.formatWaClickableUrl = function(rawUrl) {
  try {
    const u = new URL(rawUrl);
    // If hostname is raw IPv4 address (e.g. 31.97.51.101), append .nip.io so WhatsApp recognizes it as a clickable domain
    if (/^(\d{1,3}\.){3}\d{1,3}$/.test(u.hostname)) {
      u.hostname = u.hostname + '.nip.io';
    }
    return u.toString();
  } catch (e) {
    return rawUrl;
  }
};

// Copy Slip Gaji Link to Clipboard
window.copySlipGajiLink = function() {
  if (!window.currentSlipDataCache || !window.currentSlipDataCache.salary) {
    showToast('Data slip gaji belum dimuat', 'warning');
    return;
  }
  const s = window.currentSlipDataCache.salary;
  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const docUrl = window.formatWaClickableUrl(`${loc.origin}${basePath}/salary-view?id=${s.id}`);

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(docUrl).then(() => {
      showToast('Link Slip Gaji berhasil disalin!', 'success');
    }).catch(() => {
      prompt('Salin link slip gaji berikut:', docUrl);
    });
  } else {
    prompt('Salin link slip gaji berikut:', docUrl);
  }
};

// Share Slip Gaji to WhatsApp
window.shareSlipGajiWa = function() {
  if (!window.currentSlipDataCache || !window.currentSlipDataCache.salary) {
    showToast('Data slip gaji belum dimuat', 'warning');
    return;
  }
  const s = window.currentSlipDataCache.salary;
  const f = window.currentSlipDataCache.formatted;

  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const docUrl = window.formatWaClickableUrl(`${loc.origin}${basePath}/salary-view?id=${s.id}`);
  
  const text = 
`*SLIP GAJI RESMI KARYAWAN*
*PT Kala Kausa Prima*

Halo *${s.employee_name}*, berikut rincian slip gaji resmi Anda:

- Periode: ${s.month_period || '-'}
- Jabatan: ${s.employee_position || '-'} (${s.emp_dept || 'Creative & Production'})
- Tanggal Bayar: ${f.payment_date}

- Gaji Pokok: ${f.base_salary}
- Tunjangan: ${f.allowance}
- Potongan: ${f.deduction}
------------------------------------
- GAJI BERSIH (TAKE-HOME PAY): *${f.net_salary}*
- Rekening: ${s.bank_name || 'BCA'} - ${s.bank_account || '-'}
- Status: ${s.status === 'Paid' ? 'LUNAS (PAID)' : 'PENDING'}

Catatan: ${s.notes || 'Pembayaran gaji bulanan PT Kala Kausa Prima.'}

Lihat & Unduh Slip Gaji Resmi:
${docUrl}

Terima kasih atas kerja keras dan dedikasi Anda untuk PT Kala Kausa Prima.
_PT Kala Kausa Prima • Built for What’s Next_`;

  let phone = (s.emp_phone || '').replace(/[^0-9]/g, '');
  if (phone.startsWith('0')) {
    phone = '62' + phone.substring(1);
  }

  const url = phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
  window.open(url, '_blank');
};

// Direct Download PDF
async function downloadSlipPdf() {
  const element = document.getElementById('slip-print-area');
  const empName = (document.getElementById('slip-emp-name')?.innerText || 'Karyawan').trim();
  const rawPeriod = (document.getElementById('slip-display-period')?.innerText || '').replace('Periode:', '').trim();
  
  // Format Month & Year as MMYY (contoh: "0826" untuk periode "2026-08")
  let mmyy = '';
  if (/^\d{4}-\d{2}$/.test(rawPeriod)) {
    const parts = rawPeriod.split('-');
    mmyy = parts[1] + parts[0].substring(2);
  } else {
    const d = new Date();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).substring(2);
    mmyy = `${mm}${yy}`;
  }

  const filename = `SLP-${empName}-${mmyy}.pdf`;

  const btn = document.getElementById('btn-download-slip-pdf');
  const origHtml = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = `<span>Menyiapkan PDF...</span>`;

  try {
    if (typeof html2pdf !== 'undefined' && element) {
      const opt = {
        margin:       [0, 0, 0, 0],
        filename:     filename,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, letterRendering: true, scrollY: 0 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      await html2pdf().set(opt).from(element).save();
      showToast(`Slip Gaji (${filename}) berhasil didownload!`, 'success');
    } else {
      window.location.href = `api/salaries.php?action=download_slip_pdf&id=${currentSlipSalaryId}&auto_download=1`;
    }
  } catch (err) {
    console.error('Error generating PDF client-side:', err);
    window.location.href = `api/salaries.php?action=download_slip_pdf&id=${currentSlipSalaryId}&auto_download=1`;
  } finally {
    btn.disabled = false;
    btn.innerHTML = origHtml;
  }
}

function printSlipGaji(customTitle) {
  const content = document.getElementById('slip-print-area').innerHTML;
  const docTitle = customTitle || 'Slip Gaji - PT Kala Kausa Prima';
  const printWindow = window.open('', '', 'width=850,height=950');
  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>${docTitle}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
          * { box-sizing: border-box; margin: 0; padding: 0; }
          body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            margin: 20px auto;
            max-width: 800px;
            color: #000000;
            background: #FFFFFF;
            padding: 20px;
          }
          @media print {
            @page { size: A4; margin: 15mm; }
            body { margin: 0; padding: 0; max-width: 100%; }
          }
        </style>
      </head>
      <body>
        ${content}
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 350);
}

window.pendingDeleteCallback = null;

window.showConfirmDeleteModal = function({ title, descriptionHtml, confirmBtnText = 'Hapus Invoice', onConfirm }) {
  const titleEl = document.getElementById('confirm-modal-title');
  const descEl = document.getElementById('confirm-modal-desc');
  const confirmBtn = document.getElementById('btn-confirm-delete-action');

  if (titleEl) titleEl.textContent = title || 'Hapus Data?';
  if (descEl) descEl.innerHTML = descriptionHtml || 'Apakah Anda yakin ingin menghapus data ini? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.';
  if (confirmBtn) {
    const span = confirmBtn.querySelector('span');
    if (span) span.textContent = confirmBtnText;
    else confirmBtn.textContent = confirmBtnText;
  }
  
  window.pendingDeleteCallback = onConfirm;
  openModal('modal-confirm-delete');
};

window.executePendingDelete = async function() {
  const confirmBtn = document.getElementById('btn-confirm-delete-action');
  if (typeof window.pendingDeleteCallback === 'function') {
    const originalHtml = confirmBtn ? confirmBtn.innerHTML : '';
    if (confirmBtn) {
      confirmBtn.disabled = true;
      confirmBtn.innerHTML = '<span>Menghapus...</span>';
    }
    try {
      await window.pendingDeleteCallback();
    } catch (err) {
      console.error('Delete execution error:', err);
      if (typeof showToast === 'function') showToast('Terjadi kesalahan saat menghapus data', 'danger');
    } finally {
      if (confirmBtn) {
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalHtml;
      }
      closeModal('modal-confirm-delete');
    }
  }
};

window.openProjectFinancialDetailModal = async function(projectId, clientId = 0) {
  openModal('modal-project-financial-breakdown');
  switchPfbTab('invoices');

  const subTitleEl = document.getElementById('pfb-subtitle');
  if (subTitleEl) subTitleEl.innerText = 'Mengambil data keuangan & pengeluaran...';

  try {
    const url = new URL('api/analytics.php', window.location.origin + window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1));
    url.searchParams.append('action', 'project_financial_breakdown');
    if (projectId > 0) url.searchParams.append('project_id', projectId);
    if (clientId > 0) url.searchParams.append('client_id', clientId);

    const res = await fetch(url);
    const data = await res.json();

    if (!data.success) {
      if (typeof showToast === 'function') showToast(data.message || 'Gagal memuat rincian keuangan', 'danger');
      return;
    }

    const p = data.project;
    const f = data.financials;

    // Header Info
    if (subTitleEl) {
      subTitleEl.innerHTML = `<strong>${p.name}</strong> &bull; Klien: <strong>${p.client_company}</strong> (PIC: ${p.client_pic})`;
    }
    const badge = document.getElementById('pfb-badge-status');
    if (badge) {
      badge.className = `badge-status badge-${f.is_profitable ? 'paid' : 'overdue'}`;
      badge.textContent = f.is_profitable ? `${f.margin_percent}% Margin` : 'Defisit / Over Budget';
    }

    // Top 4 KPI Metrics
    document.getElementById('pfb-contract-value').innerText = f.formatted.contract_value;
    document.getElementById('pfb-invoiced-meta').innerText = `Terbit: ${f.formatted.total_invoiced} | Masuk: ${f.formatted.total_paid_inflow}`;
    
    document.getElementById('pfb-prod-cost').innerText = f.formatted.production_cost;
    document.getElementById('pfb-cost-breakdown-meta').innerText = `Fee: ${f.formatted.freelancer_cost} | Ads: ${f.formatted.ads_cost}`;

    document.getElementById('pfb-net-profit').innerText = f.formatted.net_profit;
    document.getElementById('pfb-profit-margin').innerText = f.formatted.margin_percent;
    document.getElementById('pfb-target-margin-meta').innerText = `Target Minimal: ${p.target_margin}%`;

    // Allocation Visual Bar
    const contract = Math.max(1, f.contract_value);
    const feePercent = Math.min(100, Math.round((f.total_freelancer_cost / contract) * 100));
    const adsPercent = Math.min(100 - feePercent, Math.round((f.total_ads_cost / contract) * 100));
    const profitPercent = Math.max(0, 100 - feePercent - adsPercent);

    document.getElementById('pfb-bar-freelancer').style.width = feePercent + '%';
    document.getElementById('pfb-bar-ads').style.width = adsPercent + '%';
    document.getElementById('pfb-bar-profit').style.width = profitPercent + '%';

    document.getElementById('pfb-legend-freelancer').innerText = `${f.formatted.freelancer_cost} (${feePercent}%)`;
    document.getElementById('pfb-legend-ads').innerText = `${f.formatted.ads_cost} (${adsPercent}%)`;
    document.getElementById('pfb-legend-profit').innerText = `${f.formatted.net_profit} (${profitPercent}%)`;

    // Counts
    document.getElementById('pfb-count-invoices').innerText = data.invoices ? data.invoices.length : 0;
    document.getElementById('pfb-count-freelancers').innerText = data.freelancers ? data.freelancers.length : 0;
    document.getElementById('pfb-count-ads').innerText = data.ads ? data.ads.length : 0;
    document.getElementById('pfb-count-contents').innerText = data.contents ? data.contents.length : 0;

    // Render Invoices Table
    const invTbody = document.getElementById('pfb-table-invoices-body');
    if (!data.invoices || data.invoices.length === 0) {
      invTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada invoice untuk proyek ini.</td></tr>`;
    } else {
      invTbody.innerHTML = data.invoices.map(inv => `
        <tr>
          <td style="font-weight: 700; color: #101828;">#${inv.invoice_number}</td>
          <td>${inv.formatted_issue_date}</td>
          <td>${inv.formatted_due_date}</td>
          <td style="font-weight: 700; color: #101828;">${inv.formatted_amount}</td>
          <td><span class="badge-status badge-${(inv.status || 'draft').toLowerCase()}">${inv.status}</span></td>
          <td style="text-align: right;">
            <a href="index.php?page=invoice-view&id=${inv.id}" target="_blank" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11.5px;">
              Lihat ↗
            </a>
          </td>
        </tr>
      `).join('');
    }

    // Render Freelancers Table
    const freeTbody = document.getElementById('pfb-table-freelancers-body');
    if (!data.freelancers || data.freelancers.length === 0) {
      freeTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada alokasi fee freelancer.</td></tr>`;
    } else {
      freeTbody.innerHTML = data.freelancers.map(fr => `
        <tr>
          <td style="font-weight: 700; color: #101828;">${fr.freelancer_name}</td>
          <td>${fr.task_description || '-'}</td>
          <td><small style="color: var(--text-secondary);">${fr.freelancer_bank || 'BCA'} - ${fr.freelancer_account || '-'}</small></td>
          <td style="font-weight: 700; color: #B91C1C;">${fr.formatted_amount}</td>
          <td><span class="badge-status badge-${(fr.status || 'pending').toLowerCase()}">${fr.status}</span></td>
          <td style="text-align: right;">
            ${fr.receipt_file ? `<button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;" onclick="viewReceiptImage('uploads/${fr.receipt_file}', 'Bukti ${fr.freelancer_name}')">Struk</button>` : '<span style="color:var(--text-muted); font-size:11px;">-</span>'}
          </td>
        </tr>
      `).join('');
    }

    // Render Ads Table
    const adsTbody = document.getElementById('pfb-table-ads-body');
    if (!data.ads || data.ads.length === 0) {
      adsTbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada pengeluaran iklan digital.</td></tr>`;
    } else {
      adsTbody.innerHTML = data.ads.map(ad => `
        <tr>
          <td style="font-weight: 700; color: #101828;">${ad.platform} ${ad.account_id ? `<small>(${ad.account_id})</small>` : ''}</td>
          <td>${ad.campaign_name || '-'}</td>
          <td>${ad.formatted_spent_date}</td>
          <td style="font-weight: 700; color: #B91C1C;">${ad.formatted_amount}</td>
          <td style="text-align: right;">
            ${ad.receipt_file ? `<button type="button" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;" onclick="viewReceiptImage('uploads/${ad.receipt_file}', 'Bukti ${ad.platform}')">Struk</button>` : '<span style="color:var(--text-muted); font-size:11px;">-</span>'}
          </td>
        </tr>
      `).join('');
    }

    // Render Contents Table
    const contTbody = document.getElementById('pfb-table-contents-body');
    if (!data.contents || data.contents.length === 0) {
      contTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 20px;">Belum ada jadwal konten.</td></tr>`;
    } else {
      contTbody.innerHTML = data.contents.map(ct => `
        <tr>
          <td style="font-weight: 700; color: #101828;">${ct.title}</td>
          <td>${ct.platform} &bull; ${ct.content_type || 'Post'}</td>
          <td>${ct.publish_date} ${ct.publish_time || ''}</td>
          <td>${ct.pic_name || '-'}</td>
          <td><span class="badge-status badge-${(ct.status || 'draft').toLowerCase().replace(' ', '-')}">${ct.status}</span></td>
          <td style="text-align: right;">
            ${ct.asset_url ? `<a href="${ct.asset_url}" target="_blank" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 11px;">Aset ↗</a>` : '-'}
          </td>
        </tr>
      `).join('');
    }

  } catch (err) {
    console.error('Error fetching project financial breakdown:', err);
    if (typeof showToast === 'function') showToast('Gagal terhubung ke server', 'danger');
  }
};

window.switchPfbTab = function(tabName) {
  ['invoices', 'freelancers', 'ads', 'contents'].forEach(t => {
    const pane = document.getElementById(`pfb-pane-${t}`);
    const btn = document.getElementById(`pfb-tab-btn-${t}`);
    if (pane) pane.style.display = (t === tabName) ? 'block' : 'none';
    if (btn) {
      if (t === tabName) btn.classList.add('active');
      else btn.classList.remove('active');
    }
  });
};

window.openEditEmployeeModal = function(empData) {
  if (typeof empData === 'number' || typeof empData === 'string') {
    fetch(`api/salaries.php?action=get_employee&id=${empData}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.employee) {
          populateEditEmpForm(data.employee);
          openModal('modal-edit-employee');
        } else {
          if (typeof showToast === 'function') showToast(data.message || 'Gagal memuat data karyawan', 'danger');
        }
      })
      .catch(err => {
        console.error('Error fetching employee:', err);
        if (typeof showToast === 'function') showToast('Koneksi server gagal', 'danger');
      });
  } else if (typeof empData === 'object' && empData !== null) {
    populateEditEmpForm(empData);
    openModal('modal-edit-employee');
  }
};

function populateEditEmpForm(emp) {
  const idEl = document.getElementById('edit-emp-id');
  const nameEl = document.getElementById('edit-emp-name');
  const posEl = document.getElementById('edit-emp-position');
  const deptEl = document.getElementById('edit-emp-department');
  const typeEl = document.getElementById('edit-emp-employment-type');
  const statEl = document.getElementById('edit-emp-status');
  const emailEl = document.getElementById('edit-emp-email');
  const phoneEl = document.getElementById('edit-emp-phone');
  const bankEl = document.getElementById('edit-emp-bank-name');
  const accEl = document.getElementById('edit-emp-bank-account');
  const salEl = document.getElementById('edit-emp-base-salary');

  if (idEl) idEl.value = emp.id || '';
  if (nameEl) nameEl.value = emp.name || '';
  if (posEl) posEl.value = emp.position || '';
  if (deptEl) deptEl.value = emp.department || 'Creative & Production';
  if (typeEl) typeEl.value = emp.employment_type || 'Full-time';
  if (statEl) statEl.value = emp.status || 'Active';
  if (emailEl) emailEl.value = emp.email || '';
  if (phoneEl) phoneEl.value = emp.phone || '';
  if (bankEl) bankEl.value = emp.bank_name || 'BCA';
  if (accEl) accEl.value = emp.bank_account || '';
  if (salEl) {
    const rawVal = Math.round(parseFloat(emp.base_salary) || 0);
    salEl.value = rawVal ? rawVal.toLocaleString('id-ID') : '0';
  }
}

document.addEventListener('DOMContentLoaded', () => {
  handleAjaxForm('form-input-salary', () => {
    closeModal('modal-input-salary');
    setTimeout(() => window.location.reload(), 800);
  });

  handleAjaxForm('form-create-employee', () => {
    closeModal('modal-create-employee');
    setTimeout(() => window.location.reload(), 800);
  });

  handleAjaxForm('form-edit-employee', () => {
    closeModal('modal-edit-employee');
    setTimeout(() => window.location.reload(), 800);
  });
});

window.openCreateUserModal = function(defaultRole = 'karyawan') {
  const form = document.getElementById('form-add-user');
  if (form) form.reset();
  const roleSelect = document.getElementById('new-user-role');
  if (roleSelect) {
    roleSelect.value = defaultRole;
    toggleUserRoleFields(defaultRole);
  }
  openModal('modal-add-user');
};

window.toggleUserRoleFields = function(role) {
  const clientGroup = document.getElementById('group-client-select');
  const clientSelect = document.getElementById('new-user-client-id');
  const posGroup = document.getElementById('group-position-input');
  const posLabel = document.getElementById('label-user-position');
  const nameLabel = document.getElementById('label-user-name');

  if (role === 'client') {
    if (clientGroup) clientGroup.style.display = 'block';
    if (clientSelect) clientSelect.required = true;
    if (posGroup) posGroup.style.display = 'none';
    if (nameLabel) nameLabel.textContent = 'Nama Kontak PIC Klien *';
  } else if (role === 'freelancer') {
    if (clientGroup) clientGroup.style.display = 'none';
    if (clientSelect) clientSelect.required = false;
    if (posGroup) posGroup.style.display = 'block';
    if (posLabel) posLabel.textContent = 'Keahlian / Spesialisasi Freelancer';
    if (nameLabel) nameLabel.textContent = 'Nama Lengkap Freelancer *';
  } else if (role === 'karyawan') {
    if (clientGroup) clientGroup.style.display = 'none';
    if (clientSelect) clientSelect.required = false;
    if (posGroup) posGroup.style.display = 'block';
    if (posLabel) posLabel.textContent = 'Posisi / Jabatan Internal';
    if (nameLabel) nameLabel.textContent = 'Nama Lengkap Karyawan *';
  } else {
    // admin
    if (clientGroup) clientGroup.style.display = 'none';
    if (clientSelect) clientSelect.required = false;
    if (posGroup) posGroup.style.display = 'none';
    if (nameLabel) nameLabel.textContent = 'Nama Akun Admin *';
  }
};

window.submitCreateUser = async function(formId) {
  const form = document.getElementById(formId);
  if (!form) return;
  const btn = document.getElementById('btn-save-new-user');
  const origText = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = 'Menyimpan...';

  const formData = new FormData(form);

  try {
    const res = await fetch('api/users.php?action=create', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('modal-add-user');
      setTimeout(() => window.location.reload(), 700);
    } else {
      showToast(data.message || 'Gagal membuat akun.', 'danger');
    }
  } catch (err) {
    showToast('Terjadi kesalahan koneksi server.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = origText;
  }
};

window.openResetPasswordModal = function(userId, userName) {
  document.getElementById('reset-user-id').value = userId;
  document.getElementById('reset-user-name').textContent = userName;
  document.getElementById('reset-user-new-password').value = '';
  openModal('modal-reset-user-password');
};

window.submitResetPassword = async function(formId) {
  const form = document.getElementById(formId);
  const btn = document.getElementById('btn-save-reset-pw');
  btn.disabled = true;
  btn.innerHTML = 'Menyimpan...';

  const formData = new FormData(form);
  try {
    const res = await fetch('api/users.php?action=reset_password', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('modal-reset-user-password');
    } else {
      showToast(data.message || 'Gagal reset kata sandi.', 'danger');
    }
  } catch (err) {
    showToast('Gagal koneksi server.', 'danger');
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Simpan Sandi';
  }
};

window.deleteUserAccount = async function(userId, userName) {
  if (!confirm(`Apakah Anda yakin ingin menghapus akun "${userName}"? Tindakan ini tidak dapat dibatalkan.`)) {
    return;
  }

  const formData = new FormData();
  formData.append('id', userId);

  try {
    const res = await fetch('api/users.php?action=delete', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    if (data.success) {
      showToast(data.message, 'success');
      const row = document.getElementById(`user-row-${userId}`);
      if (row) {
        row.style.opacity = '0';
        setTimeout(() => row.remove(), 400);
      } else {
        setTimeout(() => window.location.reload(), 700);
      }
    } else {
      showToast(data.message || 'Gagal menghapus akun.', 'danger');
    }
  } catch (err) {
    showToast('Gagal koneksi server.', 'danger');
  }
};
</script>

