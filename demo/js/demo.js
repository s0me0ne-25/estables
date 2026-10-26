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
 * - admin: drag-and-drop sorting with keyboard support for every reorderable
 *   list (items of a rubric, rubricator tree, photos, attributes, variants)
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

  /* ------------------------------------------------- drag-and-drop sorting */

  /*
   * One sorting engine for every reorderable admin list, built on Pointer
   * Events so that mouse, touch and pen behave the same.
   *
   * Markup:
   *   container  [data-es-sortable="list" | "grid" | "tree"]
   *   handle     [data-es-sortable-handle], inside every movable item
   * Items are the container's children that hold a handle ("list", "grid")
   * or the <li> elements of the nested lists ("tree"). Other children, such
   * as a header row or an upload zone, never move.
   *
   * Keyboard, on a focused handle: arrows move the item; in a tree, left and
   * right change the nesting. Escape cancels a drag in progress.
   *
   * After every move the container fires "es:sorted" with { item }. Features
   * renumber positions there; the engine will also send the new position to
   * the server from that event.
   */
  const SORT_HANDLE = '[data-es-sortable-handle]';
  const SORT_DRAG_THRESHOLD = 4; // px of movement before a press becomes a drag
  const SORT_SCROLL_EDGE = 48;   // px from the viewport edge that starts auto-scroll
  const SORT_SCROLL_STEP = 12;   // px per animation frame
  const SORT_HINTS = {
    list: 'Перетащите или нажимайте стрелки вверх и вниз.',
    grid: 'Перетащите или нажимайте стрелки.',
    tree: 'Перетащите или нажимайте стрелки: вверх и вниз меняют порядок, влево и вправо — вложенность.',
  };

  const sortables = new Map();
  let sortableUid = 0;
  let liveRegion = null;

  // Screen reader announcement of the result of a move.
  const announce = (text) => {
    if (!liveRegion) {
      liveRegion = document.createElement('div');
      liveRegion.className = 'visually-hidden';
      liveRegion.setAttribute('aria-live', 'polite');
      document.body.appendChild(liveRegion);
    }
    liveRegion.textContent = text;
  };

  // A hidden description shared by all handles of one kind of container.
  const sortHintId = (mode) => {
    const id = `es-sortable-hint-${mode}`;
    if (!document.getElementById(id)) {
      const hint = document.createElement('p');
      hint.id = id;
      hint.className = 'visually-hidden';
      hint.textContent = SORT_HINTS[mode];
      document.body.appendChild(hint);
    }
    return id;
  };

  const makeSortable = (root) => {
    const mode = root.dataset.esSortable;
    const isTree = mode === 'tree';

    // The movable item an element belongs to, or null.
    const itemOf = (el) => {
      if (!el || !root.contains(el)) return null;
      if (isTree) {
        const li = el.closest('li');
        return li && root.contains(li) ? li : null;
      }
      let node = el;
      while (node && node.parentElement !== root) node = node.parentElement;
      return node && node.querySelector(SORT_HANDLE) ? node : null;
    };
    const siblingsOf = (item) => Array.from(item.parentElement.children)
      .filter((el) => (isTree ? el.tagName === 'LI' : el.querySelector(SORT_HANDLE)));
    // The visible row of an item: in a tree, the node without its subtree.
    const rowOf = (item) => (isTree ? item.querySelector(':scope > .es-node') : item);

    /* Tree structure: child lists and their expand / collapse toggles */
    const childList = (li) => li.querySelector(':scope > ul');
    const ensureChildList = (li) => {
      let ul = childList(li);
      if (ul) return ul;
      sortableUid += 1;
      ul = document.createElement('ul');
      ul.id = `es-sortable-branch-${sortableUid}`;
      ul.className = 'collapse show';
      li.appendChild(ul);
      const toggle = document.createElement('button');
      toggle.className = 'es-toggle';
      toggle.type = 'button';
      toggle.setAttribute('data-bs-toggle', 'collapse');
      toggle.setAttribute('data-bs-target', `#${ul.id}`);
      toggle.setAttribute('aria-controls', ul.id);
      toggle.setAttribute('aria-expanded', 'true');
      toggle.setAttribute('aria-label', 'Подразделы');
      toggle.innerHTML = '<i class="bi bi-chevron-right" aria-hidden="true"></i>';
      rowOf(li).querySelector('.es-toggle-spacer')?.replaceWith(toggle);
      return ul;
    };
    const expand = (li) => {
      childList(li)?.classList.add('show');
      rowOf(li).querySelector('.es-toggle')?.setAttribute('aria-expanded', 'true');
    };
    // A branch that lost its last child turns back into a leaf.
    const pruneEmpty = (ul) => {
      if (!ul || ul === root || ul.querySelector(':scope > li')) return;
      const li = ul.parentElement;
      ul.remove();
      const spacer = document.createElement('span');
      spacer.className = 'es-toggle-spacer';
      rowOf(li).querySelector('.es-toggle')?.replaceWith(spacer);
    };

    // True when the move would leave the item where it is.
    const isNoop = (item, target, where) => {
      if (target === item || item.contains(target)) return true;
      if (where === 'before') return target.previousElementSibling === item;
      if (where === 'after') return target.nextElementSibling === item;
      const ul = childList(target);
      return Boolean(ul && ul.lastElementChild === item);
    };

    // Moves the item before or after the target, or into it (tree only).
    const place = (item, target, where) => {
      const from = item.parentElement;
      if (where === 'before') target.before(item);
      else if (where === 'after') target.after(item);
      else {
        ensureChildList(target).appendChild(item);
        expand(target);
      }
      if (isTree) pruneEmpty(from);
      root.dispatchEvent(new CustomEvent('es:sorted', { detail: { item } }));
    };

    const describe = (item) => {
      const siblings = siblingsOf(item);
      let text = `Позиция ${siblings.indexOf(item) + 1} из ${siblings.length}`;
      if (isTree) {
        let level = 1;
        for (let li = item.parentElement.closest('li'); li && root.contains(li); li = li.parentElement.closest('li')) {
          level += 1;
        }
        text += `, уровень ${level}`;
      }
      return text;
    };

    // Moves the item by one position among its siblings; false at the edges.
    const move = (item, delta) => {
      const siblings = siblingsOf(item);
      const target = siblings[siblings.indexOf(item) + delta];
      if (!target) return false;
      place(item, target, delta < 0 ? 'before' : 'after');
      announce(describe(item));
      return true;
    };

    root.querySelectorAll(SORT_HANDLE).forEach((handle) => {
      handle.setAttribute('aria-describedby', sortHintId(mode));
    });

    /* Keyboard */
    const PREV_KEYS = mode === 'grid' ? ['ArrowUp', 'ArrowLeft'] : ['ArrowUp'];
    const NEXT_KEYS = mode === 'grid' ? ['ArrowDown', 'ArrowRight'] : ['ArrowDown'];
    const NEST_KEYS = isTree ? ['ArrowLeft', 'ArrowRight'] : [];
    let drag = null;

    root.addEventListener('keydown', (event) => {
      const handle = event.target.closest(SORT_HANDLE);
      const item = handle && itemOf(handle);
      if (!item || drag) return;
      const key = event.key;
      if (![...PREV_KEYS, ...NEXT_KEYS, ...NEST_KEYS].includes(key)) return;
      event.preventDefault();

      if (PREV_KEYS.includes(key)) move(item, -1);
      else if (NEXT_KEYS.includes(key)) move(item, 1);
      else if (key === 'ArrowRight') {
        // Indent: become the last child of the previous sibling.
        const siblings = siblingsOf(item);
        const parent = siblings[siblings.indexOf(item) - 1];
        if (parent) {
          place(item, parent, 'into');
          announce(describe(item));
        }
      } else if (key === 'ArrowLeft') {
        // Outdent: follow the current parent.
        const parent = item.parentElement.closest('li');
        if (parent && root.contains(parent)) {
          place(item, parent, 'after');
          announce(describe(item));
        }
      }
      handle.focus();
    });

    /* Pointer drag */
    const clearMarks = () => {
      root.querySelectorAll('.is-drop-before, .is-drop-after, .is-drop-into').forEach((el) => {
        el.classList.remove('is-drop-before', 'is-drop-after', 'is-drop-into');
      });
    };

    // Drop position under the pointer: { target, where }, { target: null }
    // for "nowhere", or null to keep the previous one (pointer in a gap).
    const hitTest = (x, y) => {
      const el = document.elementFromPoint(x, y);
      const target = itemOf(el);
      if (!target) return null;
      if (target === drag.item || drag.item.contains(target)) return { target: null };
      const row = rowOf(target);
      if (isTree && !row.contains(el)) return null;
      const box = row.getBoundingClientRect();
      let where;
      if (mode === 'grid') {
        where = x < box.left + box.width / 2 ? 'before' : 'after';
      } else if (!isTree) {
        where = y < box.top + box.height / 2 ? 'before' : 'after';
      } else {
        // Tree: upper third before, lower third after, the middle nests.
        const rel = (y - box.top) / box.height;
        where = rel < 0.3 ? 'before' : (rel > 0.7 ? 'after' : 'into');
        // Below an expanded parent the next row is its first child, so the
        // line there means "first child", not "after the whole branch".
        const branch = childList(target);
        if (where === 'after' && branch?.classList.contains('show') && branch.firstElementChild) {
          return isNoop(drag.item, branch.firstElementChild, 'before')
            ? { target: null }
            : { target: branch.firstElementChild, where: 'before' };
        }
      }
      return isNoop(drag.item, target, where) ? { target: null } : { target, where };
    };

    const update = () => {
      const hit = hitTest(drag.x, drag.y);
      if (!hit) return;
      clearMarks();
      drag.drop = hit.target ? hit : null;
      if (drag.drop) rowOf(hit.target).classList.add(`is-drop-${hit.where}`);
    };

    const autoScroll = () => {
      if (!drag?.active) return;
      let dy = 0;
      if (drag.y < SORT_SCROLL_EDGE) dy = -SORT_SCROLL_STEP;
      else if (drag.y > window.innerHeight - SORT_SCROLL_EDGE) dy = SORT_SCROLL_STEP;
      if (dy) {
        window.scrollBy(0, dy);
        update();
      }
      drag.raf = window.requestAnimationFrame(autoScroll);
    };

    const finish = (commit) => {
      if (!drag) return;
      const { item, handle, drop, active } = drag;
      window.cancelAnimationFrame(drag.raf);
      drag = null;
      item.classList.remove('is-dragging');
      document.body.classList.remove('es-sorting');
      clearMarks();
      if (commit && active && drop) {
        place(item, drop.target, drop.where);
        announce(describe(item));
        handle.focus({ preventScroll: true });
      }
    };

    root.addEventListener('pointerdown', (event) => {
      const handle = event.target.closest(SORT_HANDLE);
      const item = handle && itemOf(handle);
      if (!item || drag || event.button !== 0) return;
      drag = {
        item,
        handle,
        pointerId: event.pointerId,
        startX: event.clientX,
        startY: event.clientY,
        x: event.clientX,
        y: event.clientY,
        active: false,
        drop: null,
        raf: 0,
      };
      handle.setPointerCapture(event.pointerId);
    });

    root.addEventListener('pointermove', (event) => {
      if (!drag || event.pointerId !== drag.pointerId) return;
      drag.x = event.clientX;
      drag.y = event.clientY;
      if (!drag.active) {
        if (Math.hypot(drag.x - drag.startX, drag.y - drag.startY) < SORT_DRAG_THRESHOLD) return;
        drag.active = true;
        drag.item.classList.add('is-dragging');
        document.body.classList.add('es-sorting');
        autoScroll();
      }
      update();
    });

    root.addEventListener('pointerup', (event) => {
      if (drag && event.pointerId === drag.pointerId) finish(true);
    });
    root.addEventListener('pointercancel', (event) => {
      if (drag && event.pointerId === drag.pointerId) finish(false);
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && drag?.active) {
        event.preventDefault();
        finish(false);
      }
    });

    return { move, place };
  };

  const initSortables = () => {
    document.querySelectorAll('[data-es-sortable]').forEach((root) => {
      sortables.set(root, makeSortable(root));
    });
  };

  /* -------------------------------------------- admin: authorial order */

  // Items of a rubric: drag and drop (see above) or the up / down buttons in
  // each row; the position column is renumbered after every move.
  const initOrderTable = () => {
    const body = document.querySelector('[data-es-order]');
    const sortable = body && sortables.get(body);
    if (!sortable) return;
    const firstPos = Number(body.querySelector('[data-es-order-pos]')?.textContent || 1);

    body.addEventListener('es:sorted', () => {
      body.querySelectorAll('[data-es-order-row]').forEach((row, i) => {
        row.querySelector('[data-es-order-pos]').textContent = String(firstPos + i);
      });
    });
    body.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-es-order-move]');
      if (!btn) return;
      sortable.move(btn.closest('[data-es-order-row]'), btn.dataset.esOrderMove === 'up' ? -1 : 1);
      btn.focus();
    });
  };

  /* --------------------------------------------- admin: rubricator tree */

  // After a move: the counts stay recursive totals and the highlighted path
  // still leads to the current rubric.
  const initRubricTree = () => {
    const root = document.querySelector('.es-tree[data-es-sortable="tree"]');
    if (!root) return;
    const countOf = (li) => li.querySelector(':scope > .es-node .es-count');
    const children = (li) => Array.from(li.querySelectorAll(':scope > ul > li'));
    const number = (li) => Number(countOf(li)?.textContent || 0);

    // Each count is a recursive total; keep the part that belongs to the node.
    root.querySelectorAll('li').forEach((li) => {
      const own = number(li) - children(li).reduce((sum, child) => sum + number(child), 0);
      li.dataset.esOwnCount = String(Math.max(own, 0));
    });
    const recount = (li) => {
      const total = Number(li.dataset.esOwnCount)
        + children(li).reduce((sum, child) => sum + recount(child), 0);
      const count = countOf(li);
      if (count) count.textContent = String(total);
      return total;
    };

    root.addEventListener('es:sorted', () => {
      Array.from(root.children).forEach(recount);
      root.querySelectorAll('li.is-lineage').forEach((li) => li.classList.remove('is-lineage'));
      const current = root.querySelector('li.is-current');
      for (let li = current?.parentElement.closest('li'); li && root.contains(li); li = li.parentElement.closest('li')) {
        li.classList.add('is-lineage');
      }
    });
  };

  /* ------------------------------------------------ admin: item photos */

  // The first photo is the cover. The star button moves a photo to the front.
  const initPhotoManager = () => {
    const grid = document.querySelector('[data-es-photos]');
    const sortable = grid && sortables.get(grid);
    if (!sortable) return;
    const badge = grid.querySelector('[data-es-photo-cover-badge]');
    const photos = () => Array.from(grid.querySelectorAll(':scope > .es-photo'));

    grid.addEventListener('es:sorted', () => {
      photos().forEach((photo, i) => {
        const isCover = i === 0;
        photo.classList.toggle('is-cover', isCover);
        const star = photo.querySelector('[data-es-photo-cover-set]');
        if (star) star.hidden = isCover;
        if (isCover && badge) photo.querySelector('img').after(badge);
        // Keep "фото N" in accessible names in step with the order.
        photo.querySelectorAll('label, .visually-hidden').forEach((el) => {
          el.textContent = el.textContent.replace(/фото \d+$/, `фото ${i + 1}`);
        });
      });
    });

    grid.addEventListener('click', (event) => {
      const star = event.target.closest('[data-es-photo-cover-set]');
      if (!star) return;
      const photo = star.closest('.es-photo');
      sortable.place(photo, photos()[0], 'before');
      announce('Фото стало обложкой');
      photo.querySelector(SORT_HANDLE)?.focus();
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
      initSortables,
      initOrderTable,
      initRubricTree,
      initPhotoManager,
      initRubricPicker,
    ].forEach((init) => init());
  });
})();
