/**
 * Highlight Service - Manages spotlight overlay, highlight ring, and tooltips.
 * Frontend-only. No backend interaction.
 */

export interface HighlightOptions {
  selector: string;
  message?: string;
  title?: string;
  nextStep?: string;
}

let currentHighlight: {
  overlay: HTMLDivElement;
  cutout: HTMLDivElement;
  ring: HTMLDivElement;
  tooltip: HTMLDivElement;
} | null = null;

/**
 * Highlight an element on the page with a spotlight effect.
 */
export function highlightElement(options: HighlightOptions): void {
  // Remove any existing highlight first
  clearHighlight();

  const target = document.querySelector(options.selector);
  if (!target) {
    console.warn(`[ASDevs AI] Highlight target not found: ${options.selector}`);
    return;
  }

  const rect = target.getBoundingClientRect();
  const padding = 6;

  // Create spotlight overlay
  const overlay = document.createElement('div');
  overlay.className = 'asdevs-spotlight';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:99998;pointer-events:none;';

  // Create cutout (the "hole" in the overlay)
  const cutout = document.createElement('div');
  cutout.className = 'asdevs-spotlight-cutout';
  cutout.style.cssText = `
    position: fixed;
    left: ${rect.left - padding}px;
    top: ${rect.top - padding}px;
    width: ${rect.width + padding * 2}px;
    height: ${rect.height + padding * 2}px;
    border-radius: 8px;
    box-shadow: 0 0 0 9999px rgba(0,0,0,0.45);
    pointer-events: none;
    z-index: 99999;
    transition: all 400ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
  `;

  // Create highlight ring
  const ring = document.createElement('div');
  ring.className = 'asdevs-highlight-ring';
  ring.style.cssText = `
    position: fixed;
    left: ${rect.left - padding}px;
    top: ${rect.top - padding}px;
    width: ${rect.width + padding * 2}px;
    height: ${rect.height + padding * 2}px;
    border: 2px solid #007AFF;
    border-radius: 8px;
    box-shadow: 0 0 0 4px rgba(0,122,255,0.15), 0 0 20px rgba(0,122,255,0.1);
    pointer-events: none;
    z-index: 100000;
    animation: asdevs-ring-pulse 2s ease-in-out infinite;
  `;

  // Inject ring animation if not already present
  if (!document.getElementById('asdevs-keyframes')) {
    const style = document.createElement('style');
    style.id = 'asdevs-keyframes';
    style.textContent = `
      @keyframes asdevs-ring-pulse {
        0%, 100% { box-shadow: 0 0 0 4px rgba(0,122,255,0.15), 0 0 20px rgba(0,122,255,0.1); }
        50% { box-shadow: 0 0 0 8px rgba(0,122,255,0.08), 0 0 30px rgba(0,122,255,0.15); }
      }
    `;
    document.head.appendChild(style);
  }

  // Create tooltip
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

  // Inject tooltip animation
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

  // Scroll target into view
  target.scrollIntoView({ behavior: 'smooth', block: 'center' });

  // Append to body
  document.body.appendChild(overlay);
  document.body.appendChild(cutout);
  document.body.appendChild(ring);
  document.body.appendChild(tooltip);

  currentHighlight = { overlay, cutout, ring, tooltip };

  // Listen for clear event
  const clearHandler = () => clearHighlight();
  document.addEventListener('asdevs:clearHighlight', clearHandler, { once: true });

  // Click on overlay to dismiss
  overlay.style.pointerEvents = 'auto';
  overlay.addEventListener('click', () => {
    clearHighlight();
  });
}

/**
 * Clear the current highlight and all associated elements.
 */
export function clearHighlight(): void {
  if (!currentHighlight) return;

  const { overlay, cutout, ring, tooltip } = currentHighlight;

  // Fade out
  [overlay, cutout, ring, tooltip].forEach((el) => {
    el.style.opacity = '0';
    el.style.transition = 'opacity 200ms ease';
  });

  // Remove after animation
  setTimeout(() => {
    [overlay, cutout, ring, tooltip].forEach((el) => {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    });
  }, 200);

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
