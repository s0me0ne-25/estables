/*
 * eStables: progressive enhancements on top of Bootstrap.
 *
 * Ported feature by feature from demo/js/demo.js as the engine gains the
 * matching pages; the demo stays the behavioural reference. Every feature is
 * an init function bound to data-es-* hooks:
 * - colour theme switcher (light / dark / auto), persisted in localStorage
 * - password visibility toggle; hides the confirmation of a new password
 * - confirmation of destructive admin actions (form[data-es-confirm])
 * - reopening a modal form that came back with errors ([data-es-modal-open])
 * - drag-and-drop sorting with mouse, touch and keyboard ([data-es-sortable])
 * - the admin rubricator: saving moves, the delete dialog, the slug prefix
 * - the item editor: rubrics with the main one, rows of characteristics and variants
 * - the admin list of items: selection for bulk actions, filters, authorial order
 * - the catalogue: grid or list view, the visitor's sort order (es_sort cookie)
 * - photos: the editor grid with uploads, the gallery of the item page
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const STORAGE_KEYS = {
    theme: 'estables-theme',
    view: 'estables-view',
  };

  // The visitor's sort order: a cookie, because the server sorts and pages the list.
  const SORT_COOKIE = 'es_sort';
  const SORT_COOKIE_MAX_AGE = 365 * 24 * 60 * 60;

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

  /*
   * The eye button shows the password in clear text. A new password usually
   * has a confirmation field (data-es-password-confirm="<its id>"): a typo is
   * visible in clear text, so the confirmation is hidden and disabled, and a
   * disabled field is not submitted, so the server skips the comparison.
   */
  const initPasswordToggles = () => {
    document.querySelectorAll('[data-es-password-toggle]').forEach((btn) => {
      const input = document.getElementById(btn.getAttribute('aria-controls'));
      if (!input) return;
      const confirm = document.getElementById(btn.dataset.esPasswordConfirm || '');
      const confirmField = confirm && (confirm.closest('[data-es-password-confirm-field]') || confirm);
      const label = btn.querySelector('.visually-hidden');
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-pressed', String(show));
        btn.title = show ? 'Скрыть пароль' : 'Показать пароль';
        if (label) label.textContent = btn.title;
        btn.querySelector('.bi').className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
        if (confirm) {
          confirmField.hidden = show;
          confirm.disabled = show;
        }
      });
    });
  };

  /* ------------------------------------------------------------ admin forms */

  const initConfirmations = () => {
    document.querySelectorAll('form[data-es-confirm]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.esConfirm)) event.preventDefault();
      });
    });
  };

  // The server rendered the page again because a modal form had errors.
  const initModalReopen = () => {
    const holder = document.querySelector('[data-es-modal-open]');
    const modal = holder && document.getElementById(holder.dataset.esModalOpen);
    if (!modal || !window.bootstrap) return;
    window.bootstrap.Modal.getOrCreateInstance(modal).show();
    modal.addEventListener('shown.bs.modal', () => {
      const field = modal.querySelector('.is-invalid') || modal.querySelector('input:not([type="hidden"]):not([hidden])');
      if (field) field.focus();
    }, { once: true });
  };

  /* -------------------------------------------------------- catalogue view */

  // Grid or list; the choice is remembered in this browser.
  const initCatalogView = () => {
    const catalog = document.querySelector('[data-es-catalog]');
    const buttons = Array.from(document.querySelectorAll('[data-es-view]'));
    if (!catalog || !buttons.length) return;
    const apply = (view) => {
      catalog.classList.toggle('is-list', view === 'list');
      setActive(buttons, buttons.find((btn) => btn.dataset.esView === view) || buttons[0], 'aria-pressed');
    };
    apply(storage.get(STORAGE_KEYS.view) === 'list' ? 'list' : 'grid');
    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        storage.set(STORAGE_KEYS.view, btn.dataset.esView);
        apply(btn.dataset.esView);
      });
    });
  };

  /*
   * Sort order of the catalogue. The visitor's choice becomes their default on
   * the whole site (stored in a cookie); the server sorts, so the page reloads
   * from its first page, without ?sort= in the address.
   */
  const initCatalogSort = () => {
    const select = document.querySelector('[data-es-sort]');
    if (!select) return;
    select.addEventListener('change', () => {
      document.cookie = `${SORT_COOKIE}=${encodeURIComponent(select.value)}; Max-Age=${SORT_COOKIE_MAX_AGE}; Path=/; SameSite=Lax`;
      const url = new URL(window.location.href);
      url.searchParams.delete('sort');
      url.searchParams.delete('page');
      window.location.assign(url.toString());
    });
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
   * renumber positions there and send the new position to the server.
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

  /* --------------------------------------------- admin: rubricator tree */

  /*
   * After a move the counts stay recursive totals, the highlighted path still
   * leads to the current rubric, and the new place goes to the server. Moves
   * are sent one after another, in the order they were made; if the server
   * refuses one, the page shows why and reloads the tree as it is stored.
   */
  const initRubricTree = () => {
    const root = document.querySelector('[data-es-rubric-tree]');
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

    const failure = document.querySelector('[data-es-rubric-move-error]');
    const fail = (message) => {
      if (failure) {
        failure.textContent = `${message} Дерево сейчас обновится.`;
        failure.hidden = false;
        failure.scrollIntoView({ block: 'nearest' });
      }
      window.setTimeout(() => window.location.reload(), 2500);
    };

    let queue = Promise.resolve();
    const save = (item) => {
      const parentLi = item.parentElement.closest('li');
      const body = new FormData();
      body.append('_csrf', root.dataset.esCsrf);
      body.append('id', item.dataset.esRubric);
      body.append('parent', parentLi && root.contains(parentLi) ? parentLi.dataset.esRubric : '0');
      body.append('index', String(Array.from(item.parentElement.children).indexOf(item)));
      // The form on the right shows the parent and the address prefix: refresh
      // it when they changed for the rubric being edited.
      const current = root.querySelector('li.is-current');
      const affectsForm = Boolean(current && (item === current || item.contains(current)));
      queue = queue.then(async () => {
        let result = null;
        try {
          const response = await fetch(root.dataset.esMoveUrl, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
          });
          if ((response.headers.get('Content-Type') || '').includes('application/json')) {
            result = await response.json();
          }
        } catch (e) {
          result = null;
        }
        if (!result) throw new Error('Не удалось сохранить порядок: нет ответа от сервера или сессия закончилась.');
        if (!result.ok) throw new Error(result.error);
        if (affectsForm) window.location.reload();
      }).catch((e) => {
        queue = new Promise(() => {}); // stop sending: the page is about to reload
        fail(e.message);
      });
    };

    root.addEventListener('es:sorted', (event) => {
      Array.from(root.children).forEach(recount);
      root.querySelectorAll('li.is-lineage').forEach((li) => li.classList.remove('is-lineage'));
      const current = root.querySelector('li.is-current');
      for (let li = current?.parentElement.closest('li'); li && root.contains(li); li = li.parentElement.closest('li')) {
        li.classList.add('is-lineage');
      }
      save(event.detail.item);
    });
  };

  // "1 предмет", "2 предмета", "5 предметов".
  const plural = (n, one, few, many) => {
    const mod10 = n % 10;
    const mod100 = n % 100;
    const word = mod10 === 1 && mod100 !== 11 ? one
      : (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? few : many);
    return `${n} ${word}`;
  };

  // The delete dialog of the rubricator serves every rubric: the button that
  // opens it carries the rubric's id, name, number of subrubrics and items.
  const initRubricDelete = () => {
    const modal = document.querySelector('[data-es-rubric-delete-modal]');
    if (!modal) return;
    const part = (name) => modal.querySelector(`[data-es-rubric-delete-${name}]`);
    modal.addEventListener('show.bs.modal', (event) => {
      const button = event.relatedTarget;
      if (!button) return;
      const id = button.dataset.esRubricDelete;
      const busy = Number(button.dataset.esRubricChildren) > 0;
      const items = Number(button.dataset.esRubricItems || 0);
      const shared = Number(button.dataset.esRubricShared || 0);
      const own = items - shared;
      part('id').value = id;
      part('name').textContent = button.dataset.esRubricName;
      part('empty').hidden = busy || items > 0;
      part('busy').hidden = !busy;
      part('items').hidden = busy || items === 0;
      part('choice').hidden = own === 0;
      part('submit').disabled = busy;
      let text = `В разделе ${plural(items, 'предмет', 'предмета', 'предметов')}.`;
      if (own === 0) {
        text += ' Все они есть и в других разделах: связь с этим разделом просто снимется.';
      } else {
        if (shared > 0) {
          text += ` ${shared} из них есть и в других разделах: у них связь с этим разделом просто снимется.`;
        }
        text += ` Для ${shared > 0 ? 'остальных ' : ''}${plural(own, 'предмета', 'предметов', 'предметов')} раздел единственный. Что с ${own === 1 ? 'ним' : 'ними'} сделать?`;
      }
      part('summary').textContent = text;
      part('all').textContent = `Удалить ${own === 1 ? 'этот предмет' : `эти ${plural(own, 'предмет', 'предмета', 'предметов')}`}`;
      // Items cannot move into the rubric being deleted
      const target = part('target');
      Array.from(target.options).forEach((option) => { option.disabled = option.value === id; });
      if (target.selectedOptions[0]?.disabled) {
        target.value = Array.from(target.options).find((option) => !option.disabled)?.value || '';
      }
      modal.querySelector('#delMove').disabled = !target.value;
      modal.querySelector(target.value ? '#delMove' : '#delAll').checked = true;
    });
  };

  // "Parent rubric" changes the address prefix shown before the slug field.
  const initSlugPrefix = () => {
    const select = document.querySelector('[data-es-slug-parent]');
    const prefix = document.querySelector('[data-es-slug-prefix]');
    if (!select || !prefix) return;
    const sync = () => {
      prefix.textContent = select.selectedOptions[0]?.dataset.esPrefix || '/catalog/';
    };
    select.addEventListener('change', sync);
    select.form?.addEventListener('reset', () => window.setTimeout(sync));
  };

  // Small buttons that lead to another page (inside rows where a link would
  // take the row's link styling).
  const initButtonLinks = () => {
    document.addEventListener('click', (event) => {
      const button = event.target.closest('button[data-es-href]');
      if (button) window.location.assign(button.dataset.esHref);
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
    update();
  };

  // Filter forms apply a choice at once; the search field on Enter.
  const initAutoSubmit = () => {
    document.querySelectorAll('form[data-es-autosubmit]').forEach((form) => {
      form.querySelectorAll('select, input[type="checkbox"]').forEach((field) => {
        field.addEventListener('change', () => form.requestSubmit());
      });
      form.querySelectorAll('[data-es-autosubmit-button]').forEach((button) => {
        button.hidden = true;
      });
    });
  };

  /* -------------------------------------------- admin: authorial order */

  // Items of a rubric: drag and drop or the up / down buttons in each row.
  // The position column is renumbered after every move, and the move goes
  // to the server as "after the previous row" (or "before the next one").
  const initOrderTable = () => {
    const body = document.querySelector('[data-es-order]');
    const sortable = body && sortables.get(body);
    if (!sortable) return;
    const firstPos = Number(body.querySelector('[data-es-order-pos]')?.textContent || 1);
    const failure = document.querySelector('[data-es-order-error]');
    let queue = Promise.resolve();

    const save = (row) => {
      const prev = row.previousElementSibling;
      const next = row.nextElementSibling;
      const data = new FormData();
      data.append('_csrf', body.dataset.esCsrf);
      data.append('rubric', body.dataset.esOrderRubric);
      data.append('item', row.dataset.esItem);
      if (prev?.dataset.esItem) data.append('after', prev.dataset.esItem);
      else if (next?.dataset.esItem) data.append('before', next.dataset.esItem);
      queue = queue.then(async () => {
        let result = null;
        try {
          const response = await fetch(body.dataset.esOrderUrl, {
            method: 'POST', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' },
          });
          if ((response.headers.get('Content-Type') || '').includes('application/json')) result = await response.json();
        } catch (e) {
          result = null;
        }
        if (!result) throw new Error('Не удалось сохранить порядок: нет ответа от сервера или сессия закончилась.');
        if (!result.ok) throw new Error(result.error);
      }).catch((e) => {
        queue = new Promise(() => {});
        if (failure) {
          failure.textContent = `${e.message} Список сейчас обновится.`;
          failure.hidden = false;
          failure.scrollIntoView({ block: 'nearest' });
        }
        window.setTimeout(() => window.location.reload(), 2500);
      });
    };

    body.addEventListener('es:sorted', (event) => {
      body.querySelectorAll('[data-es-order-row]').forEach((row, i) => {
        row.querySelector('[data-es-order-pos]').textContent = String(firstPos + i);
      });
      save(event.detail.item);
    });
    body.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-es-order-move]');
      if (!btn) return;
      sortable.move(btn.closest('[data-es-order-row]'), btn.dataset.esOrderMove === 'up' ? -1 : 1);
      btn.focus();
    });
  };

  /* ------------------------------------------- admin: rubrics of an item */

  // The first rubric is the main one; it can be reassigned. At least one
  // rubric must stay, so the last one cannot be removed. Each row carries a
  // hidden rubrics[] input; the main one goes to main_rubric.
  const initRubricPicker = () => {
    const list = document.querySelector('[data-es-rubrics]');
    if (!list) return;
    const select = document.querySelector('[data-es-rubric-select]');
    const template = document.querySelector('[data-es-rubric-template]');
    const mainInput = document.querySelector('[data-es-rubric-main-input]');

    const items = () => Array.from(list.querySelectorAll('[data-es-rubric]'));
    const setMain = (main) => {
      items().forEach((li) => {
        const on = li === main;
        li.classList.toggle('is-main', on);
        li.querySelector('[data-es-rubric-badge]').hidden = !on;
        li.querySelector('[data-es-rubric-main]').hidden = on;
      });
      if (mainInput) mainInput.value = main ? main.dataset.esRubric : '';
    };
    const sync = () => {
      const all = items();
      const main = all.find((li) => li.classList.contains('is-main')) || all[0] || null;
      setMain(main);
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
      const option = select?.selectedOptions[0];
      if (!option || !template || items().some((li) => li.dataset.esRubric === option.value)) return;
      const li = template.content.firstElementChild.cloneNode(true);
      const path = option.dataset.esPath || option.textContent.trim();
      li.dataset.esRubric = option.value;
      li.querySelector('input').value = option.value;
      li.querySelector('.es-rubric-path').textContent = path;
      li.querySelector('[data-es-rubric-remove] .visually-hidden').textContent = `Убрать из раздела «${path}»`;
      list.appendChild(li);
      sync();
    });
    sync();
  };

  // Rows of characteristics and variants: "add" clones the row template,
  // the cross removes a row; rows are reordered by the sorting engine.
  const initRowEditors = () => {
    document.querySelectorAll('[data-es-row-add]').forEach((button) => {
      const name = button.dataset.esRowAdd;
      const rows = document.querySelector(`[data-es-rows="${name}"]`);
      const template = document.querySelector(`[data-es-row-template="${name}"]`);
      if (!rows || !template) return;
      button.addEventListener('click', () => {
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-es-sortable-handle]').forEach((handle) => {
          handle.setAttribute('aria-describedby', 'es-sortable-hint-list');
        });
        rows.appendChild(row);
        row.querySelector('input')?.focus();
      });
    });
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-es-row-remove]');
      if (!button) return;
      const row = button.closest('.es-row');
      const next = row?.nextElementSibling;
      if (next?.classList.contains('invalid-feedback')) next.remove();
      row?.remove();
    });
  };

  /* ------------------------------------------------ admin: item photos */

  // The first photo is the cover. The star moves a photo to the front, the
  // bin takes it off the item when the form is saved.
  const initPhotoManager = () => {
    const grid = document.querySelector('[data-es-photos]');
    const sortable = grid && sortables.get(grid);
    if (!sortable) return;
    const photos = () => Array.from(grid.querySelectorAll(':scope > .es-photo'));

    const refresh = () => {
      let badge = grid.querySelector('[data-es-photo-cover-badge]');
      photos().forEach((photo, i) => {
        const isCover = i === 0;
        photo.classList.toggle('is-cover', isCover);
        const star = photo.querySelector('[data-es-photo-cover-set]');
        if (star) star.hidden = isCover;
        if (isCover) {
          if (!badge) {
            badge = document.createElement('span');
            badge.className = 'es-photo-cover';
            badge.dataset.esPhotoCoverBadge = '';
            badge.textContent = 'Обложка';
          }
          photo.querySelector('img').after(badge);
        }
        // Keep "фото N" in accessible names in step with the order.
        photo.querySelectorAll('label, .visually-hidden').forEach((el) => {
          el.textContent = el.textContent.replace(/фото \d+$/, `фото ${i + 1}`);
        });
      });
      if (badge && !photos().length) badge.remove();
    };
    grid.esRefreshPhotos = refresh;

    grid.addEventListener('es:sorted', refresh);
    grid.addEventListener('click', (event) => {
      const star = event.target.closest('[data-es-photo-cover-set]');
      if (star) {
        const photo = star.closest('.es-photo');
        sortable.place(photo, photos()[0], 'before');
        announce('Фото стало обложкой');
        photo.querySelector(SORT_HANDLE)?.focus();
        return;
      }
      const remove = event.target.closest('[data-es-photo-remove]');
      if (remove) {
        remove.closest('.es-photo')?.remove();
        refresh();
        announce('Фото будет убрано при сохранении');
      }
    });
  };

  // Uploads of photos: one request per file, each tile appears as soon as
  // its file is processed; files can be dropped onto the zone.
  const initPhotoUpload = () => {
    const input = document.querySelector('[data-es-upload]');
    const grid = document.querySelector('[data-es-photos]');
    const zone = document.querySelector('[data-es-upload-zone]');
    const status = document.querySelector('[data-es-upload-status]');
    const csrf = document.querySelector('#itemForm input[name="_csrf"]')?.value || '';
    if (!input || !grid || !zone) return;
    const max = Number(input.dataset.esUploadMax || 0);
    let queue = Promise.resolve();

    const line = (text, isError) => {
      const p = document.createElement('p');
      p.className = `small mb-1 ${isError ? 'text-danger' : 'text-body-secondary'}`;
      p.textContent = text;
      status?.appendChild(p);
      return p;
    };

    const upload = (file) => {
      queue = queue.then(async () => {
        if (max && file.size > max) {
          line(`${file.name}: файл больше ${Math.round(max / 1048576)} МБ.`, true);
          return;
        }
        const progress = line(`${file.name}: загружается…`, false);
        const body = new FormData();
        body.append('_csrf', csrf);
        body.append('photo', file);
        let result = null;
        try {
          const response = await fetch(input.dataset.esUpload, {
            method: 'POST', body, credentials: 'same-origin', headers: { Accept: 'application/json' },
          });
          if ((response.headers.get('Content-Type') || '').includes('application/json')) result = await response.json();
        } catch (e) {
          result = null;
        }
        if (result?.ok) {
          zone.insertAdjacentHTML('beforebegin', result.html);
          grid.esRefreshPhotos?.();
          progress.remove();
        } else {
          progress.textContent = `${file.name}: ${result?.error || 'не загрузился (нет ответа сервера, файл слишком большой или сессия закончилась).'}`;
          progress.className = 'small mb-1 text-danger';
        }
      });
    };

    input.addEventListener('change', () => {
      Array.from(input.files).forEach(upload);
      input.value = '';
    });
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => {
      event.preventDefault();
      zone.classList.add('is-over');
    }));
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-over')));
    zone.addEventListener('drop', (event) => {
      event.preventDefault();
      Array.from(event.dataTransfer?.files || []).forEach(upload);
    });
  };

  /* ---------------------------------------------------------- item gallery */

  // The stage shows the chosen thumbnail; "Скачать оригинал" follows it; the
  // stage opens the full-screen view (GLightbox) at the same photo.
  const initItemGallery = () => {
    const stage = document.querySelector('[data-es-gallery-stage]');
    if (!stage) return;
    const container = document.querySelector('[data-es-gallery-thumbs]');
    const stageImg = stage.querySelector('img');
    const thumbs = container ? Array.from(container.querySelectorAll('a')) : [stage];
    const download = document.querySelector('[data-es-gallery-download]');
    const info = document.querySelector('[data-es-gallery-info]');
    let currentIndex = 0;

    const captionOf = (t) => t.querySelector('img')?.alt || '';
    const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (ch) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[ch]));
    const lightbox = window.GLightbox
      ? window.GLightbox({
        loop: true,
        elements: thumbs.map((t) => ({
          href: t.getAttribute('href'),
          type: 'image',
          description: `${escapeHtml(captionOf(t))}<br><a href="${escapeHtml(t.dataset.original)}">Скачать оригинал</a> `
            + `(${escapeHtml(t.dataset.originalInfo)})`,
        })),
      })
      : null;

    if (container) {
      thumbs.forEach((thumb, index) => {
        thumb.addEventListener('click', (event) => {
          event.preventDefault();
          stageImg.src = thumb.getAttribute('href');
          stageImg.alt = captionOf(thumb);
          if (download) download.href = thumb.dataset.original;
          if (info) info.textContent = thumb.dataset.originalInfo;
          currentIndex = index;
          setActive(thumbs, thumb, 'aria-current');
        });
      });
    }

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
      initPasswordToggles,
      initConfirmations,
      initModalReopen,
      initSortables,
      initRubricTree,
      initRubricDelete,
      initSlugPrefix,
      initButtonLinks,
      initRubricPicker,
      initRowEditors,
      initBulkSelection,
      initAutoSubmit,
      initOrderTable,
      initCatalogView,
      initCatalogSort,
      initPhotoManager,
      initPhotoUpload,
      initItemGallery,
    ].forEach((init) => init());
  });
})();
