import { $, showStatus } from './form.js';

// Address autocomplete with Places API (New): AutocompleteSuggestion +
// Place.fetchFields. Writes the address text and "lat,lng" to the form.
function initPlaces({ AutocompleteSuggestion, AutocompleteSessionToken }) {
  const input = $('#location_address');
  const latlng = $('#location_latlng');
  const cityField = $('#location_city');

  // Wrapper so the dropdown anchors to the input.
  const wrap = document.createElement('div');
  wrap.className = 'df-location-wrap';
  input.replaceWith(wrap);
  wrap.appendChild(input);

  const list = document.createElement('ul');
  list.className = 'df-suggest';
  list.hidden = true;
  wrap.appendChild(list);

  let token = new AutocompleteSessionToken();
  let suggestions = [];
  let active = -1;
  let timer;

  const hide = () => {
    list.hidden = true;
    active = -1;
  };

  function highlight(i) {
    active = i;
    [...list.children].forEach((li, idx) => li.classList.toggle('is-active', idx === i));
  }

  function render() {
    list.innerHTML = '';
    suggestions.forEach((s, i) => {
      const li = document.createElement('li');
      li.textContent = s.placePrediction.text.toString();
      li.addEventListener('mousedown', (e) => {
        e.preventDefault(); // keep focus so blur doesn't hide it first
        choose(i);
      });
      list.appendChild(li);
    });
    list.hidden = suggestions.length === 0;
    active = -1;
  }

  // "City, ST" for US addresses, just "City" elsewhere.
  function cityLabel(components) {
    const find = (...types) => components.find((c) => types.some((t) => c.types.includes(t)));
    const city = find('locality', 'postal_town', 'sublocality', 'administrative_area_level_2');
    if (!city) return '';
    const country = find('country');
    const state = find('administrative_area_level_1');
    return country && country.shortText === 'US' && state
      ? city.longText + ', ' + state.shortText
      : city.longText;
  }

  async function choose(i) {
    const prediction = suggestions[i].placePrediction;
    hide();
    try {
      const place = prediction.toPlace();
      await place.fetchFields({ fields: ['formattedAddress', 'location', 'addressComponents'] });
      input.value = place.formattedAddress || prediction.text.toString();
      latlng.value = place.location.lat() + ',' + place.location.lng();
      cityField.value = cityLabel(place.addressComponents || []);
    } catch (err) {
      console.error('[directory] Could not load place details:', err);
      showStatus('Could not load that address, please try another.', true);
    } finally {
      token = new AutocompleteSessionToken(); // new session after a selection
    }
  }

  async function search() {
    const q = input.value.trim();
    if (q.length < 3) return hide();
    try {
      const res = await AutocompleteSuggestion.fetchAutocompleteSuggestions({
        input: q,
        sessionToken: token,
      });
      suggestions = res.suggestions || [];
      render();
    } catch (err) {
      console.error('[directory] Places autocomplete failed:', err);
      hide();
    }
  }

  input.addEventListener('input', () => {
    latlng.value = ''; // manual edits invalidate stored coordinates
    cityField.value = '';
    clearTimeout(timer);
    timer = setTimeout(search, 250);
  });

  input.addEventListener('keydown', (e) => {
    const open = !list.hidden;
    if (e.key === 'Enter') {
      e.preventDefault();
      if (open && active >= 0) choose(active);
    } else if (e.key === 'ArrowDown' && open) {
      e.preventDefault();
      highlight((active + 1) % suggestions.length);
    } else if (e.key === 'ArrowUp' && open) {
      e.preventDefault();
      highlight((active - 1 + suggestions.length) % suggestions.length);
    } else if (e.key === 'Escape') {
      hide();
    }
  });

  input.addEventListener('blur', hide);
}

export function initLocation() {
  // Key rejected / API not enabled: Google calls this global.
  window.gm_authFailure = () => {
    console.error('[directory] Google Maps rejected the API key (check key restrictions).');
    showStatus('Location search is unavailable (Google Maps key error).', true);
  };

  let tries = 0;
  const t = setInterval(async () => {
    if (window.google?.maps?.importLibrary) {
      clearInterval(t);
      try {
        initPlaces(await google.maps.importLibrary('places'));
      } catch (err) {
        console.error('[directory] Could not load Google Places:', err);
      }
    } else if (++tries > 75) {
      clearInterval(t);
      console.error('[directory] Google Maps script did not load (missing key or blocked request).');
    }
  }, 200);
}
