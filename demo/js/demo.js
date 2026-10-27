/*
 * eStables static demo: stands in for the server.
 *
 * The pages are rendered by the engine and saved as plain files, so nothing
 * here can be stored. This script imitates what the server would do:
 * - an administrator session: opening any admin page or submitting the login
 *   form "signs in", the "выход" link signs out; public pages then show the
 *   administrator's footer and the "Редактировать" buttons;
 * - maintenance mode: the switch on the "Users" page closes the site for
 *   guests (they get maintenance.html), administrators see a reminder bar;
 * - forms and background saves are answered with a notice that the demo
 *   keeps nothing; the search form shows a sample result page, the "Раздел"
 *   filter of the item list opens that rubric's pre-rendered list.
 * The state lives in localStorage of this browser only.
 *
 * Loaded in <head> before the engine script, so a closed site redirects
 * before anything is drawn.
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(() => {
  'use strict';

  const KEYS = {
    admin: 'estables-demo-admin',
    maintenance: 'estables-demo-maintenance',
  };
  const NOTHING_SAVED = 'Это статическая демонстрационная версия: здесь ничего не сохраняется и не фильтруется.';

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

  // "admin" (admin area), "public" (site), "login" or "stub" (maintenance page)
  const area = document.querySelector('meta[name="es-demo-area"]')?.content || 'public';
  if (area === 'admin') storage.set(KEYS.admin, '1');
  const isAdmin = () => storage.get(KEYS.admin) === '1';
  const isClosed = () => storage.get(KEYS.maintenance) === '1';

  if (area === 'public' && isClosed() && !isAdmin()) {
    window.location.replace('maintenance.html');
    return;
  }

  // Background saves (drag and drop, photo upload) get an answer like the server's.
  window.fetch = async (url, options = {}) => {
    const upload = options.body instanceof FormData && options.body.has('photo');
    const body = upload
      ? { ok: false, error: 'В демонстрационной версии фото не загружаются.' }
      : { ok: true };
    return new Response(JSON.stringify(body), { status: 200, headers: { 'Content-Type': 'application/json' } });
  };

  let toastTimer = 0;
  const toast = (text) => {
    let box = document.getElementById('es-demo-toast');
    if (!box) {
      box = document.createElement('div');
      box.id = 'es-demo-toast';
      box.className = 'alert alert-info shadow position-fixed bottom-0 end-0 m-3 mb-3';
      box.style.zIndex = '1090';
      box.style.maxWidth = '26rem';
      box.setAttribute('role', 'status');
      document.body.appendChild(box);
    }
    box.textContent = text;
    box.hidden = false;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => { box.hidden = true; }, 5000);
  };

  const showSession = () => {
    const admin = isAdmin();
    document.querySelectorAll('[data-es-auth]').forEach((el) => {
      el.hidden = (el.dataset.esAuth === 'admin') !== admin;
    });
    document.querySelectorAll('[data-es-demo-maintenance]').forEach((el) => {
      el.hidden = !(admin && isClosed());
    });
  };

  document.addEventListener('DOMContentLoaded', () => {
    showSession();
    const maintenanceSwitch = document.querySelector('form input[name="maintenance"][type="checkbox"]');
    if (maintenanceSwitch) maintenanceSwitch.checked = isClosed();

    document.addEventListener('click', (event) => {
      if (event.target.closest('[data-es-logout]')) storage.remove(KEYS.admin);
    });

    // Capture phase: runs before the engine's own handlers on the form.
    document.addEventListener('submit', (event) => {
      const form = event.target;
      if (form.matches('[role="search"][action="search.html"]')) return;
      event.preventDefault();
      event.stopImmediatePropagation();

      if (area === 'login') {
        storage.set(KEYS.admin, '1');
        window.location.assign('admin-items.html');
        return;
      }
      // The item list: every rubric has its own page, the other filters do nothing.
      if (form.matches('[action="admin-items.html"]')) {
        const data = new FormData(form);
        if (!data.get('q') && !data.get('status')) {
          window.location.assign(data.get('rubric') ? `admin-items-r${data.get('rubric')}.html` : 'admin-items.html');
          return;
        }
      }
      if (form.querySelector('input[name="action"][value="maintenance"]')) {
        const on = Boolean(form.querySelector('input[name="maintenance"][type="checkbox"]')?.checked);
        if (on) storage.set(KEYS.maintenance, '1');
        else storage.remove(KEYS.maintenance);
        showSession();
        toast(on
          ? 'Режим техработ включён (в этом браузере). Выйдите по ссылке «выход» в подвале — гостю сайт покажет заглушку.'
          : 'Режим техработ выключен: сайт снова открыт для посетителей.');
        return;
      }
      document.querySelectorAll('.modal.show').forEach((modal) => window.bootstrap?.Modal.getInstance(modal)?.hide());
      toast(NOTHING_SAVED);
    }, true);

    // The server sorts the catalogue; the static pages keep one order.
    document.addEventListener('change', (event) => {
      if (event.target.matches('[data-es-sort]')) {
        event.stopImmediatePropagation();
        toast('В статической демонстрационной версии порядок не меняется: его задаёт сервер.');
      }
    }, true);
  });
})();
