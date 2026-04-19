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
    } catch (err) {
        alert('Error: ' + err.message);
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
    ['new-name','new-price','new-desc','new-emoji'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    openModal('add-product-modal');
}

async function submitAddProduct() {
    const name     = document.getElementById('new-name')?.value.trim()   ?? '';
    const price    = parseInt(document.getElementById('new-price')?.value ?? '0');
    const category = document.getElementById('new-category')?.value       ?? '';
    const status   = document.getElementById('new-status')?.value         ?? 'Available';
    const emoji    = document.getElementById('new-emoji')?.value           ?? '';
    const desc     = document.getElementById('new-desc')?.value.trim()    ?? '';

    if (!name || !price) { alert('Name and price are required.'); return; }

    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action:'add', name, category, price, status, emoji, desc }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        closeModal('add-product-modal');
        location.reload(); // reload to show new row from DB
    } catch (err) {
        alert('Error adding product: ' + err.message);
    }
}

function openEditProduct(p) {
    document.getElementById('edit-id').value       = p.id;
    document.getElementById('edit-name').value     = p.name;
    document.getElementById('edit-category').value = p.category;
    document.getElementById('edit-price').value    = p.price;
    document.getElementById('edit-status').value   = p.status;
    document.getElementById('edit-emoji').value    = p.emoji ?? '';
    document.getElementById('edit-desc').value     = p.desc  ?? '';
    openModal('edit-product-modal');
}

async function submitEditProduct() {
    const id       = parseInt(document.getElementById('edit-id')?.value    ?? '0');
    const name     = document.getElementById('edit-name')?.value.trim()    ?? '';
    const category = document.getElementById('edit-category')?.value       ?? '';
    const price    = parseInt(document.getElementById('edit-price')?.value ?? '0');
    const status   = document.getElementById('edit-status')?.value         ?? 'Available';
    const emoji    = document.getElementById('edit-emoji')?.value           ?? '';
    const desc     = document.getElementById('edit-desc')?.value.trim()    ?? '';

    if (!id || !name || !price) { alert('Invalid data.'); return; }

    try {
        const res  = await fetch('api/products.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ action:'edit', id, name, category, price, status, emoji, desc }),
        });
        const data = await res.json();
        if (!data.success) throw new Error(data.error);

        closeModal('edit-product-modal');
        location.reload();
    } catch (err) {
        alert('Error updating product: ' + err.message);
    }
}

async function deleteProduct(id) {
    if (!confirm('Delete this product? This cannot be undone.')) return;

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
    } catch (err) {
        alert('Error deleting product: ' + err.message);
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
        // Flip the toggle button icon
        const toggleBtn = document.querySelector(`#product-row-${id} button[onclick*="toggleProduct"] .material-symbols-outlined`);
        if (toggleBtn) toggleBtn.textContent = isAvail ? 'visibility_off' : 'visibility';
    } catch (err) {
        alert('Error toggling product: ' + err.message);
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
