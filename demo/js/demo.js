/*
 * eStables demo: small progressive enhancements on top of Bootstrap.
 * - colour theme switcher (light / dark / auto) persisted in localStorage
 * - grid / list toggle for the catalogue
 * - GLightbox and thumbnail switching on the item page
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const THEME_KEY = 'estables-theme';

  const readStored = (key) => {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  };
  const writeStored = (key, value) => {
    try { localStorage.setItem(key, value); } catch (e) { /* storage unavailable */ }
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
