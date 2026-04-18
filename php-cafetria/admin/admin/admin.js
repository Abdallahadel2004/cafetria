/**
 * admin.js — Shared JS for all Cafetria admin pages
 *
 * Covers:
 *   - Claude API calls (browser-side, no proxy)
 *   - Dashboard: AI insight generation, deliver-order AJAX
 *   - Orders: client-side search/filter, view toggle, deliver/cancel AJAX
 *   - Products: client-side search/filter, add/edit/delete/toggle AJAX, AI suggestions
 *   - Modal helpers
 */

'use strict';

// ══════════════════════════════════════════════════════════
//  CLAUDE API  (browser-side, key injected by your server
//  or set here directly for development)
// ══════════════════════════════════════════════════════════

async function callClaude(prompt, system = '') {
    const body = {
        model:      'claude-sonnet-4-20250514',
        max_tokens: 1000,
        messages:   [{ role: 'user', content: prompt }],
    };
    if (system) body.system = system;

    const res = await fetch('https://api.anthropic.com/v1/messages', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body),
    });

    if (!res.ok) throw new Error(`Claude API error: ${res.status}`);
    const data = await res.json();
    return data.content?.[0]?.text ?? 'No response.';
}

// ══════════════════════════════════════════════════════════
//  DASHBOARD
// ══════════════════════════════════════════════════════════

/**
 * generateDailyInsight()
 * Uses DASHBOARD_DATA (injected by admin-dashboard.php) to build
 * a context string and ask Claude for a daily insight.
 */
async function generateDailyInsight() {
    const box = document.getElementById('ai-insight-body');
    if (!box) return;

    box.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="loading-text">Generating AI insight…</p></div>';

    const d = window.DASHBOARD_DATA ?? {};
    const topList = (d.topProducts ?? []).join(', ') || 'N/A';

    const prompt =
        `You are an AI assistant for a workplace cafeteria called Cafetria (Cairo, Egypt).
Today's snapshot:
- Total orders: ${d.totalOrders ?? 0}
- Orders still processing: ${d.processing ?? 0}
- Revenue: EGP ${d.revenue ?? 0}
- Top-selling products: ${topList}
- Active products: ${d.activeProducts ?? 0} (${d.unavailableProducts ?? 0} unavailable)

Write a concise, friendly 2–3 sentence daily insight for the admin.
Include one specific, actionable recommendation.
Keep it under 80 words. Be direct and data-driven.`;

    try {
        const text = await callClaude(prompt);
        box.innerHTML = `
            <div class="ai-insight">
                <div class="ai-insight-header">
                    <span class="material-symbols-outlined">smart_toy</span>
                    Claude AI · Today's Summary
                </div>
                <p>${escHtml(text)}</p>
            </div>`;
    } catch (err) {
        box.innerHTML = `<p style="padding:1.25rem;color:var(--error);font-size:0.85rem">
            Failed to load AI insight. Check your API key.<br><small>${err.message}</small></p>`;
    }
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
    } catch (err) {
        alert('Error: ' + err.message);
        if (btn) { btn.disabled = false; btn.style.opacity = '1'; }
    }
}

// ── AI analysis for the Orders page ───────────────────────────────────────

async function runOrdersAI() {
    const box     = document.getElementById('orders-ai-box');
    const content = document.getElementById('orders-ai-content');
    if (!box || !content) return;

    box.style.display = 'block';
    content.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="loading-text">Running AI analysis…</p></div>';

    const s = window.ORDERS_STATS ?? {};
    const prompt =
        `Analyze today's cafeteria order data and give 3 bullet-point insights:
- Total orders: ${s.total ?? 0}
- Processing:   ${s.processing ?? 0}
- Delivered:    ${s.delivered ?? 0}
- Cancelled:    ${s.cancelled ?? 0}
- Revenue:      EGP ${s.revenue ?? 0}

Each bullet (•) must be under 20 words and actionable. Plain text only, no markdown.`;

    try {
        const text = await callClaude(prompt);
        content.innerHTML = `
            <div class="ai-insight-header">
                <span class="material-symbols-outlined">smart_toy</span>Orders AI Analysis
            </div>
            <p style="white-space:pre-line">${escHtml(text)}</p>`;
    } catch (err) {
        content.innerHTML = `<p style="color:var(--error)">Error: ${err.message}</p>`;
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

// ── AI product suggestions ────────────────────────────────────────────────

async function runProductsAI() {
    const box     = document.getElementById('products-ai-box');
    const content = document.getElementById('products-ai-content');
    if (!box || !content) return;

    box.style.display = 'block';
    content.innerHTML = '<div class="loading-state"><div class="spinner"></div><p class="loading-text">AI analysing your menu…</p></div>';

    const catalog = (window.PRODUCTS_CATALOG ?? [])
        .map(p => `${p.name} (${p.category}, EGP ${p.price}, ${p.orders} orders, ${p.status})`)
        .join('\n');

    const prompt =
        `You are a menu consultant for a workplace cafeteria in Cairo, Egypt.
Current catalog:
${catalog}

Give exactly 3 bullet points (•):
1. Which existing product to promote and why (use the order numbers)
2. One new product that would suit a workplace setting in Egypt
3. One pricing adjustment with a specific suggestion

Under 120 words total. Plain text, no markdown formatting.`;

    try {
        const text = await callClaude(prompt);
        content.innerHTML = `
            <div class="ai-insight-header">
                <span class="material-symbols-outlined">smart_toy</span>AI Menu Consultant
            </div>
            <p style="white-space:pre-line">${escHtml(text)}</p>`;
    } catch (err) {
        content.innerHTML = `<p style="color:var(--error)">Error: ${err.message}</p>`;
    }
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
