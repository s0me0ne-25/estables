/*
 * eStables: progressive enhancements on top of Bootstrap.
 *
 * Ported feature by feature from demo/js/demo.js as the engine gains the
 * matching pages; the demo stays the behavioural reference. Every feature is
 * an init function bound to data-es-* hooks:
 * - colour theme switcher (light / dark / auto), persisted in localStorage
 * - password visibility toggle
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const STORAGE_KEYS = {
    theme: 'estables-theme',
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

  /* ------------------------------------------------------- password fields */

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

  /* ------------------------------------------------------------------ boot */

  document.addEventListener('DOMContentLoaded', () => {
    [
      initThemeSwitcher,
      initPasswordToggles,
    ].forEach((init) => init());
  });
})();
