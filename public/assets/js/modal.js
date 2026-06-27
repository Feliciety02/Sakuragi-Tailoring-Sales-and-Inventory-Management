/* ═══════════════════════════════════════════════════════════════
   Sakuragi Modal System — Reusable modal component
   ═══════════════════════════════════════════════════════════════
   Usage:
     const m = new Modal('myModal');
     m.open();
     m.close();
   ═══════════════════════════════════════════════════════════════ */

class Modal {
  constructor(elementOrId) {
    this.el = typeof elementOrId === 'string'
      ? document.getElementById(elementOrId)
      : elementOrId;

    if (!this.el) throw new Error('Modal element not found');

    this._onKeyDown = this._onKeyDown.bind(this);
    this._onBackdrop = this._onBackdrop.bind(this);
    this._previousActive = null;
    this._focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
  }

  open() {
    this._previousActive = document.activeElement;
    this.el.classList.add('open');
    this.el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', this._onKeyDown);
    this._trapFocus();
    this.el.dispatchEvent(new CustomEvent('modal:open'));
  }

  close() {
    this.el.classList.remove('open');
    this.el.style.display = 'none';
    document.body.style.overflow = '';
    document.removeEventListener('keydown', this._onKeyDown);
    if (this._previousActive && this._previousActive.focus) {
      this._previousActive.focus();
    }
    this.el.dispatchEvent(new CustomEvent('modal:close'));
  }

  toggle() {
    if (this.el.classList.contains('open')) {
      this.close();
    } else {
      this.open();
    }
  }

  setContent(html) {
    const body = this.el.querySelector('.modal-body');
    if (body) body.innerHTML = html;
  }

  _onKeyDown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      this.close();
    }
    if (e.key === 'Tab') {
      this._handleTab(e);
    }
  }

  _onBackdrop(e) {
    if (e.target === this.el) {
      this.close();
    }
  }

  _trapFocus() {
    const focusable = this.el.querySelectorAll(this._focusableSelector);
    if (focusable.length > 0) {
      setTimeout(function() { focusable[0].focus(); }, 50);
    }
  }

  _handleTab(e) {
    const focusable = Array.from(this.el.querySelectorAll(this._focusableSelector));
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
}

/* ── Auto-init: wire backdrop click on all .modal-overlay elements ── */
document.addEventListener('click', function(e) {
  const overlay = e.target.closest('.modal-overlay');
  if (overlay && e.target === overlay) {
    const id = overlay.id;
    if (id) {
      const m = new Modal(id);
      m.close();
    }
  }
});

/* ── Close buttons with data-modal-close ── */
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-modal-close]');
  if (!btn) return;
  const targetId = btn.getAttribute('data-modal-close');
  if (targetId) {
    const m = new Modal(targetId);
    m.close();
  }
});
