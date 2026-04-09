// ─────────────────────────────────────────────
// Cafetria — UI Interactivity
// ─────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {

    // ── 1. Navbar Scroll Glass Effect ────────
    const navbar = document.querySelector('.navbar');
    if (navbar) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 20) {
                navbar.style.background = 'rgba(255, 244, 240, 0.96)';
                navbar.style.boxShadow = '0 4px 24px rgba(75, 36, 10, 0.06)';
            } else {
                navbar.style.background = 'rgba(255, 244, 240, 0.8)';
                navbar.style.boxShadow = 'none';
            }
        });
    }

    // ── 2. Category Tab Switching ────────────
    const tabs = document.querySelectorAll('.category-tab');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tab.closest('.category-tabs')
               .querySelectorAll('.category-tab')
               .forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
        });
    });

    // ── 3. Button Press Feedback ─────────────
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(btn => {
        btn.addEventListener('mousedown', () => {
            btn.style.transform = 'scale(0.97)';
        });
        btn.addEventListener('mouseup', () => {
            btn.style.transform = '';
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = '';
        });
    });

    // ── 4. Staggered Card Animations ─────────
    const cards = document.querySelectorAll('.stat-card, .product-card, .table-container, .form-container');
    cards.forEach((card, i) => {
        card.style.opacity = '0';
        card.style.animation = `fadeIn 0.5s ease forwards`;
        card.style.animationDelay = `${i * 0.08}s`;
    });

    // ── 5. Sidebar Active State Highlight ────
    const sidebarLinks = document.querySelectorAll('.admin-sidebar-nav a');
    const currentPage = window.location.pathname.split('/').pop();
    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage) {
            link.classList.add('active');
        }
    });

});

// ── Global: Expandable Table Row Toggle ──────
function toggleRow(id) {
    const row = document.getElementById(id);
    if (!row) return;

    row.classList.toggle('active');

    const btn = row.previousElementSibling.querySelector('.expand-btn');
    if (btn) {
        btn.textContent = row.classList.contains('active') ? '−' : '+';
    }
}
