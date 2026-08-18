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
    const res = await fetch(`api/clients.php?action=get_client_projects&client_id=${clientId}`);
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

// Gather invoice items as JSON before submit
function submitInvoiceForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return;

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
    showToast('Harap isi minimal 1 item layanan!', 'danger');
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
