/**
 * user.js — front-end glue for the user-side pages.
 *
 * Loaded by user-home.php and user-orders.php.
 * Talks to api/order.php via fetch().
 */

'use strict';

// ─────────────────────────────────────────────
//  Cart state (used by user-home.php only)
// ─────────────────────────────────────────────

const cart = new Map(); // productId -> { id, name, price, qty }

function fmtMoney(n) {
    return 'EGP ' + Number(n || 0).toLocaleString();
}

function renderCart() {
    const wrap   = document.getElementById('cartItems');
    const empty  = document.getElementById('cartEmpty');
    const totalEl = document.getElementById('totalPrice');
    if (!wrap || !totalEl) return;

    // Clear everything except the placeholder text
    wrap.querySelectorAll('.cart-item').forEach(n => n.remove());

    if (cart.size === 0) {
        if (empty) empty.style.display = '';
        totalEl.textContent = fmtMoney(0);
        return;
    }
    if (empty) empty.style.display = 'none';

    let total = 0;
    for (const item of cart.values()) {
        total += item.price * item.qty;
        const row = document.createElement('div');
        row.className = 'cart-item';
        row.innerHTML = `
            <div>
                <span class="cart-item-name">${item.name}</span><br>
                <span class="cart-item-price">EGP ${item.price} each</span>
            </div>
            <div class="qty-controls">
                <button class="qty-btn" type="button" aria-label="Decrease">&minus;</button>
                <span class="qty-value">${item.qty}</span>
                <button class="qty-btn add" type="button" aria-label="Increase">+</button>
            </div>
        `;
        const decBtn = row.querySelector('.qty-btn:not(.add)');
        const incBtn = row.querySelector('.qty-btn.add');
        decBtn.addEventListener('click', () => changeQty(item.id, -1));
        incBtn.addEventListener('click', () => changeQty(item.id, +1));
        wrap.appendChild(row);
    }
    totalEl.textContent = fmtMoney(total);
}

function addToCart(card) {
    const id    = parseInt(card.dataset.id, 10);
    const name  = card.dataset.name;
    const price = parseInt(card.dataset.price, 10);
    if (!id) return;

    const existing = cart.get(id);
    if (existing) {
        existing.qty += 1;
    } else {
        cart.set(id, { id, name, price, qty: 1 });
    }
    renderCart();
}

function changeQty(id, delta) {
    const item = cart.get(id);
    if (!item) return;
    item.qty += delta;
    if (item.qty <= 0) cart.delete(id);
    renderCart();
}

// ─────────────────────────────────────────────
//  Category filter for the product grid
// ─────────────────────────────────────────────

function filterCategory(btn, category) {
    btn.closest('.category-tabs')
       .querySelectorAll('.category-tab')
       .forEach(t => t.classList.remove('active'));
    btn.classList.add('active');

    document.querySelectorAll('#productGrid .product-card').forEach(card => {
        card.style.display =
            (category === 'all' || card.dataset.category === category) ? '' : 'none';
    });
}

// ─────────────────────────────────────────────
//  AJAX — place / cancel / reorder
// ─────────────────────────────────────────────

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

async function postOrder(payload) {
    const res = await fetch('api/order.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(payload),
    });
    return res.json();
}

async function submitOrder() {
    const btn   = document.getElementById('confirmOrderBtn');
    const room  = document.getElementById('orderRoom').value;
    const notes = document.getElementById('orderNotes').value;

    if (cart.size === 0) {
        alert('Your cart is empty.');
        return;
    }
    if (!room) {
        alert('Please choose a delivery room.');
        return;
    }

    btn.disabled = true;
    btn.style.opacity = '0.6';

    try {
        const items = [...cart.values()].map(i => ({ id: i.id, qty: i.qty }));
        const data  = await postOrder({ action: 'place', items, room, notes });

        if (!data.success) {
            showToast(data.error || 'Failed to place order', 'error');
            return;
        }

        showToast(`Order #${data.id} placed! Total: EGP ${data.total}`, 'success');
        cart.clear();
        renderCart();
        setTimeout(() => {
            window.location.href = 'user-orders.php';
        }, 1500);
    } catch (err) {
        showToast('Network error placing order', 'error');
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.style.opacity = '';
    }
}

async function cancelMyOrder(id, btn) {
    const confirmed = await showConfirm('Cancel Order', 'Are you sure you want to cancel this order?');
    if (!confirmed) return;
    
    if (btn) { btn.disabled = true; btn.style.opacity = '0.6'; }

    try {
        const data = await postOrder({ action: 'cancel', id });
        if (!data.success) {
            showToast(data.error || 'Could not cancel', 'error');
            if (btn) { btn.disabled = false; btn.style.opacity = ''; }
            return;
        }
        
        showToast('Order cancelled successfully', 'success');

        // Repaint just this row's status badge + remove the cancel button
        const row = document.getElementById(`row-${id}`);
        if (row) {
            const badge = row.querySelector('.badge');
            if (badge) {
                badge.className = 'badge badge-unavailable';
                badge.textContent = 'Cancelled';
            }
            const cell = row.cells[row.cells.length - 1];
            if (cell) cell.innerHTML =
                '<span style="color: var(--on-surface-variant); font-size:0.8125rem;">&mdash;</span>';
        }
    } catch (err) {
        showToast('Network error', 'error');
        console.error(err);
        if (btn) { btn.disabled = false; btn.style.opacity = ''; }
    }
}

async function reorderLast(id) {
    if (!id) return;
    try {
        const data = await postOrder({ action: 'reorder', id });
        if (!data.success) {
            showToast(data.error || 'Could not reorder', 'error');
            return;
        }
        if (!data.items || data.items.length === 0) {
            showToast('None of those items are available right now.', 'error');
            return;
        }
        cart.clear();
        for (const it of data.items) {
            cart.set(it.id, { id: it.id, name: it.name, price: it.price, qty: it.qty });
        }
        renderCart();
        showToast('Items added to cart', 'success');
        document.querySelector('.order-cart')?.scrollIntoView({ behavior: 'smooth' });
    } catch (err) {
        showToast('Network error', 'error');
        console.error(err);
    }
}
