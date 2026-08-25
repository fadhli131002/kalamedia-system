/**
 * Kalamedia Agency Financial & Project Management System
 * Core Frontend Interactions & Modal Manager
 */

// Mobile Sidebar Drawer Navigation Helper
function toggleMobileSidebar() {
  const sidebar = document.querySelector('.sidebar') || document.getElementById('app-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (sidebar) sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('active');
}

function closeMobileSidebar() {
  const sidebar = document.querySelector('.sidebar') || document.getElementById('app-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  if (sidebar) sidebar.classList.remove('open');
  if (overlay) overlay.classList.remove('active');
}


// Toast Notifications System
function showToast(message, type = 'info') {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <div style="flex:1; font-size:13px; font-weight:500;">${message}</div>
    <button onclick="this.parentElement.remove()" style="background:transparent;border:none;color:#FFF;cursor:pointer;opacity:0.6">&times;</button>
  `;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

// Modal Control
function openModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(modalId) {
  const modal = document.getElementById(modalId);
  if (modal) {
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
  }
}

// Close modal when clicking outside dialog
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('show');
    document.body.style.overflow = 'auto';
  }
});

// Fast Table Filter & Search
function initTableSearch(inputId, tableId) {
  const searchInput = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!searchInput || !table) return;

  searchInput.addEventListener('keyup', () => {
    const query = searchInput.value.toLowerCase();
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
      const text = row.innerText.toLowerCase();
      row.style.display = text.includes(query) ? '' : 'none';
    });
  });
}

// Receipt Upload Modal & Trigger
function triggerUploadModal(targetType, targetId, title = 'Upload Bukti Pembayaran') {
  const modal = document.getElementById('modal-upload-receipt');
  if (!modal) return;

  document.getElementById('upload-target-type').value = targetType;
  document.getElementById('upload-target-id').value = targetId;
  document.getElementById('upload-modal-title').innerText = title;

  // Reset file input & preview
  document.getElementById('receipt-file-input').value = '';
  document.getElementById('receipt-preview-container').style.display = 'none';
  document.getElementById('receipt-preview-img').src = '';

  openModal('modal-upload-receipt');
}

// Handle Receipt File Selection & Preview
document.addEventListener('DOMContentLoaded', () => {
  const fileInput = document.getElementById('receipt-file-input');
  if (fileInput) {
    fileInput.addEventListener('change', (e) => {
      const file = e.target.files[0];
      if (!file) return;

      if (file.size > 5 * 1024 * 1024) {
        showToast('Ukuran file melebihi 5MB! Silakan pilih file yang lebih kecil.', 'danger');
        fileInput.value = '';
        return;
      }

      const previewContainer = document.getElementById('receipt-preview-container');
      const previewImg = document.getElementById('receipt-preview-img');

      if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (event) => {
          previewImg.src = event.target.result;
          previewContainer.style.display = 'block';
        };
        reader.readAsDataURL(file);
      } else {
        previewContainer.style.display = 'none';
      }
    });
  }

  // Upload Form Submit
  const uploadForm = document.getElementById('form-upload-receipt');
  if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(uploadForm);
      const submitBtn = uploadForm.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Mengunggah...';

      try {
        const res = await fetch(uploadForm.action, {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, 'success');
          closeModal('modal-upload-receipt');
          setTimeout(() => window.location.reload(), 800);
        } else {
          showToast(data.message || 'Gagal mengunggah bukti', 'danger');
        }
      } catch (err) {
        showToast('Terjadi kesalahan koneksi server', 'danger');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }
});

// View Receipt Image Lightbox
function viewReceiptImage(imgUrl, title = 'Bukti Pembayaran') {
  const modal = document.getElementById('modal-view-receipt');
  if (!modal) return;

  document.getElementById('view-receipt-img').src = imgUrl;
  document.getElementById('view-receipt-title').innerText = title;
  document.getElementById('view-receipt-download').href = imgUrl;
  openModal('modal-view-receipt');
}

// Rupiah Input Real-Time Formatter
function formatRupiahDisplay(value) {
  if (value === null || value === undefined || value === '') return '';
  const num = value.toString().replace(/[^0-9]/g, '');
  if (!num) return '';
  return Number(num).toLocaleString('id-ID');
}

function unformatRupiah(value) {
  if (!value) return 0;
  return parseFloat(value.toString().replace(/[^0-9]/g, '')) || 0;
}

window.formatRupiahDisplay = formatRupiahDisplay;
window.formatRupiahInput = formatRupiahDisplay;
window.unformatRupiah = unformatRupiah;

// Global Client Projects Loader for Select Dropdowns
window.loadProjectsForClient = async function(clientId, targetSelectId = 'inv-project-select') {
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
};

function initRupiahInputs() {
  document.querySelectorAll('.rupiah-input').forEach(input => {
    if (input.dataset.rupiahInitialized) return;
    input.dataset.rupiahInitialized = 'true';

    // Format existing initial value if any
    if (input.value && input.value !== '0') {
      input.value = formatRupiahDisplay(input.value);
    }

    input.addEventListener('input', () => {
      const raw = input.value.replace(/[^0-9]/g, '');
      if (!raw) {
        input.value = '';
        return;
      }
      input.value = Number(raw).toLocaleString('id-ID');
    });

    input.addEventListener('blur', () => {
      if (!input.value.trim() && input.required) {
        input.value = '0';
      }
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initRupiahInputs();
});

// Generic AJAX Form Handler
function handleAjaxForm(formId, onSuccess) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Memproses...';
    }

    try {
      const formData = new FormData(form);

      // Unformat any rupiah inputs before submitting so backend receives clean floats
      form.querySelectorAll('.rupiah-input').forEach(input => {
        if (input.name) {
          const rawNum = input.value.replace(/[^0-9]/g, '');
          formData.set(input.name, rawNum);
        }
      });

      const res = await fetch(form.action, {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        showToast(data.message, 'success');
        if (onSuccess) {
          onSuccess(data);
        } else {
          setTimeout(() => window.location.reload(), 800);
        }
      } else {
        showToast(data.message || 'Operasi gagal', 'danger');
      }
    } catch (err) {
      showToast('Terjadi kesalahan jaringan atau server', 'danger');
    } finally {
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    }
  });
}

// Universal Custom Destructive Confirmation Modal
window.pendingDeleteCallback = null;

window.showConfirmDeleteModal = function({ title, descriptionHtml, confirmBtnText = 'Hapus', onConfirm }) {
  const modal = document.getElementById('modal-confirm-delete');
  if (!modal) {
    console.error('modal-confirm-delete element not found in DOM');
    return;
  }

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

// Invoices Delete Helper
window.confirmDeleteInvoice = function(id, invoiceNumber) {
  window.showConfirmDeleteModal({
    title: 'Hapus Invoice?',
    descriptionHtml: `Apakah Anda yakin ingin menghapus invoice <strong style="color: #101828;">#${invoiceNumber}</strong>? Tindakan ini bersifat permanen dan data yang dihapus tidak dapat dipulihkan.`,
    confirmBtnText: 'Hapus Invoice',
    onConfirm: async () => {
      const formData = new FormData();
      formData.append('invoice_id', id);

      try {
        const res = await fetch('api/invoices.php?action=delete', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || 'Invoice berhasil dihapus!', 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(data.message || 'Gagal menghapus invoice', 'danger');
        }
      } catch (err) {
        showToast('Gagal menghapus invoice', 'danger');
      }
    }
  });
};

// --- Client CRUD Helpers ---
window.openEditClientModal = function(client) {
  if (typeof client === 'number' || typeof client === 'string') {
    fetch(`api/clients.php?action=get_client&id=${client}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.client) {
          fillClientModal(data.client);
        } else {
          showToast(data.message || 'Gagal memuat data klien', 'danger');
        }
      })
      .catch(() => showToast('Gagal menghubungi server', 'danger'));
  } else if (typeof client === 'object' && client !== null) {
    fillClientModal(client);
  }
};

function fillClientModal(c) {
  const idEl = document.getElementById('edit-client-id');
  const compEl = document.getElementById('edit-client-company');
  const nameEl = document.getElementById('edit-client-name');
  const emailEl = document.getElementById('edit-client-email');
  const phoneEl = document.getElementById('edit-client-phone');
  const addrEl = document.getElementById('edit-client-address');

  if (idEl) idEl.value = c.id || '';
  if (compEl) compEl.value = c.company || '';
  if (nameEl) nameEl.value = c.name || '';
  if (emailEl) emailEl.value = c.email || '';
  if (phoneEl) phoneEl.value = c.phone || '';
  if (addrEl) addrEl.value = c.address || '';

  openModal('modal-edit-client');
}

window.confirmDeleteClient = function(id, companyName) {
  window.showConfirmDeleteModal({
    title: 'Hapus Data Klien?',
    descriptionHtml: `Apakah Anda yakin ingin menghapus data klien <strong style="color: #101828;">${companyName}</strong>? Data klien akan diarsipkan dari daftar aktif.`,
    confirmBtnText: 'Hapus Klien',
    onConfirm: async () => {
      const formData = new FormData();
      formData.append('id', id);

      try {
        const res = await fetch('api/clients.php?action=delete_client', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || 'Data klien berhasil dihapus!', 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(data.message || 'Gagal menghapus data klien', 'danger');
        }
      } catch (err) {
        showToast('Gagal menghapus data klien', 'danger');
      }
    }
  });
};

// --- Project CRUD Helpers ---
window.openEditProjectModal = function(project) {
  if (typeof project === 'number' || typeof project === 'string') {
    fetch(`api/clients.php?action=get_project&id=${project}`)
      .then(res => res.json())
      .then(data => {
        if (data.success && data.project) {
          fillProjectModal(data.project);
        } else {
          showToast(data.message || 'Gagal memuat data proyek', 'danger');
        }
      })
      .catch(() => showToast('Gagal menghubungi server', 'danger'));
  } else if (typeof project === 'object' && project !== null) {
    fillProjectModal(project);
  }
};

function fillProjectModal(p) {
  const idEl = document.getElementById('edit-project-id');
  const clientEl = document.getElementById('edit-project-client-id');
  const nameEl = document.getElementById('edit-project-name');
  const valEl = document.getElementById('edit-project-contract-value');
  const marginEl = document.getElementById('edit-project-target-margin');
  const statusEl = document.getElementById('edit-project-status');
  const startEl = document.getElementById('edit-project-start-date');
  const endEl = document.getElementById('edit-project-end-date');

  if (idEl) idEl.value = p.id || '';
  if (clientEl) clientEl.value = p.client_id || '';
  if (nameEl) nameEl.value = p.name || '';
  if (valEl) {
    const num = Math.round(parseFloat(p.contract_value || 0));
    valEl.value = num.toLocaleString('id-ID');
  }
  if (marginEl) marginEl.value = p.target_margin_percent || '30.00';
  if (statusEl) statusEl.value = p.status || 'In Progress';
  if (startEl) startEl.value = p.start_date || '';
  if (endEl) endEl.value = p.end_date || '';

  openModal('modal-edit-project');
}

window.confirmDeleteProject = function(id, projectName) {
  window.showConfirmDeleteModal({
    title: 'Hapus Data Proyek?',
    descriptionHtml: `Apakah Anda yakin ingin menghapus proyek <strong style="color: #101828;">${projectName}</strong>? Data proyek akan diarsipkan dari daftar aktif.`,
    confirmBtnText: 'Hapus Proyek',
    onConfirm: async () => {
      const formData = new FormData();
      formData.append('id', id);

      try {
        const res = await fetch('api/clients.php?action=delete_project', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || 'Data proyek berhasil dihapus!', 'success');
          setTimeout(() => window.location.reload(), 600);
        } else {
          showToast(data.message || 'Gagal menghapus data proyek', 'danger');
        }
      } catch (err) {
        showToast('Gagal menghapus data proyek', 'danger');
      }
    }
  });
};

// --- Freelancer Voucher / Invoice CRUD Helpers ---
window.currentVoucherDataCache = null;

window.openFreelancerVoucherModal = async function(payoutId) {
  try {
    const res = await fetch(`api/expenses.php?action=get_payout_voucher&id=${payoutId}`);
    const data = await res.json();
    if (!data.success || !data.payout) {
      showToast(data.message || 'Gagal memuat voucher pembayaran', 'danger');
      return;
    }

    window.currentVoucherDataCache = data;

    const p = data.payout;
    const f = data.formatted;

    const fnEl = document.getElementById('vch-freelancer-name');
    const fbEl = document.getElementById('vch-freelancer-bank');
    const vnEl = document.getElementById('vch-display-number');
    const vdEl = document.getElementById('vch-display-date');
    const vsEl = document.getElementById('vch-status');
    const pnEl = document.getElementById('vch-project-name');
    const ccEl = document.getElementById('vch-client-company');
    const aiEl = document.getElementById('vch-account-info');
    const ttEl = document.getElementById('vch-task-title');
    const tdEl = document.getElementById('vch-task-desc');
    const acEl = document.getElementById('vch-amount-col');
    const taEl = document.getElementById('vch-total-amount');
    const sfEl = document.getElementById('vch-sign-freelancer');

    if (fnEl) fnEl.innerText = p.freelancer_name || '-';
    if (fbEl) fbEl.innerText = (p.freelancer_bank || 'Bank') + ' - ' + (p.freelancer_account || '-');
    if (vnEl) vnEl.innerText = 'VOUCHER: #' + f.voucher_number;
    if (vdEl) vdEl.innerText = 'Tanggal Bayar: ' + f.payment_date;
    if (vsEl) {
      vsEl.innerText = p.status === 'Paid' ? 'LUNAS (PAID)' : 'PENDING (MENUNGGU TRANSFER)';
      vsEl.style.color = p.status === 'Paid' ? '#10B981' : '#F59E0B';
    }

    if (pnEl) pnEl.innerText = p.project_name || '-';
    if (ccEl) ccEl.innerText = p.client_company || '-';
    if (aiEl) aiEl.innerText = (p.freelancer_bank || 'BCA') + ' - ' + (p.freelancer_account || '-');

    if (ttEl) ttEl.innerText = p.task_description || 'Jasa Produksi Konten / Ads Management';
    if (tdEl) tdEl.innerText = `Pelaksanaan deliverable untuk proyek ${p.project_name} (Klien: ${p.client_company}).`;
    if (acEl) acEl.innerText = f.amount;
    if (taEl) taEl.innerText = f.amount;
    if (sfEl) sfEl.innerText = p.freelancer_name || '(Nama Freelancer)';

    openModal('modal-freelancer-voucher');
  } catch (err) {
    showToast('Gagal memuat voucher fee freelancer', 'danger');
  }
};

window.copyFreelancerVoucherLink = function() {
  if (!window.currentVoucherDataCache || !window.currentVoucherDataCache.payout) {
    showToast('Data voucher belum dimuat', 'warning');
    return;
  }
  const p = window.currentVoucherDataCache.payout;
  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const rawUrl = `${loc.origin}${basePath}/voucher-view?id=${p.id}`;
  const docUrl = typeof window.formatWaClickableUrl === 'function' ? window.formatWaClickableUrl(rawUrl) : rawUrl;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(docUrl).then(() => {
      showToast('Link Voucher Fee berhasil disalin!', 'success');
    }).catch(() => {
      prompt('Salin link voucher fee berikut:', docUrl);
    });
  } else {
    prompt('Salin link voucher fee berikut:', docUrl);
  }
};

window.shareFreelancerVoucherWa = function() {
  if (!window.currentVoucherDataCache || !window.currentVoucherDataCache.payout) {
    showToast('Data voucher belum dimuat', 'warning');
    return;
  }
  const p = window.currentVoucherDataCache.payout;
  const f = window.currentVoucherDataCache.formatted;

  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const rawUrl = `${loc.origin}${basePath}/voucher-view?id=${p.id}`;
  const docUrl = typeof window.formatWaClickableUrl === 'function' ? window.formatWaClickableUrl(rawUrl) : rawUrl;

  const text = 
`*BUKTI PEMBAYARAN & INVOICE FEE TALENTA*
*Kala Media Creative*

Halo *${p.freelancer_name}*, berikut rincian bukti pembayaran honor/fee resmi Anda:

- No. Voucher: #${f.voucher_number}
- Proyek Terkait: ${p.project_name}
- Klien / Brand: ${p.client_company}
- Uraian Tugas: ${p.task_description}
- Tanggal Bayar: ${f.payment_date}
------------------------------------
- TOTAL DIBAYARKAN: *${f.amount}*
- Transfer ke: ${p.freelancer_bank || 'BCA'} - ${p.freelancer_account || '-'}
- Status: LUNAS (PAID)

Lihat & Unduh Dokumen Resmi:
${docUrl}

Terima kasih atas kerja sama dan hasil karya hebat Anda bersama Kala Media Creative.
_Kala Media Creative • Built to Be Seen._`;

  let phone = (p.freelancer_phone || '').replace(/[^0-9]/g, '');
  if (phone.startsWith('0')) {
    phone = '62' + phone.substring(1);
  }

  const waUrl = phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
  window.open(waUrl, '_blank');
};

window.printFreelancerVoucher = function() {
  const printArea = document.getElementById('freelancer-voucher-print-area');
  if (!printArea) return;
  const content = printArea.innerHTML;
  const printWin = window.open('', '', 'width=850,height=900');
  printWin.document.write(`
    <html>
      <head>
        <title>Voucher Pembayaran Freelancer - Kala Media</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style>
          body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #fff; padding: 20px; color: #000; }
          @page { size: A4; margin: 15mm; }
        </style>
      </head>
      <body>${content}</body>
    </html>
  `);
  printWin.document.close();
  printWin.focus();
  setTimeout(() => {
    printWin.print();
    printWin.close();
  }, 400);
};

window.downloadFreelancerVoucherPdf = function() {
  const element = document.getElementById('freelancer-voucher-print-area');
  if (!element) return;
  const flName = (document.getElementById('vch-freelancer-name')?.innerText || 'Freelancer').trim().replace(/[^a-zA-Z0-9_-]/g, '_');
  const opt = {
    margin: [8, 8, 8, 8],
    filename: `Voucher_Fee_${flName}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, logging: false },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };

  if (typeof html2pdf !== 'undefined') {
    showToast('Membuat PDF Voucher Fee Freelancer...', 'info');
    html2pdf().set(opt).from(element).save().then(() => {
      showToast('PDF Voucher Fee berhasil diunduh!', 'success');
    });
  } else {
    window.printFreelancerVoucher();
  }
};

// Open Edit Payout Modal
window.openEditPayoutModal = async function(payoutId) {
  try {
    const res = await fetch(`api/expenses.php?action=get_payout&id=${payoutId}`);
    const data = await res.json();
    if (!data.success) {
      showToast(data.message || 'Gagal memuat data pembayaran', 'danger');
      return;
    }
    const p = data.payout;
    if (document.getElementById('edit-payout-id')) document.getElementById('edit-payout-id').value = p.id;
    if (document.getElementById('edit-payout-name')) document.getElementById('edit-payout-name').value = p.freelancer_name || '';
    if (document.getElementById('edit-payout-phone')) document.getElementById('edit-payout-phone').value = p.freelancer_phone || '';
    if (document.getElementById('edit-payout-bank')) document.getElementById('edit-payout-bank').value = p.freelancer_bank || 'BCA';
    if (document.getElementById('edit-payout-account')) document.getElementById('edit-payout-account').value = p.freelancer_account || '';
    
    const clientSelect = document.getElementById('edit-payout-client-select');
    if (clientSelect) {
      clientSelect.value = p.client_id || '';
      if (typeof window.loadProjectsForClient === 'function') {
        await window.loadProjectsForClient(p.client_id, 'edit-payout-project-select');
      }
      const projSelect = document.getElementById('edit-payout-project-select');
      if (projSelect && p.project_id) {
        projSelect.value = p.project_id;
      }
    }
    
    if (document.getElementById('edit-payout-task')) document.getElementById('edit-payout-task').value = p.task_description || '';
    if (document.getElementById('edit-payout-amount')) document.getElementById('edit-payout-amount').value = window.formatRupiahDisplay(p.amount ? p.amount.toString() : '0');
    if (document.getElementById('edit-payout-status')) document.getElementById('edit-payout-status').value = p.status || 'Pending';

    openModal('modal-edit-payout');
  } catch (err) {
    console.error('Error opening edit payout modal:', err);
    showToast('Gagal memuat data fee freelancer: ' + err.message, 'danger');
  }
};

// Open Edit Ads Modal
window.openEditAdsModal = async function(adsId) {
  try {
    const res = await fetch(`api/expenses.php?action=get_ads&id=${adsId}`);
    const data = await res.json();
    if (!data.success) {
      showToast(data.message || 'Gagal memuat data iklan', 'danger');
      return;
    }
    const a = data.ads;
    if (document.getElementById('edit-ads-id')) document.getElementById('edit-ads-id').value = a.id;
    
    const clientSelect = document.getElementById('edit-ads-client-select');
    if (clientSelect) {
      clientSelect.value = a.client_id || '';
      if (typeof window.loadProjectsForClient === 'function') {
        await window.loadProjectsForClient(a.client_id, 'edit-ads-project-select');
      }
      const projSelect = document.getElementById('edit-ads-project-select');
      if (projSelect && a.project_id) {
        projSelect.value = a.project_id;
      }
    }

    if (document.getElementById('edit-ads-platform')) document.getElementById('edit-ads-platform').value = a.platform || 'Meta Ads';
    if (document.getElementById('edit-ads-account')) document.getElementById('edit-ads-account').value = a.account_id || '';
    if (document.getElementById('edit-ads-amount')) document.getElementById('edit-ads-amount').value = window.formatRupiahDisplay(a.amount ? a.amount.toString() : '0');
    if (document.getElementById('edit-ads-date')) document.getElementById('edit-ads-date').value = a.spent_date || '';
    if (document.getElementById('edit-ads-notes')) document.getElementById('edit-ads-notes').value = a.notes || '';

    openModal('modal-edit-ads');
  } catch (err) {
    console.error('Error opening edit ads modal:', err);
    showToast('Gagal memuat data pengeluaran iklan: ' + err.message, 'danger');
  }
};

// Open Ads Voucher Modal
window.openAdsVoucherModal = async function(adsId) {
  try {
    const res = await fetch(`api/expenses.php?action=get_ads_voucher&id=${adsId}`);
    const data = await res.json();
    if (!data.success) {
      showToast(data.message || 'Gagal memuat voucher ads', 'danger');
      return;
    }
    window.currentAdsVoucherDataCache = data;
    const a = data.ads;
    const f = data.formatted;

    const ccEl = document.getElementById('vch-ads-client-company');
    const cpEl = document.getElementById('vch-ads-client-pic');
    const pnEl = document.getElementById('vch-ads-project-name');
    const vnEl = document.getElementById('vch-ads-number');
    const vdEl = document.getElementById('vch-ads-date');
    const plEl = document.getElementById('vch-ads-platform');
    const acEl = document.getElementById('vch-ads-account-id');
    const ttEl = document.getElementById('vch-ads-item-title');
    const tdEl = document.getElementById('vch-ads-item-desc');
    const amEl = document.getElementById('vch-ads-amount-col');
    const tmEl = document.getElementById('vch-ads-total-amount');

    if (ccEl) ccEl.innerText = a.client_company || '-';
    if (cpEl) cpEl.innerText = a.client_pic || '-';
    if (pnEl) pnEl.innerText = a.project_name || 'Ads Management Campaign';
    if (vnEl) vnEl.innerText = 'VOUCHER: #' + f.voucher_number;
    if (vdEl) vdEl.innerText = 'Tanggal Top-Up: ' + f.spent_date;
    if (plEl) plEl.innerText = a.platform || 'Meta Ads';
    if (acEl) acEl.innerText = a.account_id || '-';
    if (ttEl) ttEl.innerText = `Top-Up Saldo Iklan ${a.platform}`;
    if (tdEl) tdEl.innerText = a.notes || `Alokasi anggaran iklan digital untuk proyek ${a.project_name || a.client_company}`;
    if (amEl) amEl.innerText = f.amount;
    if (tmEl) tmEl.innerText = f.amount;

    openModal('modal-ads-voucher');
  } catch (err) {
    showToast('Gagal memuat voucher top-up ads', 'danger');
  }
};

window.copyAdsVoucherLink = function() {
  if (!window.currentAdsVoucherDataCache || !window.currentAdsVoucherDataCache.ads) {
    showToast('Data voucher iklan belum dimuat', 'warning');
    return;
  }
  const a = window.currentAdsVoucherDataCache.ads;
  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const rawUrl = `${loc.origin}${basePath}/ads-voucher-view?id=${a.id}`;
  const docUrl = typeof window.formatWaClickableUrl === 'function' ? window.formatWaClickableUrl(rawUrl) : rawUrl;

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(docUrl).then(() => {
      showToast('Link Bukti Top-Up Ads berhasil disalin!', 'success');
    }).catch(() => {
      prompt('Salin link voucher ads berikut:', docUrl);
    });
  } else {
    prompt('Salin link voucher ads berikut:', docUrl);
  }
};

window.shareAdsVoucherWa = function() {
  if (!window.currentAdsVoucherDataCache || !window.currentAdsVoucherDataCache.ads) {
    showToast('Data voucher iklan belum dimuat', 'warning');
    return;
  }
  const a = window.currentAdsVoucherDataCache.ads;
  const f = window.currentAdsVoucherDataCache.formatted;

  const loc = window.location;
  const basePath = loc.pathname.replace(/\/(expenses|salaries|invoices|clients|reports|settings|content-calendar|owner-dashboard|admin-dashboard).*$/, '').replace(/\/$/, '');
  const rawUrl = `${loc.origin}${basePath}/ads-voucher-view?id=${a.id}`;
  const docUrl = typeof window.formatWaClickableUrl === 'function' ? window.formatWaClickableUrl(rawUrl) : rawUrl;

  const text = 
`*BUKTI TOP-UP ANGGARAN IKLAN DIGITAL*
*Kala Media Creative*

Halo *${a.client_company}* (PIC: ${a.client_pic || '-'}), berikut rincian bukti transaksi top-up saldo iklan Anda:

- No. Voucher: #${f.voucher_number}
- Platform Iklan: ${a.platform}
- ID Akun Iklan: ${a.account_id || '-'}
- Proyek Terkait: ${a.project_name || '-'}
- Tanggal Transaksi: ${f.spent_date}
- Keterangan: ${a.notes || 'Alokasi saldo iklan campaign'}
------------------------------------
- TOTAL TOP-UP: *${f.amount}*
- Status: BERHASIL (PAID / SUCCESS)

Lihat & Unduh Dokumen Resmi:
${docUrl}

Saldo iklan telah dialokasikan langsung ke akun iklan resmi.
_Kala Media Creative • Built to Be Seen._`;

  let phone = (a.client_phone || '').replace(/[^0-9]/g, '');
  if (phone.startsWith('0')) {
    phone = '62' + phone.substring(1);
  }

  const waUrl = phone ? `https://wa.me/${phone}?text=${encodeURIComponent(text)}` : `https://wa.me/?text=${encodeURIComponent(text)}`;
  window.open(waUrl, '_blank');
};

window.printAdsVoucher = function() {
  const printArea = document.getElementById('ads-voucher-print-area');
  if (!printArea) return;
  const content = printArea.innerHTML;
  const printWin = window.open('', '', 'width=850,height=900');
  printWin.document.write(`
    <html>
      <head>
        <title>Voucher Top-Up Ads - Kala Media</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
        <style>
          body { font-family: 'Plus Jakarta Sans', Arial, sans-serif; background: #fff; padding: 20px; color: #000; }
          @page { size: A4; margin: 15mm; }
        </style>
      </head>
      <body>${content}</body>
    </html>
  `);
  printWin.document.close();
  printWin.focus();
  setTimeout(() => {
    printWin.print();
    printWin.close();
  }, 400);
};

window.downloadAdsVoucherPdf = function() {
  const element = document.getElementById('ads-voucher-print-area');
  if (!element) return;
  const clientName = (document.getElementById('vch-ads-client-company')?.innerText || 'Klien').trim().replace(/[^a-zA-Z0-9_-]/g, '_');
  const opt = {
    margin: [8, 8, 8, 8],
    filename: `Voucher_TopUp_Ads_${clientName}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2, useCORS: true, logging: false },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
  };

  if (typeof html2pdf !== 'undefined') {
    showToast('Membuat PDF Bukti Top-Up Ads...', 'info');
    html2pdf().set(opt).from(element).save().then(() => {
      showToast('PDF Bukti Top-Up Ads berhasil diunduh!', 'success');
    });
  } else {
    window.printAdsVoucher();
  }
};

// Global event delegation for delete button clicks
document.addEventListener('click', (e) => {
  const delBtn = e.target.closest('.btn-delete-ghost');
  if (delBtn && delBtn.dataset.invoiceId) {
    e.preventDefault();
    window.confirmDeleteInvoice(delBtn.dataset.invoiceId, delBtn.dataset.invoiceNumber || '');
  }
});

// --- Content Calendar Helper Functions ---
window.setModalContentColor = function(hex) {
  const input = document.getElementById('cp-color-hex');
  const dot = document.getElementById('modal-content-color-dot');
  if (input) input.value = hex;
  if (dot) dot.style.background = hex;
};

window.updateAssetUrlButton = function(val) {
  const btn = document.getElementById('cp-btn-open-asset');
  if (!btn) return;
  if (val && (val.startsWith('http://') || val.startsWith('https://'))) {
    btn.href = val;
    btn.style.display = 'inline-flex';
  } else {
    btn.style.display = 'none';
  }
};

window.openCreateContentModal = function(dateStr = '', timeStr = '10:00') {
  const form = document.getElementById('form-content-planner');
  if (form) form.reset();

  document.getElementById('cp-id').value = '';
  document.getElementById('modal-content-title').textContent = '+ Tambah Jadwal Konten';
  
  const delBtn = document.getElementById('cp-btn-delete');
  if (delBtn) delBtn.style.display = 'none';

  if (dateStr) {
    document.getElementById('cp-publish-date').value = dateStr;
  } else {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('cp-publish-date').value = today;
  }

  if (timeStr) {
    document.getElementById('cp-publish-time').value = timeStr;
  }

  setModalContentColor('#3B82F6');
  updateAssetUrlButton('');
  openModal('modal-content-planner');
};

window.openEditContentModal = async function(contentId) {
  try {
    const res = await fetch(`api/content.php?action=get_details&id=${contentId}`);
    const data = await res.json();
    if (!data.success || !data.content) {
      showToast(data.message || 'Gagal memuat detail konten', 'danger');
      return;
    }

    const c = data.content;
    document.getElementById('cp-id').value = c.id;
    document.getElementById('modal-content-title').textContent = 'Edit Jadwal Konten';
    document.getElementById('cp-title').value = c.title || '';
    document.getElementById('cp-client-id').value = c.client_id || '';
    
    // Trigger project dropdown update for client
    if (typeof loadProjectsForClient === 'function') {
      loadProjectsForClient(c.client_id, 'cp-project-id');
    }
    setTimeout(() => {
      document.getElementById('cp-project-id').value = c.project_id || '';
    }, 100);

    document.getElementById('cp-platform').value = c.platform || 'Instagram';
    document.getElementById('cp-content-type').value = c.content_type || 'Reels / Video';
    document.getElementById('cp-publish-date').value = c.publish_date || '';
    document.getElementById('cp-publish-time').value = c.publish_time || '10:00';
    document.getElementById('cp-status').value = c.status || 'Draft';
    document.getElementById('cp-assignee-id').value = c.assignee_id || '';
    document.getElementById('cp-asset-url').value = c.asset_url || '';
    document.getElementById('cp-notes').value = c.notes || '';

    setModalContentColor(c.color_hex || '#3B82F6');
    updateAssetUrlButton(c.asset_url || '');

    const delBtn = document.getElementById('cp-btn-delete');
    if (delBtn) delBtn.style.display = 'inline-flex';

    openModal('modal-content-planner');
  } catch (err) {
    console.error('Error fetching content details:', err);
    showToast('Gagal memuat detail konten', 'danger');
  }
};

window.submitContentForm = async function() {
  const form = document.getElementById('form-content-planner');
  if (!form) return;

  const formData = new FormData(form);
  const id = formData.get('id');
  const action = id ? 'update' : 'create';

  const submitBtn = document.getElementById('cp-btn-submit');
  const origText = submitBtn ? submitBtn.innerHTML : '';
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span>Menyimpan...</span>';
  }

  try {
    const res = await fetch(`api/content.php?action=${action}`, {
      method: 'POST',
      body: formData
    });
    const result = await res.json();

    if (result.success) {
      showToast(result.message || 'Konten berhasil disimpan!', 'success');
      closeModal('modal-content-planner');
      if (typeof window.refreshContentCalendar === 'function') {
        window.refreshContentCalendar();
      }
    } else {
      showToast(result.message || 'Gagal menyimpan konten', 'danger');
    }
  } catch (err) {
    console.error('Submit error:', err);
    showToast('Terjadi kesalahan saat menyimpan konten', 'danger');
  } finally {
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.innerHTML = origText;
    }
  }
};

window.deleteContentFromModal = function() {
  const id = document.getElementById('cp-id').value;
  const title = document.getElementById('cp-title').value || 'Jadwal Konten';
  if (!id) return;

  window.showConfirmDeleteModal({
    title: 'Hapus Jadwal Konten?',
    descriptionHtml: `Apakah Anda yakin ingin menghapus jadwal konten <strong style="color: #101828;">"${title}"</strong>? Data yang dihapus tidak akan ditampilkan lagi di kalender.`,
    confirmBtnText: 'Hapus Konten',
    onConfirm: async () => {
      const formData = new FormData();
      formData.append('id', id);

      try {
        const res = await fetch('api/content.php?action=delete', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message || 'Konten berhasil dihapus!', 'success');
          closeModal('modal-content-planner');
          if (typeof window.refreshContentCalendar === 'function') {
            window.refreshContentCalendar();
          }
        } else {
          showToast(data.message || 'Gagal menghapus konten', 'danger');
        }
      } catch (err) {
        showToast('Gagal menghapus konten', 'danger');
      }
    }
  });
};

window.openExportContentModal = function() {
  // Sync current calendar month into input if available
  const monthInput = document.getElementById('export-month-input');
  if (monthInput) {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    if (!monthInput.value) monthInput.value = `${yyyy}-${mm}`;
  }
  openModal('modal-export-content-pdf');
};

window.toggleExportRangeMode = function(mode) {
  const monthWrap = document.getElementById('export-month-wrap');
  const customWrap = document.getElementById('export-custom-date-wrap');
  const labelMonth = document.getElementById('label-export-mode-month');
  const labelCustom = document.getElementById('label-export-mode-custom');

  if (mode === 'month') {
    if (monthWrap) monthWrap.style.display = 'block';
    if (customWrap) customWrap.style.display = 'none';
    if (labelMonth) labelMonth.style.borderColor = '#2563EB';
    if (labelCustom) labelCustom.style.borderColor = '#D0D5DD';
  } else {
    if (monthWrap) monthWrap.style.display = 'none';
    if (customWrap) customWrap.style.display = 'flex';
    if (labelCustom) labelCustom.style.borderColor = '#2563EB';
  }
};

// Register PWA Service Worker for mobile app capability
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch(err => {
      console.log('PWA ServiceWorker registration failed: ', err);
    });
  });
}

