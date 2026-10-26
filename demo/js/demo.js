/*
 * eStables demo: small progressive enhancements on top of Bootstrap.
 *
 * Every feature is an init function bound to data-es-* hooks, so styling
 * classes can change without breaking behaviour:
 * - colour theme switcher (light / dark / auto), persisted in localStorage
 * - simulated administrator session (footer links, login form, logout)
 * - password visibility toggle
 * - admin table row selection with the bulk action bar
 * - grid / list toggle and remembered sort order for the catalogue
 * - item gallery: thumbnails switch the stage photo and the "download original"
 *   link, the stage opens GLightbox
 * - admin: authorial order of items in a rubric (drag and drop, up / down)
 * - admin: rubrics of an item, like tags, with one main rubric
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  // Sort choice is a cookie, not localStorage: the engine needs it while rendering.
  const SORT_COOKIE = 'es_sort';
  const SORT_COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

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

  const readCookie = (name) => {
    const pair = document.cookie.split('; ').find((c) => c.startsWith(name + '='));
    return pair ? decodeURIComponent(pair.slice(name.length + 1)) : null;
  };
  const writeCookie = (name, value, maxAge) => {
    document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
  };

  const escapeHtml = (text) => text.replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
  }[ch]));

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

  /*
   * Sort order of the catalogue. The visitor's choice becomes their default on
   * the whole site (stored in a cookie). The engine sorts on the server; the
   * demo reorders the cards of the current page on the client.
   */
  const SORT_KEYS = {
    author: (el) => Number(el.dataset.sortAuthor),
    number: (el) => Number(el.dataset.sortNumber),
    year: (el) => Number(el.dataset.sortYear),
    title: (el) => el.dataset.sortTitle,
  };

  const initCatalogSort = () => {
    const select = document.querySelector('[data-es-sort]');
    const catalog = document.querySelector('[data-es-catalog]');
    if (!select || !catalog) return;

    const apply = (mode) => {
      const key = SORT_KEYS[mode] || SORT_KEYS.author;
      const cards = Array.from(catalog.children);
      cards.sort((a, b) => {
        const ka = key(a);
        const kb = key(b);
        return typeof ka === 'string' ? ka.localeCompare(kb, 'ru') : ka - kb;
      });
      cards.forEach((card) => catalog.appendChild(card));
    };

    const saved = readCookie(SORT_COOKIE);
    if (saved && SORT_KEYS[saved]) {
      select.value = saved;
      apply(saved);
    }
    select.addEventListener('change', () => {
      writeCookie(SORT_COOKIE, select.value, SORT_COOKIE_MAX_AGE);
      apply(select.value);
    });
  };

  /* ---------------------------------------------------------- item gallery */

  const initItemGallery = () => {
    const stage = document.querySelector('[data-es-gallery-stage]');
    const container = document.querySelector('[data-es-gallery-thumbs]');
    if (!stage || !container) return;
    const stageImg = stage.querySelector('img');
    const thumbs = Array.from(container.querySelectorAll('a'));
    const download = document.querySelector('[data-es-gallery-download]');
    const info = document.querySelector('[data-es-gallery-info]');
    let currentIndex = 0;

    const captionOf = (t) => t.querySelector('img')?.alt || '';
    const lightbox = window.GLightbox
      ? window.GLightbox({
        loop: true,
        elements: thumbs.map((t) => ({
          href: t.getAttribute('href'),
          type: 'image',
          description: `${escapeHtml(captionOf(t))}<br><a href="${escapeHtml(t.dataset.original)}" `
            + `download="${escapeHtml(t.dataset.originalName)}">Скачать оригинал</a> `
            + `(${escapeHtml(t.dataset.originalInfo)})`,
        })),
      })
      : null;

    thumbs.forEach((thumb, index) => {
      thumb.addEventListener('click', (event) => {
        event.preventDefault();
        stageImg.src = thumb.getAttribute('href');
        stageImg.alt = captionOf(thumb);
        if (download) {
          download.href = thumb.dataset.original;
          download.download = thumb.dataset.originalName;
        }
        if (info) info.textContent = thumb.dataset.originalInfo;
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

  /* -------------------------------------------- admin: authorial order */

  // Rows of [data-es-order] are reordered by drag and drop on the handle or by
  // the up / down buttons; the position column is renumbered. The engine sends
  // the new position of the moved item to the server.
  const initOrderTable = () => {
    const body = document.querySelector('[data-es-order]');
    if (!body) return;
    const firstPos = Number(body.querySelector('[data-es-order-pos]')?.textContent || 1);
    const renumber = () => {
      body.querySelectorAll('[data-es-order-row]').forEach((row, i) => {
        row.querySelector('[data-es-order-pos]').textContent = String(firstPos + i);
      });
    };

    body.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-es-order-move]');
      if (!btn) return;
      const row = btn.closest('[data-es-order-row]');
      if (btn.dataset.esOrderMove === 'up' && row.previousElementSibling) {
        row.previousElementSibling.before(row);
      } else if (btn.dataset.esOrderMove === 'down' && row.nextElementSibling) {
        row.nextElementSibling.after(row);
      }
      renumber();
      btn.focus();
    });

    let dragged = null;
    const clearMarks = () => body.querySelectorAll('.is-drop-before, .is-drop-after')
      .forEach((r) => r.classList.remove('is-drop-before', 'is-drop-after'));
    const isAfter = (row, event) => {
      const box = row.getBoundingClientRect();
      return event.clientY > box.top + box.height / 2;
    };

    body.addEventListener('dragstart', (event) => {
      if (!event.target.closest('[data-es-order-handle]')) return;
      dragged = event.target.closest('[data-es-order-row]');
      dragged.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setDragImage(dragged, 16, 16);
    });
    body.addEventListener('dragover', (event) => {
      const row = event.target.closest('[data-es-order-row]');
      if (!dragged || !row || row === dragged) return;
      event.preventDefault();
      clearMarks();
      row.classList.add(isAfter(row, event) ? 'is-drop-after' : 'is-drop-before');
    });
    body.addEventListener('drop', (event) => {
      const row = event.target.closest('[data-es-order-row]');
      if (!dragged || !row || row === dragged) return;
      event.preventDefault();
      if (isAfter(row, event)) row.after(dragged); else row.before(dragged);
      renumber();
    });
    body.addEventListener('dragend', () => {
      dragged?.classList.remove('is-dragging');
      dragged = null;
      clearMarks();
    });
  };

  /* ------------------------------------------- admin: rubrics of an item */

  // The first rubric is the main one; it can be reassigned. At least one
  // rubric must stay, so the last one cannot be removed.
  const initRubricPicker = () => {
    const list = document.querySelector('[data-es-rubrics]');
    if (!list) return;
    const select = document.querySelector('[data-es-rubric-select]');
    const template = list.querySelector('[data-es-rubric]:not(.is-main)') || list.firstElementChild;

    const items = () => Array.from(list.querySelectorAll('[data-es-rubric]'));
    const setMain = (main) => {
      items().forEach((li) => {
        const on = li === main;
        li.classList.toggle('is-main', on);
        li.querySelector('[data-es-rubric-badge]').hidden = !on;
        li.querySelector('[data-es-rubric-main]').hidden = on;
      });
    };
    const sync = () => {
      const all = items();
      if (!all.some((li) => li.classList.contains('is-main')) && all.length) setMain(all[0]);
      all.forEach((li) => {
        li.querySelector('[data-es-rubric-remove]').disabled = all.length === 1;
      });
    };

    list.addEventListener('click', (event) => {
      const li = event.target.closest('[data-es-rubric]');
      if (!li) return;
      if (event.target.closest('[data-es-rubric-main]')) setMain(li);
      if (event.target.closest('[data-es-rubric-remove]')) li.remove();
      sync();
    });

    document.querySelector('[data-es-rubric-add]')?.addEventListener('click', () => {
      const path = select?.value;
      if (!path || items().some((li) => li.querySelector('.es-rubric-path').textContent === path)) return;
      const li = template.cloneNode(true);
      li.querySelector('.es-rubric-path').textContent = path;
      li.querySelector('[data-es-rubric-remove] .visually-hidden').textContent = `Убрать из раздела «${path}»`;
      list.appendChild(li);
      li.classList.remove('is-main');
      li.querySelector('[data-es-rubric-badge]').hidden = true;
      li.querySelector('[data-es-rubric-main]').hidden = false;
      sync();
    });
    sync();
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
      initCatalogSort,
      initItemGallery,
      initOrderTable,
      initRubricPicker,
    ].forEach((init) => init());
  });
})();
