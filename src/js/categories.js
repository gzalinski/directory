import { form } from './form.js';

// Categories tree: at least one category must be checked before saving.
export function categoriesSelected() {
  return form.querySelectorAll('input[name="directory_cat[]"]:checked').length > 0;
}
