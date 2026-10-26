/*
 * eStables demo: small progressive enhancements on top of Bootstrap.
 *
 * Every feature is an init function bound to data-es-* hooks, so styling
 * classes can change without breaking behaviour:
 * - colour theme switcher (light / dark / auto), persisted in localStorage
 * - simulated administrator session (footer links, login form, logout)
 * - password visibility toggle
 * - admin table row selection with the bulk action bar
 * - grid / list toggle for the catalogue
 * - item gallery: thumbnails switch the stage photo, the stage opens GLightbox
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const STORAGE_KEYS = {
    theme: 'estables-theme',
    // Demo only: the real session will live on the server.
    admin: 'estables-demo-admin',
  };

  /* ---------------------------------------------------------------- helpers */

  // localStorage can be unavailable (private mode, blocked site data).
  const storage = {
    get(key) {
      try { return localStorage.getItem(key); } catch (e) { return null; }
    },
    set(key, value) {
      try { localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ }
    },
    remove(key) {
      try { localStorage.removeItem(key); } catch (e) { /* storage unavailable */ }
    },
  };

  /**
   * Marks one element of a group as current: toggles the "active" class
   * and the given ARIA state ("aria-pressed" or "aria-current").
   */
  const setActive = (items, current, ariaState) => {
    items.forEach((item) => {
      const on = item === current;
      item.classList.toggle('active', on);
      if (ariaState === 'aria-current') {
        if (on) item.setAttribute('aria-current', 'true');
        else item.removeAttribute('aria-current');
      } else {
        item.setAttribute(ariaState, String(on));
      }
    });
  };

  /* ------------------------------------------------------------------ theme */

  const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
  const storedThemeChoice = () => storage.get(STORAGE_KEYS.theme) || 'auto';
  const resolveTheme = (choice) =>
    choice === 'auto' ? (darkQuery.matches ? 'dark' : 'light') : choice;

  const applyTheme = (choice) => {
    document.documentElement.setAttribute('data-bs-theme', resolveTheme(choice));
  };

  const syncThemeControls = (choice) => {
    const items = Array.from(document.querySelectorAll('[data-es-theme-value]'));
    setActive(items, items.find((el) => el.dataset.esThemeValue === choice), 'aria-pressed');
    const icon = document.querySelector('[data-es-theme-icon]');
    if (icon) {
      icon.className = 'bi ' + (resolveTheme(choice) === 'dark' ? 'bi-moon-stars' : 'bi-sun');
    }
  };

  // Runs immediately (script is loaded in <head>) to avoid a flash of the wrong theme.
  applyTheme(storedThemeChoice());
  darkQuery.addEventListener('change', () => {
    if (storedThemeChoice() === 'auto') {
      applyTheme('auto');
      syncThemeControls('auto');
    }
  });

  const initThemeSwitcher = () => {
    syncThemeControls(storedThemeChoice());
    document.querySelectorAll('[data-es-theme-value]').forEach((el) => {
      el.addEventListener('click', () => {
        const choice = el.dataset.esThemeValue;
        storage.set(STORAGE_KEYS.theme, choice);
        applyTheme(choice);
        syncThemeControls(choice);
      });
    });
  };

  /* ---------------------------------------------------- admin session (demo) */

  const initAdminSession = () => {
    // Opening any admin page counts as being logged in.
    const onAdminPage = document.body.dataset.esArea === 'admin';
    if (onAdminPage) storage.set(STORAGE_KEYS.admin, '1');
    const isAdmin = onAdminPage || storage.get(STORAGE_KEYS.admin) === '1';
    document.querySelectorAll('[data-es-auth]').forEach((el) => {
      el.hidden = (el.dataset.esAuth === 'admin') !== isAdmin;
    });
    document.querySelectorAll('[data-es-logout]').forEach((el) => {
      el.addEventListener('click', () => storage.remove(STORAGE_KEYS.admin));
    });
  };

  // Login form: in the demo any input is accepted.
  const initLoginForm = () => {
    document.querySelectorAll('form[data-es-login]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        event.preventDefault();
        storage.set(STORAGE_KEYS.admin, '1');
        window.location.assign(form.action);
      });
    });
  };

  const initPasswordToggles = () => {
    document.querySelectorAll('[data-es-password-toggle]').forEach((btn) => {
      const input = document.getElementById(btn.getAttribute('aria-controls'));
      if (!input) return;
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.querySelector('.bi').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
      });
    });
  };

  /* ------------------------------------------------- admin table selection */

  const initBulkSelection = () => {
    const rows = Array.from(document.querySelectorAll('[data-es-select]'));
    if (!rows.length) return;
    const selectAll = document.querySelector('[data-es-select-all]');
    const bar = document.querySelector('[data-es-bulk]');
    const count = document.querySelector('[data-es-bulk-count]');

    const update = () => {
      const selected = rows.filter((c) => c.checked).length;
      if (bar) bar.hidden = selected === 0;
      if (count) count.textContent = String(selected);
      if (selectAll) {
        selectAll.checked = selected > 0 && selected === rows.length;
        selectAll.indeterminate = selected > 0 && selected < rows.length;
      }
    };
    const setAll = (checked) => {
      rows.forEach((c) => { c.checked = checked; });
      update();
    };

    rows.forEach((c) => c.addEventListener('change', update));
    selectAll?.addEventListener('change', () => setAll(selectAll.checked));
    document.querySelector('[data-es-bulk-clear]')?.addEventListener('click', () => setAll(false));
  };

  /* -------------------------------------------------------- catalogue view */

  const initCatalogView = () => {
    const catalog = document.querySelector('[data-es-catalog]');
    const buttons = Array.from(document.querySelectorAll('[data-es-view]'));
    if (!catalog) return;
    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        catalog.classList.toggle('is-list', btn.dataset.esView === 'list');
        setActive(buttons, btn, 'aria-pressed');
      });
    });
  };

  /* ---------------------------------------------------------- item gallery */

  const initItemGallery = () => {
    const stage = document.querySelector('[data-es-gallery-stage]');
    const container = document.querySelector('[data-es-gallery-thumbs]');
    if (!stage || !container) return;
    const stageImg = stage.querySelector('img');
    const thumbs = Array.from(container.querySelectorAll('a'));
    let currentIndex = 0;

    const lightbox = window.GLightbox
      ? window.GLightbox({
        loop: true,
        elements: thumbs.map((t) => ({
          href: t.getAttribute('href'),
          type: 'image',
          description: t.querySelector('img')?.alt || '',
        })),
      })
      : null;

    thumbs.forEach((thumb, index) => {
      thumb.addEventListener('click', (event) => {
        event.preventDefault();
        stageImg.src = thumb.getAttribute('href');
        stageImg.alt = thumb.querySelector('img')?.alt || '';
        currentIndex = index;
        setActive(thumbs, thumb, 'aria-current');
      });
    });

    if (lightbox) {
      stage.addEventListener('click', (event) => {
        event.preventDefault();
        lightbox.openAt(currentIndex);
      });
    }
  };

  /* ------------------------------------------------------------------ boot */

  document.addEventListener('DOMContentLoaded', () => {
    [
      initThemeSwitcher,
      initAdminSession,
      initLoginForm,
      initPasswordToggles,
      initBulkSelection,
      initCatalogView,
      initItemGallery,
    ].forEach((init) => init());
  });
})();
