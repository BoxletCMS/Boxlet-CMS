/*
 * THE RAIL'S PAGE TAB (PLAN.md D-175, README 4.2): the page's title, address, parent, its SEO
 * words with a picture of a search result, and its publish history, each kept page restored
 * into the draft. Every field writes the document; nothing here is posted.
 */
(function () {
  'use strict';

  var pb = window.pb;
  var panel = document.querySelector('[data-pb-panel="page"]');
  if (!pb || !panel) {
    return;
  }
  var field = function (name) { return panel.querySelector('[data-pb-page="' + name + '"]'); };
  var address = panel.querySelector('[data-pb-address]');
  var serp = panel.querySelector('.pb-serp');
  var slugHint = panel.querySelector('[data-pb-slug-hint]');
  var slugEdited = false;

  /**
   * The address follows the title, but only while the page has never been published and only
   * until the address is touched (as the editor always has). A published page's address never
   * changes behind its author, and an empty address is never generated over: empty means "the
   * home page of this language". The server's Slug is authoritative; this is a convenience.
   */
  function followTitle(doc) {
    if (slugEdited || pb.data.page.state !== 'draft' || (doc.slug || '') === '') {
      return;
    }
    doc.slug = (doc.title || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90);
    field('slug').value = doc.slug;
  }

  function seo() {
    try { return JSON.parse(pb.doc.seo_json || '{}') || {}; } catch (e) { return {}; }
  }

  /** The whole address as it would be: the parent's, or the language's home, and the slug. */
  function shown() {
    var parent = field('parent_id');
    var base = parent.options[parent.selectedIndex].getAttribute('data-url') || address.getAttribute('data-home');
    var slug = pb.doc.slug || '';
    var path = slug === '' && parent.value === '' ? base : base.replace(/\/$/, '') + '/' + slug;
    var url = window.location.origin + path;
    address.textContent = url;
    slugHint.textContent = slug === '' ? slugHint.getAttribute('data-home-hint') : (pb.data.page.state === 'draft' && !slugEdited ? slugHint.getAttribute('data-auto-hint') : '');
    var s = seo();
    serp.querySelector('[data-pb-serp-title]').textContent = (s.title || pb.doc.title || '') + ' · ' + serp.getAttribute('data-site');
    serp.querySelector('[data-pb-serp-url]').textContent = url.replace(/^https?:\/\//, '');
    serp.querySelector('[data-pb-serp-text]').textContent = s.description || '';
    document.querySelector('[data-pb-title]').textContent = pb.doc.title || '';
  }

  function fill() {
    var s = seo();
    field('title').value = pb.doc.title || '';
    field('slug').value = pb.doc.slug || '';
    field('parent_id').value = pb.doc.parent_id === null || pb.doc.parent_id === undefined ? '' : String(pb.doc.parent_id);
    field('seo_title').value = s.title || '';
    field('seo_title').placeholder = pb.doc.title || '';
    field('seo_description').value = s.description || '';
    field('noindex').checked = s.noindex === true;
    shown();
  }

  panel.addEventListener('input', function (event) {
    var name = event.target.getAttribute('data-pb-page');
    if (!name) {
      return;
    }
    var value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
    pb.change(function (doc) {
      if (name === 'title' || name === 'slug') {
        doc[name] = value;
        if (name === 'slug') { slugEdited = true; } else { followTitle(doc); }
      } else if (name === 'parent_id') {
        doc.parent_id = value === '' ? null : Number(value);
      } else {
        var s = seo();
        s[{ seo_title: 'title', seo_description: 'description', noindex: 'noindex' }[name]] = value;
        if (s.noindex !== true) { delete s.noindex; }
        doc.seo_json = JSON.stringify(s);
      }
    }, { coalesce: 'page.' + name, quiet: true });
    shown();
  });
  panel.addEventListener('change', function (event) {
    if (event.target.matches('select, input[type="checkbox"]')) {
      event.target.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  panel.addEventListener('click', function (event) {
    var restore = event.target.closest('[data-pb-restore]');
    if (!restore) {
      return;
    }
    pb.flush().catch(function () {}).then(function () {
      return pb.api(pb.data.endpoints.restore, { revision: Number(restore.getAttribute('data-pb-restore')), version: pb.version });
    }).then(function (answer) {
      if (answer && answer.json && answer.json.document) {
        pb.reset(answer.json.document, answer.json.version);
        pb.emit('state', answer.json.state);
        pb.emit('notice', pb.t('page.restored'));
      }
    });
  });

  pb.on('replace', fill);
  fill();
})();
