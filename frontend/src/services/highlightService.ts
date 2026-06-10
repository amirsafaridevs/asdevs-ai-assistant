/**
 * Highlight Service - Adds a subtle yellow border class to target elements
 * and optionally shows a tooltip. No overlay or spotlight effect.
 * Frontend-only. No backend interaction.
 */

export interface HighlightOptions {
  selector: string;
  message?: string;
  title?: string;
  nextStep?: string;
}

const HIGHLIGHT_CLASS = 'asdevs-highlight';

let currentHighlight: {
  element: Element;
  tooltip: HTMLDivElement | null;
} | null = null;

/**
 * Highlight an element on the page by adding a CSS class.
 * Optionally shows a tooltip with a message.
 */
export function highlightElement(options: HighlightOptions): void {
  // Remove any existing highlight first
  clearHighlight();

  const target = document.querySelector(options.selector);
  if (!target) {
    console.warn(`[ASDevs AI] Highlight target not found: ${options.selector}`);
    return;
  }

  // Add highlight class to the target element
  target.classList.add(HIGHLIGHT_CLASS);

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
 */
export function clearHighlight(): void {
  if (!currentHighlight) return;

  const { element, tooltip } = currentHighlight;

  // Remove highlight class from the element
  element.classList.remove(HIGHLIGHT_CLASS);

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
