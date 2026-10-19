import { form } from './form.js';

// Live "n/max" character counters for the description textareas.
export function initCounters() {
  form.querySelectorAll('.df-count').forEach((counter) => {
    const field = counter.closest('.df-field').querySelector('textarea');
    const max = counter.dataset.max;
    const update = () => (counter.textContent = field.value.length + '/' + max);
    field.addEventListener('input', update);
    update();
  });
}
