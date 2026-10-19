// Directory archive: list view <-> map view on top of FacetWP.
//
// Split view like https://facetwp.com/demo/state-parks/:
// - "View Map" (toolbar) shows cards on the left and a sticky map on the right
//   and turns map filtering ON: panning / zooming filters the cards.
// - "Close Map" (top-right of the map, or Esc) returns to full-width cards and
//   turns map filtering OFF, refreshing the listing if the viewport filtered it.
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
  // Map filtering button labels from directory_map_button_labels() (inc/facets.php).
  let labels = {};
  try { labels = JSON.parse(root.dataset.mapLabels || '{}'); } catch (e) {}

  const el = {
    open: root.querySelector('[data-dir-open-map]'),
    close: root.querySelector('[data-dir-close-map]'),
    mapview: root.querySelector('.dir-mapview'),
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
    const fwp = (window.FWP_JSON && FWP_JSON.map) || {};
    const text = on ? labels.reset || fwp.resetText : labels.filter || fwp.filterText;
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
    setActiveMarker(null);

    // Only refresh when the viewport actually filtered the results.
    const hadBounds = facetValue(MAP).length > 0;
    setFiltering(false);
    if (hadBounds && hasFWP()) FWP.refresh();

    el.open.focus({ preventScroll: true });
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

  /* ---- Active marker (#B98F5C while its popup is open) ---------------- */

  // Markers are <span class="dir-marker"> (facetwp_map_marker_args markerHtml).
  // Hover color is pure CSS; "open" needs a class because the popup is a
  // Google info window, so we watch for it appearing / disappearing.
  let activeMarker = null;

  function setActiveMarker(marker) {
    if (activeMarker && activeMarker !== marker) activeMarker.classList.remove('is-active');
    activeMarker = marker;
    if (marker) marker.classList.add('is-active');
  }

  function watchMarkers() {
    const mapEl = el.mapview.querySelector('#facetwp-map') || el.mapview;
    mapEl.addEventListener(
      'click',
      (e) => {
        const marker = e.target.closest('.dir-marker');
        if (marker) setActiveMarker(marker);
      },
      true
    );
    // Popup closed (X button, map click, refresh): clear the active marker.
    new MutationObserver(() => {
      if (activeMarker && (!activeMarker.isConnected || !mapEl.querySelector('.gm-style-iw'))) {
        setActiveMarker(null);
      }
    }).observe(mapEl, { childList: true, subtree: true });
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
  });

  document.addEventListener('facetwp-maps-loaded', () => {
    // FacetWP's own button click handler reads these, so set them once.
    if (window.FWP_JSON && FWP_JSON.map) {
      if (labels.filter) FWP_JSON.map.filterText = labels.filter;
      if (labels.reset) FWP_JSON.map.resetText = labels.reset;
    }
    syncFilterButton();
  });

  whenMap(watchMarkers);

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
