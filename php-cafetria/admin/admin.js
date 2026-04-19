/**
 * admin.js — Shared JS for all Cafetria admin pages
 *
 * Covers:
 *   - Dashboard: deliver-order AJAX
 *   - Orders: client-side search/filter, view toggle, deliver/cancel AJAX
 *   - Products: client-side search/filter, add/edit/delete/toggle AJAX
 *   - Modal helpers
 */

'use strict';

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'check_circle' : (type === 'error' ? 'error' : 'info');
    
    toast.innerHTML = `
        <span class="material-symbols-outlined toast-icon">${icon}</span>
        <span class="toast-message">${message}</span>
    `;

    container.appendChild(toast);

    // Remove after 4 seconds
    setTimeout(() => {
        toast.classList.add('toast-out');
        toast.addEventListener('animationend', () => toast.remove());
    }, 4000);
}

function showConfirm(title, message) {
    return new Promise((resolve) => {
        const overlay = document.getElementById('confirm-modal-overlay');
        const titleEl = document.getElementById('confirm-title');
        const msgEl   = document.getElementById('confirm-message');
        const okBtn   = document.getElementById('confirm-ok-btn');
        const canBtn  = document.getElementById('confirm-cancel-btn');

        if (!overlay || !okBtn || !canBtn) return resolve(false);

        titleEl.textContent = title;
        msgEl.textContent   = message;
        overlay.classList.add('open');

        const cleanup = (result) => {
            overlay.classList.remove('open');
            okBtn.removeEventListener('click', onOk);
            canBtn.removeEventListener('click', onCancel);
            resolve(result);
        };

        const onOk = () => cleanup(true);
        const onCancel = () => cleanup(false);

        okBtn.addEventListener('click', onOk);
        canBtn.addEventListener('click', onCancel);
    });
}


// ══════════════════════════════════════════════════════════
//  ORDERS — AJAX ACTIONS
// ══════════════════════════════════════════════════════════

async function deliverOrder(id, btn) {
    await _updateOrder('deliver', id, btn, 'Delivered');
}

async function cancelOrder(id, btn) {
    await _updateOrder('cancel', id, btn, 'Cancelled');
}

async function _updateOrder(action, id, btn, newStatus) {
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

    try {
        const res  = await fetch('api/orders.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action, id }),
        });
        const data = await res.json();

        if (!data.success) throw new Error(data.error ?? 'Update failed');

        // Update the status badge in the row without a full reload
        const row = btn?.closest('tr') ?? btn?.closest('.order-card');
        if (row) {
            // Update badge
            const badge = row.querySelector('.badge');
            if (badge) {
                badge.className = `badge badge-${newStatus.toLowerCase()}`;
                badge.textContent = newStatus;
            }
            // Remove action buttons since order is no longer Processing
            const actionsCell = row.querySelector('td:last-child, .order-card-body > div:last-child');
            if (actionsCell) actionsCell.innerHTML = '';
        }
        showToast(`Order ${action}ed successfully`, 'success');
    } catch (err) {
        showToast('Error: ' + err.message, 'error');
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    }
}


// ── Client-side search / filter ───────────────────────────────────────────

function filterOrdersTable() {
    const q = document.getElementById('orders-search')?.value.toLowerCase() ?? '';

    document.querySelectorAll('#orders-table-body tr[data-search]').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
    });
    document.querySelectorAll('#orders-card-view .order-card[data-search]').forEach(card => {
        card.style.display = card.dataset.search.includes(q) ? '' : 'none';
    });
}

// ── View toggle (table ↔ card) ────────────────────────────────────────────

function setOrderView(view) {
    const tableView = document.getElementById('orders-table-view');
    const cardView  = document.getElementById('orders-card-view');
    const tableBtn  = document.getElementById('view-table-btn');
    const cardBtn   = document.getElementById('view-card-btn');

    if (!tableView || !cardView) return;

    tableView.style.display = view === 'table' ? '' : 'none';
    cardView.style.display  = view === 'card'  ? '' : 'none';
    tableBtn?.classList.toggle('active', view === 'table');
    cardBtn?.classList.toggle('active',  view === 'card');
}

// ══════════════════════════════════════════════════════════
//  PRODUCTS — AJAX ACTIONS
// ══════════════════════════════════════════════════════════

function openAddProduct() {
    ['new-name','new-price','new-desc','new-image'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    openModal('add-product-modal');
}

async function submitAddProduct() {
    const name     = document.getElementById('new-name')?.value.trim()   ?? '';
    const price    = parseInt(document.getElementById('new-price')?.value ?? '0');
    const category_id = parseInt(document.getElementById('new-category-id')?.value ?? '0');
    const status   = document.getElementById('new-status')?.value         ?? 'Available';
    const image    = document.getElementById('new-image')?.value           ?? '';
    const desc     = document.getElementById('new-desc')?.value.trim()    ?? '';

    if (!name || !price) { showToast('Name and price are required.', 'error'); return; }

    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action:'add', name, category_id, price, status, image, desc }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        closeModal('add-product-modal');
        showToast('Product added successfully', 'success');
        setTimeout(() => location.reload(), 1000);
    } catch (err) {
        showToast('Error adding product: ' + err.message, 'error');
    }
}

function openEditProduct(p) {
    document.getElementById('edit-id').value          = p.id;
    document.getElementById('edit-name').value        = p.name;
    document.getElementById('edit-category-id').value = p.category_id;
    document.getElementById('edit-price').value       = p.price;
    document.getElementById('edit-status').value   = p.status;
    document.getElementById('edit-image').value       = p.image ?? '';
    document.getElementById('edit-desc').value     = p.desc  ?? '';
    openModal('edit-product-modal');
}

async function submitEditProduct() {
    const id       = parseInt(document.getElementById('edit-id')?.value    ?? '0');
    const name     = document.getElementById('edit-name')?.value.trim()    ?? '';
    const category_id = parseInt(document.getElementById('edit-category-id')?.value ?? '0');
    const price    = parseInt(document.getElementById('edit-price')?.value ?? '0');
    const status   = document.getElementById('edit-status')?.value         ?? 'Available';
    const image    = document.getElementById('edit-image')?.value           ?? '';
    const desc     = document.getElementById('edit-desc')?.value.trim()    ?? '';

    if (!id || !name || !price) { showToast('Invalid data.', 'error'); return; }

    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action:'edit', id, name, category_id, price, status, image, desc }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        closeModal('edit-product-modal');
        showToast('Product updated successfully', 'success');
        setTimeout(() => location.reload(), 1000);
    } catch (err) {
        showToast('Error updating product: ' + err.message, 'error');
    }
}

async function deleteProduct(id) {
    const confirmed = await showConfirm('Delete Product', 'Are you sure you want to delete this product? This cannot be undone.');
    if (!confirmed) return;

    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'delete', id }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        // Remove both the table row and the grid card without reloading
        document.getElementById(`product-row-${id}`)?.remove();
        document.getElementById(`product-card-${id}`)?.remove();

        // Update count badge
        const badge = document.getElementById('products-count');
        if (badge) {
            const current = parseInt(badge.textContent) || 0;
            badge.textContent = `${Math.max(0, current - 1)} products`;
        }
        showToast('Product deleted successfully', 'success');
    } catch (err) {
        showToast('Error deleting product: ' + err.message, 'error');
    }
}

async function toggleProduct(id) {
    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action: 'toggle', id }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        const newStatus = data.newStatus;
        const isAvail   = newStatus === 'Available';

        // Update badge in the table row
        const badge = document.querySelector(`#product-row-${id} .status-badge`);
        if (badge) {
            badge.className  = `badge ${isAvail ? 'badge-available' : 'badge-unavailable'} status-badge`;
            badge.textContent = newStatus;
        }
        // Update badge in the grid card
        const cardBadge = document.querySelector(`#product-card-${id} .badge`);
        if (cardBadge) {
            cardBadge.className  = `badge ${isAvail ? 'badge-available' : 'badge-unavailable'}`;
            cardBadge.textContent = newStatus;
        }
        // Flip the toggle button icon in all views (table row & grid card)
        document.querySelectorAll(`[onclick*="toggleProduct(${id})"] .material-symbols-outlined`).forEach(icon => {
            icon.textContent = isAvail ? 'visibility_off' : 'visibility';
        });
        showToast(`Product is now ${newStatus}`, 'success');
    } catch (err) {
        showToast('Error toggling product: ' + err.message, 'error');
    }
}

// ── Client-side search for products ──────────────────────────────────────

function filterProductsTable() {
    const q = document.getElementById('products-search')?.value.toLowerCase() ?? '';

    document.querySelectorAll('#products-table-body tr[data-name]').forEach(row => {
        const match = row.dataset.name.includes(q) || row.dataset.category.includes(q);
        row.style.display = match ? '' : 'none';
    });
}


// ══════════════════════════════════════════════════════════
//  MODAL HELPERS
// ══════════════════════════════════════════════════════════

function openModal(id) {
    document.getElementById(id)?.classList.add('open');
}

function closeModal(id) {
    document.getElementById(id)?.classList.remove('open');
}

// Close modal when clicking the dark overlay
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
    }
});

// ══════════════════════════════════════════════════════════
//  UTILITIES
// ══════════════════════════════════════════════════════════

function escHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
