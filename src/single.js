// Single Directory listing: Google map + copy link.

// Clean map: no POI / transit clutter, but street, neighborhood and city names stay.
const MAP_STYLE = [
  { elementType: 'geometry', stylers: [{ color: '#f1efe9' }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#5b5b5b' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: '#ffffff' }, { weight: 3 }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
  { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#dddbd3' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#f7e6c4' }] },
  { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#2b2b2b' }] },
  { featureType: 'administrative.neighborhood', elementType: 'labels', stylers: [{ visibility: 'on' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#dbe6f0' }] },
];

window.initDirectoryMap = function () {
  const el = document.getElementById('directory-map');
  if (!el || !el.dataset.latlng) return;
  const [lat, lng] = el.dataset.latlng.split(',').map(parseFloat);
  if (isNaN(lat) || isNaN(lng)) return;

  const position = { lat, lng };
  const map = new google.maps.Map(el, {
    center: position,
    zoom: 14,
    disableDefaultUI: true,
    zoomControl: true,
    gestureHandling: 'cooperative',
    styles: MAP_STYLE,
  });
  new google.maps.Marker({ position, map });
};

document.querySelectorAll('[data-copy-link]').forEach((btn) => {
  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      const icon = btn.querySelector('i');
      const old = icon.className;
      icon.className = 'fa-solid fa-check';
      setTimeout(() => (icon.className = old), 1500);
    } catch (e) {
      window.prompt('Copy this link:', window.location.href);
    }
  });
});

// Distance (miles, straight line) between the visitor and the listing.
// Needs the visitor's permission; hidden when unavailable.
(function initDistance() {
  const root = document.querySelector('.directory-single');
  const target = (root?.dataset.latlng || '').split(',').map(parseFloat);
  const slots = document.querySelectorAll('.dir-distance');
  const log = (msg, ...a) => console.warn('[directory] distance: ' + msg, ...a);
  if (!slots.length) return log('no distance slots in the template.');
  if (target.length !== 2 || target.some(isNaN)) {
    return log('this listing has no saved coordinates (data-latlng="' + (root?.dataset.latlng || '') + '"). Re-save the address by picking a suggestion.');
  }
  if (!window.isSecureContext) return log('geolocation needs HTTPS or localhost; this page is not a secure context.');

  const KEY = 'directoryVisitorPos';

  function miles([lat1, lng1], [lat2, lng2]) {
    const R = 3958.8; // Earth radius in miles
    const rad = (d) => (d * Math.PI) / 180;
    const a =
      Math.sin(rad(lat2 - lat1) / 2) ** 2 +
      Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(rad((lng2 - lng1)) / 2) ** 2;
    return 2 * R * Math.asin(Math.sqrt(a));
  }

  function show(pos) {
    const d = miles(pos, target);
    const text = (d < 0.1 ? '<0.1' : d < 10 ? d.toFixed(1) : Math.round(d).toLocaleString('en-US')) + ' miles';
    slots.forEach((slot) => {
      slot.querySelector('[data-distance]').textContent = text;
      slot.hidden = false;
    });
  }

  try {
    const cached = JSON.parse(sessionStorage.getItem(KEY));
    if (cached && cached.length === 2) return show(cached);
  } catch (e) {}

  if (!navigator.geolocation) return log('geolocation not supported by this browser.');
  navigator.geolocation.getCurrentPosition(
    ({ coords }) => {
      const pos = [coords.latitude, coords.longitude];
      try { sessionStorage.setItem(KEY, JSON.stringify(pos)); } catch (e) {}
      show(pos);
    },
    (err) => log('could not get your position (' + err.code + ': ' + err.message + ').'), // keep hidden
    { timeout: 10000, maximumAge: 600000 }
  );
})();
