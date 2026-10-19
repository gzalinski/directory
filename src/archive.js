// Directory archive: list view <-> map view on top of FacetWP.
//
// - "View Map" hides cards + pager, shows the Map facet full width and turns
//   map filtering ON (the map viewport becomes a filter).
// - "Close Map" (or Esc) returns to cards + pager and turns map filtering OFF,
//   refreshing the listing if a viewport filter had been applied.
//
// FacetWP JS API used (https://facetwp.com/help-center/developers/javascript-reference/):
//   FWP.facets, FWP.refresh(), FWP.reset(), FWP.hooks, FWP.settings.pager,
//   FWP_MAP.is_filtering / FWP_MAP.map, FWP_JSON.map.filterText / resetText,
//   events: facetwp-refresh, facetwp-loaded, facetwp-maps-loaded.

const root = document.querySelector('.directory-archive');
if (root) init(root);

function init(root) {
  const MAP = root.dataset.mapFacet;
  const PROX = root.dataset.proximityFacet;
  const POS_KEY = 'directoryVisitorPos'; // shared with single.js

  const el = {
    open: root.querySelector('[data-dir-open-map]'),
    close: root.querySelector('[data-dir-close-map]'),
    mapview: root.querySelector('.dir-mapview'),
    count: root.querySelector('[data-dir-map-count]'),
    search: root.querySelector('[data-dir-search]'),
    reset: root.querySelector('[data-dir-reset]'),
    distWrap: root.querySelector('[data-dir-distance-wrap]'),
    dist: root.querySelector('[data-dir-distance]'),
  };

  let mapMode = false;
  let booted = false;
  let lastProx = null; // last Proximity value, to detect a new location search
  let refilterAfterLoad = false; // re-enable map filtering after FacetWP fits the new bounds

  const hasFWP = () => typeof window.FWP !== 'undefined';
  const hasMap = () => typeof window.FWP_MAP !== 'undefined' && !!window.FWP_MAP.map;
  const facetValue = (name) => (hasFWP() && FWP.facets[name]) || [];

  // Run cb once the Google map behind the Map facet exists.
  function whenMap(cb) {
    if (hasMap()) return cb();
    document.addEventListener('facetwp-maps-loaded', () => cb(), { once: true });
  }

  /* ---- Map filtering ------------------------------------------------- */

  // Keep FacetWP's own "Enable map filtering" button in line with the state.
  function syncFilterButton() {
    const btn = root.querySelector('.facetwp-map-filtering');
    if (!btn || typeof window.FWP_MAP === 'undefined') return;
    const on = !!FWP_MAP.is_filtering;
    btn.classList.toggle('enabled', on);
    const labels = window.FWP_JSON && FWP_JSON.map;
    const text = labels && (on ? labels.resetText : labels.filterText);
    if (text) btn.textContent = text;
  }

  function setFiltering(on) {
    if (typeof window.FWP_MAP === 'undefined') return;
    FWP_MAP.is_filtering = on;
    if (!on && hasFWP()) FWP.facets[MAP] = [];
    syncFilterButton();
  }

  // After a refresh that moved the map (new location / reset), switch
  // filtering back on once the map has settled on its new bounds.
  function refilterWhenIdle() {
    whenMap(() => {
      let done = false;
      const enable = () => {
        if (done || !mapMode) return;
        done = true;
        setFiltering(true);
      };
      google.maps.event.addListenerOnce(FWP_MAP.map, 'idle', enable);
      setTimeout(enable, 800); // idle may already have fired
    });
  }

  /* ---- Views --------------------------------------------------------- */

  function openMap() {
    if (mapMode) return;
    mapMode = true;
    root.classList.add('is-map-view');
    el.mapview.setAttribute('aria-hidden', 'false');
    el.open.setAttribute('aria-expanded', 'true');
    whenMap(() => setFiltering(true));
    updateCount();
    root.querySelector('.dir-stage').scrollIntoView({ behavior: 'smooth', block: 'start' });
    el.close.focus({ preventScroll: true });
  }

  function closeMap() {
    if (!mapMode) return;
    mapMode = false;
    refilterAfterLoad = false;
    root.classList.remove('is-map-view');
    el.mapview.setAttribute('aria-hidden', 'true');
    el.open.setAttribute('aria-expanded', 'false');

    // Only refresh when the viewport actually filtered the results.
    const hadBounds = facetValue(MAP).length > 0;
    setFiltering(false);
    if (hadBounds && hasFWP()) FWP.refresh();

    el.open.focus({ preventScroll: true });
    root.querySelector('.dir-toolbar').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function updateCount() {
    if (!el.count || !hasFWP() || !FWP.settings || !FWP.settings.pager) return;
    const n = parseInt(FWP.settings.pager.total_rows, 10) || 0;
    const noun = n === 1 ? 'provider' : 'providers';
    const where = typeof window.FWP_MAP !== 'undefined' && FWP_MAP.is_filtering ? ' in this area' : '';
    el.count.textContent = n.toLocaleString('en-US') + ' ' + noun + where;
  }

  /* ---- Distance pill (mirrors the Proximity radius dropdown) --------- */

  function proximityRadius() {
    return root.querySelector('.facetwp-facet-' + PROX + ' .facetwp-radius');
  }

  function syncDistance() {
    const src = proximityRadius();
    el.distWrap.hidden = !src;
    if (!src) return;
    if (el.dist.innerHTML !== src.innerHTML) el.dist.innerHTML = src.innerHTML;
    el.dist.value = src.value;
  }

  el.dist.addEventListener('change', () => {
    const src = proximityRadius();
    if (!src) return;
    src.value = el.dist.value;
    // FacetWP listens (delegated) for change on its radius select.
    src.dispatchEvent(new Event('change', { bubbles: true }));
  });

  /* ---- Client-side distance on cards (no location entered) ----------- */

  function miles([lat1, lng1], [lat2, lng2]) {
    const rad = (d) => (d * Math.PI) / 180;
    const a =
      Math.sin(rad(lat2 - lat1) / 2) ** 2 +
      Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad(lng2 - lng1) / 2) ** 2;
    return 2 * 3958.8 * Math.asin(Math.sqrt(a));
  }

  function formatMiles(d) {
    return (d < 0.1 ? '<0.1' : d < 10 ? d.toFixed(1) : Math.round(d).toLocaleString('en-US')) + ' miles';
  }

  function fillDistances(pos) {
    root.querySelectorAll('.dir-card-row[data-latlng]').forEach((card) => {
      const slot = card.querySelector('.dir-loc__distance');
      if (!slot || slot.hasAttribute('data-server')) return; // FacetWP distance wins
      const target = card.dataset.latlng.split(',').map(parseFloat);
      if (target.length !== 2 || target.some(isNaN)) return;
      slot.querySelector('[data-distance]').textContent = formatMiles(miles(pos, target));
      slot.hidden = false;
    });
  }

  // Never prompts: uses a cached position or an already-granted permission.
  async function visitorDistances() {
    try {
      const cached = JSON.parse(sessionStorage.getItem(POS_KEY));
      if (cached && cached.length === 2) return fillDistances(cached);
    } catch (e) {}
    if (!window.isSecureContext || !navigator.geolocation || !navigator.permissions) return;
    try {
      const state = await navigator.permissions.query({ name: 'geolocation' });
      if (state.state !== 'granted') return;
    } catch (e) {
      return;
    }
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        const pos = [coords.latitude, coords.longitude];
        try { sessionStorage.setItem(POS_KEY, JSON.stringify(pos)); } catch (e) {}
        fillDistances(pos);
      },
      () => {},
      { timeout: 10000, maximumAge: 600000 }
    );
  }

  /* ---- "Clear filters" visibility ------------------------------------ */

  function syncReset() {
    if (!el.reset || !hasFWP()) return;
    const active = Object.keys(FWP.facets).some((name) => name !== MAP && (FWP.facets[name] || []).length);
    el.reset.hidden = !active;
  }

  /* ---- FacetWP events ------------------------------------------------ */

  // Fires after FacetWP has parsed facet values, before the AJAX request.
  document.addEventListener('facetwp-refresh', () => {
    if (!mapMode || typeof window.FWP_MAP === 'undefined' || !FWP_MAP.is_filtering) return;
    // A new location search must be allowed to zoom the map: FacetWP cannot
    // auto-zoom while map filtering is on, so drop the viewport for this
    // request and switch filtering back on afterwards.
    const prox = JSON.stringify(facetValue(PROX));
    if (lastProx !== null && prox !== lastProx) {
      FWP_MAP.is_filtering = false;
      FWP.facets[MAP] = [];
      refilterAfterLoad = true;
    }
  });

  document.addEventListener('facetwp-loaded', () => {
    if (!booted) {
      booted = true;
      FWP.hooks.addAction('facetwp/reset', () => {
        if (mapMode) refilterAfterLoad = true;
        setFiltering(false);
      });
      // Shared / reloaded URL that already holds map bounds: open the map view.
      if (facetValue(MAP).length) openMap();
    }

    lastProx = JSON.stringify(facetValue(PROX));
    if (refilterAfterLoad && mapMode) {
      refilterAfterLoad = false;
      refilterWhenIdle();
    }

    syncFilterButton();
    syncDistance();
    syncReset();
    updateCount();
    visitorDistances();
  });

  document.addEventListener('facetwp-maps-loaded', syncFilterButton);

  /* ---- UI ------------------------------------------------------------ */

  el.open.addEventListener('click', openMap);
  el.close.addEventListener('click', closeMap);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mapMode && !e.target.closest('input, select, textarea')) closeMap();
  });
  el.search.addEventListener('click', () => hasFWP() && FWP.refresh());
  if (el.reset) el.reset.addEventListener('click', () => hasFWP() && FWP.reset());

  // Without FacetWP the listing still works; the map button has nothing to show.
  if (!document.querySelector('.facetwp-type-map, .facetwp-facet-' + MAP)) el.open.hidden = true;
}
