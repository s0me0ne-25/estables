/*
 * eStables demo: small progressive enhancements on top of Bootstrap.
 * - colour theme switcher (light / dark / auto) persisted in localStorage
 * - grid / list toggle for the catalogue
 * - GLightbox and thumbnail switching on the item page
 * - simulated administrator session (footer link, login form, logout)
 * - admin helpers: password visibility, row selection with bulk actions
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const THEME_KEY = 'estables-theme';
  // Demo only: the real session will live on the server.
  const ADMIN_KEY = 'estables-demo-admin';

  const readStored = (key) => {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  };
  const writeStored = (key, value) => {
    try { localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ }
  };
  const removeStored = (key) => {
    try { localStorage.removeItem(key); } catch (e) { /* storage unavailable */ }
  };

  const systemTheme = () =>
    window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';

  const applyTheme = (choice) => {
    const theme = choice === 'auto' || !choice ? systemTheme() : choice;
    document.documentElement.setAttribute('data-bs-theme', theme);
    document.querySelectorAll('[data-es-theme-value]').forEach((el) => {
      const active = el.dataset.esThemeValue === (choice || 'auto');
      el.classList.toggle('active', active);
      el.setAttribute('aria-pressed', String(active));
    });
    const icon = document.querySelector('[data-es-theme-icon]');
    if (icon) {
      icon.className = 'bi ' + (theme === 'dark' ? 'bi-moon-stars' : 'bi-sun');
    }
  };

  // Apply as early as possible to avoid a flash of the wrong theme.
  applyTheme(readStored(THEME_KEY) || 'auto');

  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    const stored = readStored(THEME_KEY) || 'auto';
    if (stored === 'auto') applyTheme('auto');
  });

  document.addEventListener('DOMContentLoaded', () => {
    applyTheme(readStored(THEME_KEY) || 'auto');

    document.querySelectorAll('[data-es-theme-value]').forEach((el) => {
      el.addEventListener('click', () => {
        const value = el.dataset.esThemeValue;
        writeStored(THEME_KEY, value);
        applyTheme(value);
      });
    });

    // Simulated administrator session.
    // Opening any admin page counts as being logged in.
    if (document.body.dataset.esArea === 'admin') writeStored(ADMIN_KEY, '1');
    const isAdmin = readStored(ADMIN_KEY) === '1' || document.body.dataset.esArea === 'admin';
    document.querySelectorAll('[data-es-auth]').forEach((el) => {
      el.hidden = (el.dataset.esAuth === 'admin') !== isAdmin;
    });
    document.querySelectorAll('[data-es-logout]').forEach((el) => {
      el.addEventListener('click', () => removeStored(ADMIN_KEY));
    });

    // Login form: in the demo any input is accepted.
    document.querySelectorAll('form[data-es-login]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        writeStored(ADMIN_KEY, '1');
        window.location.assign(form.getAttribute('action'));
      });
    });

    // Show / hide password
    document.querySelectorAll('[data-es-password-toggle]').forEach((btn) => {
      const input = document.getElementById(btn.getAttribute('aria-controls'));
      btn.addEventListener('click', () => {
        if (!input) return;
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.querySelector('.bi').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
      });
    });

    // Admin table: select rows, show the bulk action bar.
    const selectAll = document.querySelector('[data-es-select-all]');
    const rowChecks = document.querySelectorAll('[data-es-select]');
    const bulk = document.querySelector('[data-es-bulk]');
    const bulkCount = document.querySelector('[data-es-bulk-count]');
    const updateBulk = () => {
      const selected = Array.from(rowChecks).filter((c) => c.checked).length;
      if (bulk) bulk.hidden = selected === 0;
      if (bulkCount) bulkCount.textContent = String(selected);
      if (selectAll) {
        selectAll.checked = selected > 0 && selected === rowChecks.length;
        selectAll.indeterminate = selected > 0 && selected < rowChecks.length;
      }
    };
    selectAll?.addEventListener('change', () => {
      rowChecks.forEach((c) => { c.checked = selectAll.checked; });
      updateBulk();
    });
    rowChecks.forEach((c) => c.addEventListener('change', updateBulk));
    document.querySelector('[data-es-bulk-clear]')?.addEventListener('click', () => {
      rowChecks.forEach((c) => { c.checked = false; });
      updateBulk();
    });

    // Grid / list toggle
    const catalog = document.querySelector('.es-catalog');
    document.querySelectorAll('[data-es-view]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const isList = btn.dataset.esView === 'list';
        catalog?.classList.toggle('is-list', isList);
        document.querySelectorAll('[data-es-view]').forEach((b) => {
          const on = b === btn;
          b.classList.toggle('active', on);
          b.setAttribute('aria-pressed', String(on));
        });
      });
    });

    // Item page: thumbnails swap the stage image; the stage opens the lightbox.
    const stageLink = document.querySelector('.es-stage a');
    const stageImg = document.querySelector('.es-stage img');
    const thumbs = document.querySelectorAll('.es-thumbs a');
    let lightbox = null;
    if (window.GLightbox && thumbs.length) {
      lightbox = window.GLightbox({
        loop: true,
        elements: Array.from(thumbs, (t) => ({
          href: t.getAttribute('href'),
          type: 'image',
          description: t.querySelector('img')?.alt || '',
        })),
      });
    }
    thumbs.forEach((thumb, index) => {
      thumb.addEventListener('click', (event) => {
        event.preventDefault();
        if (stageImg && stageLink) {
          stageImg.src = thumb.getAttribute('href');
          stageImg.alt = thumb.querySelector('img')?.alt || '';
          stageLink.dataset.esIndex = String(index);
        }
        document.querySelectorAll('.es-thumbs a').forEach((t) => {
          t.classList.toggle('active', t === thumb);
          t.setAttribute('aria-current', t === thumb ? 'true' : 'false');
        });
      });
    });
    if (stageLink && lightbox) {
      stageLink.addEventListener('click', (event) => {
        event.preventDefault();
        lightbox.openAt(Number(stageLink.dataset.esIndex || 0));
      });
    }
  });
})();
