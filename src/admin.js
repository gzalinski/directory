import { form } from './js/form.js';
import { initCounters } from './js/counters.js';
import { initServices } from './js/services.js';
import { initAvatar } from './js/avatar.js';
import { initLocation } from './js/location.js';
import { initPhone } from './js/phone.js';
import { initEmail } from './js/email.js';
import { initSubmit } from './js/submit.js';
import './js/categories.js';

if (form && typeof DirectoryForm !== 'undefined') {
  initCounters();
  initServices();
  initAvatar();
  initLocation();
  initPhone();
  initEmail();
  initSubmit();
}
