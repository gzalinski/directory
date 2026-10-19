// Directory archive: list view <-> map view on top of FacetWP.
//
// Split view like https://facetwp.com/demo/state-parks/:
// - "View Map" (toolbar toggle) shows cards on the left and a sticky map on
//   the right; the page scrolls the cards. Map filtering turns ON: panning /
//   zooming filters the cards.
// - "Close Map" (same toggle, or Esc) returns to full-width cards and turns
//   map filtering OFF, refreshing the listing if the viewport filtered it.
//
// FacetWP JS API used (https://facetwp.com/help-center/developers/javascript-reference/):
//   FWP.facets, FWP.refresh(), FWP.reset(), FWP.hooks, FWP.settings.pager,
//   FWP_MAP.is_filtering / FWP_MAP.map, FWP_JSON.map.filterText / resetText,
//   events: facetwp-refresh, facetwp-loaded, facetwp-maps-loaded.

const root = document.querySelector('.directory-archive');
if (root) {
  initClusterColors();
  init(root);
}

// Cluster icons: same look and size rule as the default MarkerClusterer
// renderer (blue = small, red = large), in brand colors.
// https://facetwp.com/help-center/facets/facet-types/map/advanced-map-customizations/customize-marker-clustering/
function initClusterColors() {
  const SMALL = '#243b55'; // instead of default blue
  const LARGE = '#b98f5c'; // instead of default red
  let done = false;

  const renderer = {
    render({ count, position }, stats) {
      const color = count > Math.max(10, stats.clusters.markers.mean) ? LARGE : SMALL;
      const svg =
        '<svg fill="' + color + '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 240" width="50" height="50">' +
        '<circle cx="120" cy="120" opacity=".6" r="70"/>' +
        '<circle cx="120" cy="120" opacity=".3" r="90"/>' +
        '<circle cx="120" cy="120" opacity=".2" r="110"/>' +
        '<text x="50%" y="50%" style="fill:#fff" text-anchor="middle" font-size="50" ' +
        'dominant-baseline="middle" font-family="roboto,arial,sans-serif">' + count + '</text></svg>';
      const content = new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement;
      return new google.maps.marker.AdvancedMarkerElement({
        position,
        content,
        title: count + ' providers',
        zIndex: 1000000 + count, // clusters above single markers
      });
    },
  };

  // Must be registered before the map builds its clusterer (first refresh).
  const register = () => {
    if (done || typeof window.FWP === 'undefined' || !FWP.hooks) return;
    done = true;
    FWP.hooks.addFilter('facetwp_map/clusterer', (clusterargs) => {
      clusterargs.renderer = renderer;
      return clusterargs;
    });
  };
  register();
  document.addEventListener('DOMContentLoaded', register);
  document.addEventListener('facetwp-refresh', register);
}

function init(root) {
  const MAP = root.dataset.mapFacet;
  const PROX = root.dataset.proximityFacet;
  // TCK_DIRECTORY_MAP_FILTERING (PHP): false = the map never filters the cards.
  const FILTERING = root.dataset.mapFiltering !== '0';
  // Map filtering button labels from directory_map_button_labels() (inc/facets.php).
  let labels = {};
  try { labels = JSON.parse(root.dataset.mapLabels || '{}'); } catch (e) {}

  const el = {
    toggle: root.querySelector('[data-dir-toggle-map]'), // "View Map" / "Close Map"
    toolbar: root.querySelector('.dir-toolbar'),
    results: root.querySelector('[data-dir-results]'),
    mapview: root.querySelector('.dir-mapview'),
    search: root.querySelector('[data-dir-search]'),
    reset: root.querySelector('[data-dir-reset]'),
    distWrap: root.querySelector('[data-dir-distance-wrap]'),
    dist: root.querySelector('[data-dir-distance]'),
  };

  let mapMode = false;
  let booted = false;
  let lastProx = null; // last Proximity value, to detect a new location search
  let lastOthers = null; // every facet except the map + page, to spot map-only refreshes
  let mapOnlyRefresh = false; // refresh caused only by moving the map
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
    on = on && FILTERING;
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

  // Fixed header height above the page, from CSS: --dir-offset-top (px).
  function offsetTop() {
    return parseFloat(getComputedStyle(root).getPropertyValue('--dir-offset-top')) || 0;
  }

  // Scroll the page so `node` sits just below the fixed header.
  function scrollToNode(node) {
    const y = node.getBoundingClientRect().top + window.scrollY - offsetTop() - 8;
    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
  }

  /* ---- Views --------------------------------------------------------- */

  function openMap() {
    if (mapMode) return;
    mapMode = true;
    root.classList.add('is-map-view');
    el.mapview.setAttribute('aria-hidden', 'false');
    el.toggle.setAttribute('aria-expanded', 'true');
    whenMap(() => setFiltering(true));
    scrollToNode(el.toolbar);
  }

  function closeMap() {
    if (!mapMode) return;
    mapMode = false;
    refilterAfterLoad = false;
    root.classList.remove('is-map-view');
    el.mapview.setAttribute('aria-hidden', 'true');
    el.toggle.setAttribute('aria-expanded', 'false');
    setActiveMarker(null);
    if (fallbackPopup) fallbackPopup.close();

    // Only refresh when the viewport actually filtered the results.
    const hadBounds = facetValue(MAP).length > 0;
    setFiltering(false);
    if (hadBounds && hasFWP()) FWP.refresh();

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
  // Clear the Proximity location (value + inputs) without a refresh.
  function clearProximity() {
    FWP.facets[PROX] = [];
    root.querySelectorAll('.facetwp-facet-' + PROX + ' input').forEach((input) => {
      if (input.type !== 'checkbox' && input.type !== 'radio') input.value = '';
    });
  }

  // Proximity and map bounds are both location filters and FacetWP ANDs
  // them, so "25 mi around New York" + "Los Angeles viewport" = no results.
  // Only one of them may be active in a request:
  //  - a new location was entered  -> it wins, the viewport is dropped;
  //  - the map area was searched   -> the viewport wins, the location is dropped.
  // Fires after FacetWP has parsed facet values, before the AJAX request.
  // Facet state without the map viewport (+ current page): unchanged = map move.
  function othersState() {
    const facets = Object.assign({}, FWP.facets);
    delete facets[MAP];
    return JSON.stringify([facets, FWP.paged || 1]);
  }

  document.addEventListener('facetwp-refresh', () => {
    if (!hasFWP()) return;
    if (!FILTERING) {
      // Filtering disabled in PHP: never send map bounds.
      if (typeof window.FWP_MAP !== 'undefined') FWP_MAP.is_filtering = false;
      FWP.facets[MAP] = [];
    }
    mapOnlyRefresh = lastOthers !== null && othersState() === lastOthers;
    const prox = JSON.stringify(facetValue(PROX));
    const proxChanged = lastProx !== null && prox !== lastProx;
    const filtering = typeof window.FWP_MAP !== 'undefined' && FWP_MAP.is_filtering;

    if (proxChanged && facetValue(PROX).length) {
      if (mapMode && filtering) {
        // Let the map zoom to the new location (FacetWP cannot auto-zoom while
        // map filtering is on), then switch filtering back on.
        FWP_MAP.is_filtering = false;
        refilterAfterLoad = true;
      }
      FWP.facets[MAP] = [];
    } else if (facetValue(MAP).length && facetValue(PROX).length) {
      clearProximity();
    }
  });

  document.addEventListener('facetwp-loaded', () => {
    if (!booted) {
      booted = true;
      FWP.hooks.addAction('facetwp/reset', () => {
        if (mapMode) refilterAfterLoad = true;
        setFiltering(false);
      });
      // Map bounds in the URL while filtering is disabled: drop them.
      if (!FILTERING && facetValue(MAP).length) {
        FWP.refresh();
        return;
      }
      // Shared / reloaded URL that already holds map bounds: open the map view.
      if (facetValue(MAP).length) {
        openMap();
        // Old links can hold both a location and map bounds (= no results):
        // keep the viewport, drop the location.
        if (facetValue(PROX).length) {
          clearProximity();
          lastProx = '[]';
          FWP.refresh();
          return;
        }
      }
    }

    lastProx = JSON.stringify(facetValue(PROX));
    if (refilterAfterLoad && mapMode) {
      refilterAfterLoad = false;
      refilterWhenIdle();
    }

    // New filters / page while scrolled down the cards: back to the first card.
    // Not when only the map was moved: the visitor is using the map there.
    if (mapMode && !mapOnlyRefresh && el.results.getBoundingClientRect().top < offsetTop()) {
      scrollToNode(el.toolbar);
    }
    mapOnlyRefresh = false;
    lastOthers = othersState();
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

  /* ---- "Show Location" on cards (split view) ------------------------ */

  let fallbackPopup = null; // our own info window, only when the marker can't be opened

  const isStacked = () => getComputedStyle(root.querySelector('.dir-stage')).display !== 'grid';
  const popupOpenFor = (id) => !!el.mapview.querySelector('.gm-style-iw .dir-iw[data-post-id="' + id + '"]');

  function openListingPopup(card, id, position) {
    if (fallbackPopup) fallbackPopup.close();
    const marker = el.mapview.querySelector('.dir-marker[data-post-id="' + id + '"]');
    if (marker) {
      setActiveMarker(marker);
      marker.click(); // FacetWP opens its own popup for this marker
    }
    // Marker still clustered / not clickable: show the same popup ourselves.
    setTimeout(() => {
      if (popupOpenFor(id)) return;
      const tpl = card.querySelector('.dir-card-row__popup');
      if (!tpl) return;
      fallbackPopup = fallbackPopup || new google.maps.InfoWindow({ maxWidth: 480 });
      fallbackPopup.setContent(tpl.innerHTML);
      fallbackPopup.setOptions({ pixelOffset: new google.maps.Size(0, -32) });
      fallbackPopup.setPosition(position);
      fallbackPopup.open({ map: FWP_MAP.map });
    }, 350);
  }

  function showLocation(card) {
    const id = card.dataset.postId;
    const [lat, lng] = (card.dataset.latlng || '').split(',').map(parseFloat);
    if (!id || isNaN(lat) || isNaN(lng)) return;

    whenMap(() => {
      const map = FWP_MAP.map;
      const position = { lat, lng };
      if (isStacked()) scrollToNode(el.mapview); // map is above the cards on small screens

      // Move without re-filtering the cards by the new viewport.
      const wasFiltering = !!FWP_MAP.is_filtering;
      FWP_MAP.is_filtering = false;
      google.maps.event.addListenerOnce(map, 'idle', () => {
        setTimeout(() => {
          FWP_MAP.is_filtering = wasFiltering;
          openListingPopup(card, id, position);
        }, 150);
      });
      map.panTo(position);
      if ((map.getZoom() || 0) < 15) map.setZoom(15);
    });
  }

  root.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-dir-show-location]');
    if (btn && mapMode) showLocation(btn.closest('.dir-card-row'));
  });

  // Ctrl + mouse wheel over the map zooms the map only: the page must not
  // scroll (or browser-zoom). Listening on window in the bubble phase lets
  // Google Maps handle the wheel first; we only cancel the browser default.
  window.addEventListener(
    'wheel',
    (e) => {
      if ((e.ctrlKey || e.metaKey) && e.target instanceof Element && el.mapview.contains(e.target)) {
        e.preventDefault();
      }
    },
    { passive: false }
  );

  /* ---- UI ------------------------------------------------------------ */

  el.toggle.addEventListener('click', () => (mapMode ? closeMap() : openMap()));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mapMode && !e.target.closest('input, select, textarea')) closeMap();
  });
  el.search.addEventListener('click', () => hasFWP() && FWP.refresh());
  if (el.reset) el.reset.addEventListener('click', () => hasFWP() && FWP.reset());

  // Without FacetWP the listing still works; the map button has nothing to show.
  if (!document.querySelector('.facetwp-type-map, .facetwp-facet-' + MAP)) el.toggle.hidden = true;
}
