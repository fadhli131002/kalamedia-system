/**
 * Kalamedia Invoice Builder & Dynamic Calculations
 */

function addInvoiceItemRow() {
  const container = document.getElementById('invoice-items-container');
  if (!container) return;

  const rowId = 'item-' + Date.now();
  const tr = document.createElement('tr');
  tr.id = rowId;
  tr.innerHTML = `
    <td>
      <input type="text" class="form-control item-name" placeholder="Deskripsi layanan / jasa" required>
    </td>
    <td style="width: 90px;">
      <input type="number" class="form-control item-qty" value="1" min="1" step="1" oninput="calculateInvoiceTotals()" required>
    </td>
    <td style="width: 200px;">
      <div class="input-group-currency">
        <span class="currency-addon">Rp</span>
        <input type="text" class="form-control item-price rupiah-input" placeholder="0" oninput="calculateInvoiceTotals()" required>
      </div>
    </td>
    <td style="width: 170px;" class="text-right">
      <span class="item-line-total" style="font-weight: 700; color: #FFF;">Rp 0</span>
    </td>
    <td style="width: 50px; text-align: center;">
      <button type="button" class="btn-sm btn-danger" onclick="removeInvoiceItemRow('${rowId}')" title="Hapus Item">&times;</button>
    </td>
  `;
  container.appendChild(tr);
  if (typeof initRupiahInputs === 'function') initRupiahInputs();
  calculateInvoiceTotals();
}

function removeInvoiceItemRow(rowId) {
  const container = document.getElementById('invoice-items-container');
  if (container.children.length <= 1) {
    showToast('Minimal harus ada 1 baris item jasa!', 'warning');
    return;
  }
  const el = document.getElementById(rowId);
  if (el) el.remove();
  calculateInvoiceTotals();
}

function calculateInvoiceTotals() {
  const rows = document.querySelectorAll('#invoice-items-container tr');
  let subtotal = 0;

  rows.forEach(row => {
    const qty = parseFloat(row.querySelector('.item-qty')?.value || 1);
    const rawPrice = row.querySelector('.item-price')?.value.replace(/[^0-9]/g, '') || 0;
    const price = parseFloat(rawPrice);
    const lineTotal = qty * price;
    subtotal += lineTotal;

    const lineTotalSpan = row.querySelector('.item-line-total');
    if (lineTotalSpan) {
      lineTotalSpan.innerText = 'Rp ' + lineTotal.toLocaleString('id-ID');
    }
  });

  const discountPercent = parseFloat(document.getElementById('inv-discount-percent')?.value || 0);
  const taxPercent = parseFloat(document.getElementById('inv-tax-percent')?.value || 0);

  const discountAmount = (subtotal * discountPercent) / 100;
  const afterDiscount = subtotal - discountAmount;
  const taxAmount = (afterDiscount * taxPercent) / 100;
  const grandTotal = afterDiscount + taxAmount;

  if (document.getElementById('inv-display-subtotal')) {
    document.getElementById('inv-display-subtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
  }
  if (document.getElementById('inv-display-discount')) {
    document.getElementById('inv-display-discount').innerText = '- Rp ' + discountAmount.toLocaleString('id-ID');
  }
  if (document.getElementById('inv-display-tax')) {
    document.getElementById('inv-display-tax').innerText = '+ Rp ' + taxAmount.toLocaleString('id-ID');
  }
  if (document.getElementById('inv-display-total')) {
    document.getElementById('inv-display-total').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');
  }
}

function getApiUrl(endpoint) {
  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard|invoice-view).*$/, '').replace(/\/$/, '');
  const cleanEndpoint = endpoint.replace(/^\//, '');
  return `${loc.origin}${basePath}/${cleanEndpoint}`;
}

// Client changed -> load client's projects
async function loadProjectsForClient(clientId, targetSelectId = 'inv-project-select') {
  const select = document.getElementById(targetSelectId);
  if (!select) return;

  select.innerHTML = '<option value="">-- Memuat Proyek... --</option>';
  if (!clientId) {
    select.innerHTML = '<option value="">-- Pilih Klien Terlebih Dahulu --</option>';
    return;
  }

  try {
    const res = await fetch(getApiUrl(`api/clients.php?action=get_client_projects&client_id=${clientId}`));
    const data = await res.json();
    if (data.success && data.projects && data.projects.length > 0) {
      select.innerHTML = '<option value="">-- Pilih Proyek Terkait --</option>';
      data.projects.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.innerText = `${p.name} (Kontrak: Rp ${Number(p.contract_value).toLocaleString('id-ID')})`;
        select.appendChild(opt);
      });
    } else {
      select.innerHTML = '<option value="">-- Otomatis Buat Proyek untuk Klien Ini --</option>';
    }
  } catch (err) {
    console.error('Failed to load client projects', err);
    select.innerHTML = '<option value="">-- Otomatis Terkait Proyek Klien --</option>';
  }
}

function handleInvoiceClientChange(val) {
  const box = document.getElementById('box-new-client-fields');
  const compInput = document.getElementById('new-client-company');
  if (val === '__new__') {
    if (box) box.style.display = 'block';
    if (compInput) compInput.required = true;
  } else {
    if (box) box.style.display = 'none';
    if (compInput) compInput.required = false;
    loadProjectsForClient(val, 'inv-project-select');
  }
}

// Gather invoice items as JSON before submit
function submitInvoiceForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return;

  const clientSelect = form.querySelector('[name="client_id"]');
  if (clientSelect && (clientSelect.value === '__new__' || !clientSelect.value)) {
    const compInput = form.querySelector('[name="new_client_company"]');
    if (!compInput || !compInput.value.trim()) {
      if (typeof showToast === 'function') {
        showToast('Harap pilih klien atau masukkan Nama Perusahaan Klien Baru!', 'danger');
      } else {
        alert('Harap pilih klien atau masukkan Nama Perusahaan Klien Baru!');
      }
      if (compInput) compInput.focus();
      return;
    }
  }

  const rows = document.querySelectorAll('#invoice-items-container tr');
  const items = [];

  rows.forEach(row => {
    const name = row.querySelector('.item-name')?.value.trim();
    const qty = parseFloat(row.querySelector('.item-qty')?.value || 1);
    const rawPrice = row.querySelector('.item-price')?.value.replace(/[^0-9]/g, '') || 0;
    const price = parseFloat(rawPrice);

    if (name && qty > 0 && price >= 0) {
      items.push({
        service_name: name,
        quantity: qty,
        unit_price: price
      });
    }
  });

  if (items.length === 0) {
    if (typeof showToast === 'function') {
      showToast('Harap isi minimal 1 item layanan!', 'danger');
    } else {
      alert('Harap isi minimal 1 item layanan!');
    }
    return;
  }

  // Set hidden input
  let hiddenInput = form.querySelector('input[name="items"]');
  if (!hiddenInput) {
    hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'items';
    form.appendChild(hiddenInput);
  }
  hiddenInput.value = JSON.stringify(items);

  // Submit via AJAX
  const submitBtn = form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Menyimpan...';

  const formData = new FormData(form);
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('modal-create-invoice');
      setTimeout(() => {
        if (data.invoice_id) {
          window.location.href = `index.php?page=invoice-view&id=${data.invoice_id}`;
        } else {
          window.location.reload();
        }
      }, 800);
    } else {
      showToast(data.message || 'Gagal menyimpan invoice', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan server', 'danger');
  })
  .finally(() => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  });
}

// --- Edit Invoice Modal Functions ---

function escapeHtml(text) {
  if (!text) return '';
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function addEditInvoiceItemRow(name = '', qty = 1, price = 0) {
  const container = document.getElementById('edit-invoice-items-container');
  if (!container) return;

  const rowId = 'edit-item-' + Date.now() + '-' + Math.floor(Math.random() * 1000);
  const formattedPrice = typeof formatNumber === 'function' ? formatNumber(price) : Number(price).toLocaleString('id-ID');
  const lineTotal = (parseFloat(qty) || 0) * (parseFloat(price) || 0);
  const formattedLineTotal = 'Rp ' + lineTotal.toLocaleString('id-ID');

  const tr = document.createElement('tr');
  tr.id = rowId;
  tr.innerHTML = `
    <td>
      <input type="text" class="form-control item-name" placeholder="Deskripsi layanan / jasa" value="${name ? escapeHtml(name) : ''}" required>
    </td>
    <td style="width: 100px;">
      <input type="number" class="form-control item-qty" value="${qty}" min="1" step="1" oninput="calculateEditInvoiceTotals()" required>
    </td>
    <td style="width: 180px;">
      <div class="input-group-currency">
        <span class="currency-addon">Rp</span>
        <input type="text" class="form-control item-price rupiah-input" placeholder="0" value="${price > 0 ? formattedPrice : ''}" oninput="calculateEditInvoiceTotals()" required>
      </div>
    </td>
    <td style="width: 180px;" class="text-right">
      <span class="item-line-total" style="font-weight: 700; color: #101828;">${formattedLineTotal}</span>
    </td>
    <td style="width: 50px; text-align: center;">
      <button type="button" class="btn-sm btn-danger" onclick="removeEditInvoiceItemRow('${rowId}')" title="Hapus Item">&times;</button>
    </td>
  `;
  container.appendChild(tr);
  if (typeof initRupiahInputs === 'function') initRupiahInputs();
  calculateEditInvoiceTotals();
}

function removeEditInvoiceItemRow(rowId) {
  const container = document.getElementById('edit-invoice-items-container');
  if (!container) return;
  if (container.children.length <= 1) {
    showToast('Minimal harus ada 1 baris item jasa!', 'warning');
    return;
  }
  const el = document.getElementById(rowId);
  if (el) el.remove();
  calculateEditInvoiceTotals();
}

function calculateEditInvoiceTotals() {
  const rows = document.querySelectorAll('#edit-invoice-items-container tr');
  let subtotal = 0;

  rows.forEach(row => {
    const qty = parseFloat(row.querySelector('.item-qty')?.value || 1);
    const rawPrice = row.querySelector('.item-price')?.value.replace(/[^0-9]/g, '') || 0;
    const price = parseFloat(rawPrice);
    const lineTotal = qty * price;
    subtotal += lineTotal;

    const lineTotalSpan = row.querySelector('.item-line-total');
    if (lineTotalSpan) {
      lineTotalSpan.innerText = 'Rp ' + lineTotal.toLocaleString('id-ID');
    }
  });

  const discountPercent = parseFloat(document.getElementById('edit-inv-discount-percent')?.value || 0);
  const taxPercent = parseFloat(document.getElementById('edit-inv-tax-percent')?.value || 0);

  const discountAmount = (subtotal * discountPercent) / 100;
  const afterDiscount = subtotal - discountAmount;
  const taxAmount = (afterDiscount * taxPercent) / 100;
  const grandTotal = afterDiscount + taxAmount;

  if (document.getElementById('edit-inv-display-subtotal')) {
    document.getElementById('edit-inv-display-subtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
  }
  if (document.getElementById('edit-inv-display-discount')) {
    document.getElementById('edit-inv-display-discount').innerText = '- Rp ' + discountAmount.toLocaleString('id-ID');
  }
  if (document.getElementById('edit-inv-display-tax')) {
    document.getElementById('edit-inv-display-tax').innerText = '+ Rp ' + taxAmount.toLocaleString('id-ID');
  }
  if (document.getElementById('edit-inv-display-total')) {
    document.getElementById('edit-inv-display-total').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');
  }
}

async function openEditInvoiceModal(invoiceId) {
  if (!invoiceId) return;

  try {
    const res = await fetch(getApiUrl(`api/invoices.php?action=get_details&id=${invoiceId}`));
    const data = await res.json();
    if (!data.success) {
      if (data.redirect) {
        showToast(data.message || 'Silakan login terlebih dahulu.', 'warning');
        setTimeout(() => { window.location.href = data.redirect; }, 1000);
        return;
      }
      showToast(data.message || 'Gagal memuat data invoice', 'danger');
      return;
    }
    if (!data.invoice) {
      showToast('Data invoice tidak ditemukan.', 'danger');
      return;
    }

    const inv = data.invoice;
    if (inv.status === 'Paid') {
      showToast('Invoice berstatus Paid (Lunas) tidak dapat diedit.', 'warning');
      return;
    }

    const editIdInput = document.getElementById('edit-inv-id');
    if (editIdInput) editIdInput.value = inv.id;

    const badge = document.getElementById('edit-inv-number-badge');
    if (badge) badge.innerText = '#' + inv.invoice_number;

    const clientSelect = document.getElementById('edit-inv-client-select');
    if (clientSelect) {
      clientSelect.value = inv.client_id;
    }

    // Load projects and preselect
    await loadProjectsForClient(inv.client_id, 'edit-inv-project-select');
    const projSelect = document.getElementById('edit-inv-project-select');
    if (projSelect && inv.project_id) {
      projSelect.value = inv.project_id;
    }

    const issueDateInput = document.getElementById('edit-inv-issue-date');
    if (issueDateInput) issueDateInput.value = inv.issue_date || '';

    const dueDateInput = document.getElementById('edit-inv-due-date');
    if (dueDateInput) dueDateInput.value = inv.due_date || '';

    const discInput = document.getElementById('edit-inv-discount-percent');
    if (discInput) discInput.value = inv.discount_percent || 0;

    const taxInput = document.getElementById('edit-inv-tax-percent');
    if (taxInput) taxInput.value = inv.tax_percent || 0;

    const notesInput = document.getElementById('edit-inv-notes');
    if (notesInput) notesInput.value = inv.notes || '';

    // Clear items container
    const itemsContainer = document.getElementById('edit-invoice-items-container');
    if (itemsContainer) {
      itemsContainer.innerHTML = '';
      if (inv.items && inv.items.length > 0) {
        inv.items.forEach(item => {
          addEditInvoiceItemRow(item.service_name, item.quantity, item.unit_price);
        });
      } else {
        addEditInvoiceItemRow('', 1, 0);
      }
    }

    calculateEditInvoiceTotals();
    openModal('modal-edit-invoice');
  } catch (err) {
    console.error('Error opening edit invoice modal:', err);
    showToast('Terjadi kesalahan saat memuat data invoice', 'danger');
  }
}

function submitEditInvoiceForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return;

  const rows = document.querySelectorAll('#edit-invoice-items-container tr');
  const items = [];

  rows.forEach(row => {
    const name = row.querySelector('.item-name')?.value.trim();
    const qty = parseFloat(row.querySelector('.item-qty')?.value || 1);
    const rawPrice = row.querySelector('.item-price')?.value.replace(/[^0-9]/g, '') || 0;
    const price = parseFloat(rawPrice);

    if (name && qty > 0 && price >= 0) {
      items.push({
        service_name: name,
        quantity: qty,
        unit_price: price
      });
    }
  });

  if (items.length === 0) {
    showToast('Harap isi minimal 1 item layanan!', 'danger');
    return;
  }

  let hiddenInput = form.querySelector('input[name="items"]');
  if (!hiddenInput) {
    hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'items';
    form.appendChild(hiddenInput);
  }
  hiddenInput.value = JSON.stringify(items);

  const submitBtn = document.getElementById('btn-submit-edit-invoice') || form.querySelector('button[type="submit"]');
  const originalText = submitBtn.innerHTML;
  submitBtn.disabled = true;
  submitBtn.innerHTML = 'Menyimpan Perubahan...';

  const formData = new FormData(form);
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      showToast(data.message, 'success');
      closeModal('modal-edit-invoice');
      setTimeout(() => {
        window.location.reload();
      }, 700);
    } else {
      showToast(data.message || 'Gagal menyimpan invoice', 'danger');
    }
  })
  .catch(err => {
    showToast('Terjadi kesalahan server', 'danger');
  })
  .finally(() => {
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalText;
  });
}

