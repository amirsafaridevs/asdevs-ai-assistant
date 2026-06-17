/**
 * Highlight Service - Sets a data-asdevs-ai-highlight attribute on target elements
 * and optionally shows a tooltip. No overlay or spotlight effect.
 * Frontend-only. No backend interaction.
 *
 * Using a data attribute (instead of a CSS class) ensures the highlight styles
 * are applied via the [data-asdevs-ai-highlight] CSS selector defined in widget.css,
 * which uses !important to reliably override WordPress admin's high-specificity styles.
 */

export interface HighlightOptions {
  selector: string;
  message?: string;
  title?: string;
  nextStep?: string;
}

const HIGHLIGHT_ATTR = 'data-asdevs-ai-highlight';
const SCAN_ATTR = 'data-asdevs-scan';

let currentHighlight: {
  element: Element;
  tooltip: HTMLDivElement | null;
} | null = null;

/**
 * Try to find the target element using multiple strategies.
 *
 * 1. Try the exact selector (works for IDs, name attrs, data-asdevs-scan attrs)
 * 2. If the selector looks like a [data-asdevs-scan="N"] attr and fails,
 *    the element may have been removed/re-rendered — log a warning
 * 3. If selector is a class-based selector, try with fewer classes as fallback
 */
function findTargetElement(selector: string): Element | null {
  // Strategy 1: direct querySelector
  const direct = document.querySelector(selector);
  if (direct) return direct;

  // Strategy 2: if it's a data-asdevs-scan selector, the element may have
  // been re-rendered (e.g., React/Vue in admin).
  if (selector.startsWith(`[${SCAN_ATTR}=`)) {
    console.warn(
      `[ASDevs AI] Direct selector "${selector}" not found. ` +
      `A DOM re-render may have removed the data attribute.`
    );
    return null;
  }

  // Strategy 3: if selector is tag.class1.class2, try with just the first class
  if (/^[a-z]+\.[a-z]/.test(selector)) {
    const parts = selector.split('.');
    const tag = parts[0];
    const firstClass = parts[1];
    if (firstClass) {
      const fallback = document.querySelector(`${tag}.${CSS.escape(firstClass)}`);
      if (fallback) {
        console.warn(
          `[ASDevs AI] Exact selector "${selector}" not found, ` +
          `but found fallback: "${tag}.${firstClass}"`
        );
        return fallback;
      }
    }
  }

  return null;
}

/**
 * Check whether an element is actually visible and large enough
 * to show a meaningful highlight ring. Small/hidden elements
 * (e.g., original <select> hidden by select2, 0×0 containers)
 * won't show the highlight properly.
 */
function isElementHighlightable(el: Element): boolean {
  const rect = el.getBoundingClientRect();
  // Must have both width and height of at least 10px to be visible
  if (rect.width < 10 || rect.height < 10) return false;

  const style = window.getComputedStyle(el);
  if (style.display === 'none') return false;
  if (style.visibility === 'hidden') return false;
  if (parseFloat(style.opacity) === 0) return false;

  return true;
}

/**
 * Walk up the DOM tree to find the nearest visible parent that is
 * large enough to show a meaningful highlight. Handles cases where:
 * - The target is a hidden <select> replaced by select2/SelectWoo
 * - The target is inside a widget/panel that provides the visible area
 * - The target is a tiny inline element (e.g., a hidden input)
 *
 * Max walk depth: 6 levels. If no suitable parent is found,
 * returns the original element as a fallback.
 */
function findVisibleParent(el: Element): Element {
  // If the element itself is highlightable, return it
  if (isElementHighlightable(el)) return el;

  // Special case: select2/SelectWoo — the original <select> is hidden,
  // and a .select2-container is rendered as a sibling or nearby.
  if (el.tagName === 'SELECT') {
    // Look for select2 container next to or wrapping the select
    const select2Container =
      el.nextElementSibling?.matches('.select2-container') ? el.nextElementSibling :
      el.parentElement?.querySelector('.select2-container');
    if (select2Container && select2Container instanceof Element && isElementHighlightable(select2Container)) {
      console.log('[ASDevs AI] Highlight redirected from hidden <select> to .select2-container');
      return select2Container;
    }
  }

  // Walk up the DOM tree
  let current: Element | null = el.parentElement;
  let depth = 0;
  const maxDepth = 6;

  while (current && depth < maxDepth) {
    if (isElementHighlightable(current)) {
      console.log(
        `[ASDevs AI] Highlight walked up ${depth + 1} level(s) ` +
        `from <${el.tagName.toLowerCase()}> to <${current.tagName.toLowerCase()}> ` +
        `(target was too small/hidden: ${el.getBoundingClientRect().width}×${el.getBoundingClientRect().height})`
      );
      return current;
    }
    current = current.parentElement;
    depth++;
  }

  // Fallback: return the original element even if not ideal
  return el;
}

/**
 * Highlight an element on the page by setting a data attribute.
 * The [data-asdevs-ai-highlight] CSS selector in widget.css provides the visual styling.
 *
 * If the target element is too small or hidden (e.g., a <select> replaced by select2),
 * the highlight automatically walks up to the nearest visible parent container
 * so the visual ring is always visible to the user.
 *
 * Optionally shows a tooltip with a message.
 */
export function highlightElement(options: HighlightOptions): void {
  // Remove any existing highlight first
  clearHighlight();

  const rawTarget = findTargetElement(options.selector);
  if (!rawTarget) {
    console.warn(
      `[ASDevs AI] Highlight target not found: "${options.selector}". ` +
      `The element may have been removed from the DOM or the page may have changed. ` +
      `Try running scan_current_page again to get fresh selectors.`
    );
    return;
  }

  // Walk up to a visible parent if the direct target is too small/hidden
  const target = findVisibleParent(rawTarget);

  // Set the data attribute that triggers the highlight CSS
  target.setAttribute(HIGHLIGHT_ATTR, '');

  // Create tooltip if there's a message or title
  let tooltip: HTMLDivElement | null = null;
  if (options.message || options.title) {
    tooltip = createTooltip(target, options);
  }

  currentHighlight = { element: target, tooltip };

  // Scroll target into view
  target.scrollIntoView({ behavior: 'smooth', block: 'center' });

  // Listen for clear event
  const clearHandler = () => clearHighlight();
  document.addEventListener('asdevs:clearHighlight', clearHandler, { once: true });
}

/**
 * Create a tooltip positioned near the highlighted element.
 */
function createTooltip(
  target: Element,
  options: HighlightOptions
): HTMLDivElement {
  const rect = target.getBoundingClientRect();
  const tooltip = document.createElement('div');
  tooltip.className = 'asdevs-tooltip';

  const tooltipTop = rect.bottom + 12;
  const tooltipLeft = Math.min(rect.left, window.innerWidth - 300);

  let tooltipHTML = '';
  if (options.title) {
    tooltipHTML += `<div class="asdevs-tooltip-title">${escapeHtml(options.title)}</div>`;
  }
  if (options.message) {
    tooltipHTML += `<div class="asdevs-tooltip-desc">${escapeHtml(options.message)}</div>`;
  }
  if (options.nextStep) {
    tooltipHTML += `<button class="asdevs-tooltip-action" onclick="document.dispatchEvent(new CustomEvent('asdevs:clearHighlight'))">${escapeHtml(options.nextStep)} →</button>`;
  }
  tooltipHTML += `<button class="asdevs-tooltip-close" onclick="document.dispatchEvent(new CustomEvent('asdevs:clearHighlight'))">✕</button>`;

  tooltip.innerHTML = tooltipHTML;
  tooltip.style.cssText = `
    position: fixed;
    left: ${tooltipLeft}px;
    top: ${tooltipTop}px;
    z-index: 100001;
    background: var(--asdevs-surface, #fff);
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.12);
    padding: 14px 18px;
    max-width: 280px;
    border: 1px solid rgba(0,0,0,0.06);
    animation: asdevs-tooltip-in 200ms ease-out;
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
  `;

  // Inject tooltip animation keyframes if not present
  if (!document.getElementById('asdevs-tooltip-keyframes')) {
    const tipStyle = document.createElement('style');
    tipStyle.id = 'asdevs-tooltip-keyframes';
    tipStyle.textContent = `
      @keyframes asdevs-tooltip-in {
        from { opacity: 0; transform: translateY(6px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(tipStyle);
  }

  document.body.appendChild(tooltip);
  return tooltip;
}

/**
 * Clear the current highlight and remove the tooltip.
 * Also cleans up any lingering data-asdevs-scan and data-asdevs-ai-highlight
 * attributes from previous scans and highlights.
 */
export function clearHighlight(): void {
  // Clean up all data-asdevs-scan attributes from previous scans
  document.querySelectorAll(`[${SCAN_ATTR}]`).forEach((el) => {
    el.removeAttribute(SCAN_ATTR);
  });

  // Clean up all data-asdevs-ai-highlight attributes
  document.querySelectorAll(`[${HIGHLIGHT_ATTR}]`).forEach((el) => {
    el.removeAttribute(HIGHLIGHT_ATTR);
  });

  if (!currentHighlight) return;

  const { element, tooltip } = currentHighlight;

  // Remove the highlight data attribute from the element (already done above,
  // but this ensures the specific element is cleaned even if another element
  // somehow got the attribute)
  element.removeAttribute(HIGHLIGHT_ATTR);

  // Fade out and remove tooltip
  if (tooltip) {
    tooltip.style.opacity = '0';
    tooltip.style.transition = 'opacity 200ms ease';
    setTimeout(() => {
      if (tooltip.parentNode) {
        tooltip.parentNode.removeChild(tooltip);
      }
    }, 200);
  }

  currentHighlight = null;
}

/**
 * Check if a highlight is currently active.
 */
export function isHighlightActive(): boolean {
  return currentHighlight !== null;
}

function escapeHtml(str: string): string {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}
