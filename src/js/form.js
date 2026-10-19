// Shared form context + helpers used by all components.
export const form = document.getElementById('directory-edit-form');

export const $ = (selector, ctx = form) => ctx.querySelector(selector);

// `link` (optional): { href, text } appended to the message as an anchor.
export function showStatus(msg, isError, link) {
  const status = $('[data-role="status"]');
  status.textContent = msg;
  if (link) {
    const a = document.createElement('a');
    a.href = link.href;
    a.textContent = link.text;
    status.append(' ', a);
  }
  status.hidden = false;
  status.classList.toggle('is-error', !!isError);
  status.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
