/** Helpers that operate on the WordPress admin page outside the panel. */

const HIGHLIGHT_CLASS = 'asdevs-ai-page-highlight';
const STYLE_ID = 'asdevs-ai-page-highlight-style';
const MAX_PAGE_CHARS = 8000;

let clearTimer: ReturnType<typeof setTimeout> | null = null;

function ensureHighlightStyle(): void {
  if (document.getElementById(STYLE_ID)) {
    return;
  }

  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = `
.${HIGHLIGHT_CLASS} {
  outline: 3px solid #1f6b4f !important;
  outline-offset: 3px !important;
  box-shadow: 0 0 0 6px rgb(31 107 79 / 22%) !important;
  border-radius: 4px;
  scroll-margin: 96px;
  transition: outline-color 180ms ease, box-shadow 180ms ease;
}
`;
  document.head.appendChild(style);
}

function clearHighlights(): void {
  document.querySelectorAll(`.${HIGHLIGHT_CLASS}`).forEach((node) => {
    node.classList.remove(HIGHLIGHT_CLASS);
  });
}

function isVisible(el: HTMLElement): boolean {
  const style = window.getComputedStyle(el);

  if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
    return false;
  }

  const rect = el.getBoundingClientRect();

  return rect.width > 0 && rect.height > 0;
}

function findByText(text: string): HTMLElement | null {
  const needle = text.trim().toLowerCase();

  if (needle === '') {
    return null;
  }

  const root = document.querySelector('#wpbody-content') ?? document.body;
  const candidates = root.querySelectorAll<HTMLElement>(
    'a, button, label, h1, h2, h3, h4, th, td, span, p, li, .button, .page-title-action, .wp-heading-inline, [role="button"]'
  );

  let best: HTMLElement | null = null;
  let bestScore = Number.POSITIVE_INFINITY;

  for (const el of Array.from(candidates)) {
    if (!isVisible(el) || el.closest('#asdevs-ai-assistant-root')) {
      continue;
    }

    const content = (el.innerText || el.textContent || '').trim().toLowerCase();

    if (content === '' || content.length > 240) {
      continue;
    }

    if (content === needle) {
      return el;
    }

    if (content.includes(needle) && content.length < bestScore) {
      best = el;
      bestScore = content.length;
    }
  }

  return best;
}

/** Collect readable text from the current admin screen. */
export function readCurrentPage(): Record<string, unknown> {
  const title = document.title;
  const heading =
    document.querySelector('#wpbody-content .wrap h1, #wpbody-content h1.wp-heading-inline, #wpbody-content h1')?.textContent?.trim() ??
    '';

  const root = document.querySelector('#wpbody-content') ?? document.body;
  const clone = root.cloneNode(true) as HTMLElement;

  clone.querySelectorAll('#asdevs-ai-assistant-root, script, style, noscript, svg, .notice, #screen-meta, #screen-meta-links').forEach((node) => {
    node.remove();
  });

  let text = (clone.innerText || clone.textContent || '')
    .replace(/\u00a0/g, ' ')
    .replace(/[ \t]+\n/g, '\n')
    .replace(/\n{3,}/g, '\n\n')
    .trim();

  const truncated = text.length > MAX_PAGE_CHARS;
  if (truncated) {
    text = `${text.slice(0, MAX_PAGE_CHARS)}\n…(truncated)`;
  }

  return {
    document_title: title,
    heading,
    truncated,
    content: text,
    url: window.location.href,
  };
}

/** Highlight a DOM node by CSS selector and/or visible text. */
export function highlightOnPage(input: { selector?: string; text?: string }): Record<string, unknown> {
  ensureHighlightStyle();
  clearHighlights();

  if (clearTimer) {
    clearTimeout(clearTimer);
    clearTimer = null;
  }

  const selector = typeof input.selector === 'string' ? input.selector.trim() : '';
  const text = typeof input.text === 'string' ? input.text.trim() : '';

  if (selector === '' && text === '') {
    return { ok: false, error: 'Provide a selector and/or text to highlight.' };
  }

  let target: HTMLElement | null = null;

  if (selector !== '') {
    try {
      const found = document.querySelector(selector);

      if (found instanceof HTMLElement && !found.closest('#asdevs-ai-assistant-root')) {
        target = found;
      }
    } catch {
      return { ok: false, error: 'Invalid CSS selector.' };
    }
  }

  if (!target && text !== '') {
    target = findByText(text);
  }

  if (!target) {
    return { ok: false, error: 'No matching element was found on this page.' };
  }

  target.classList.add(HIGHLIGHT_CLASS);
  target.scrollIntoView({ behavior: 'smooth', block: 'center' });

  clearTimer = setTimeout(() => {
    clearHighlights();
    clearTimer = null;
  }, 12000);

  return {
    ok: true,
    tag: target.tagName.toLowerCase(),
    id: target.id || null,
    className: target.className || null,
    text: (target.innerText || target.textContent || '').trim().slice(0, 160),
  };
}
