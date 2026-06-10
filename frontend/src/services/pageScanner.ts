/**
 * Page Scanner - Frontend-only DOM scanning.
 * Never reads files, database, or PHP source.
 * Scans only what is visible in the DOM.
 */

export interface PageInfo {
  url: string;
  title: string;
  screenId: string;
  isAdmin: boolean;
}

export interface ScannedPage {
  labels: ScannedLabel[];
  buttons: ScannedButton[];
  headings: ScannedHeading[];
  inputs: ScannedInput[];
  links: ScannedLink[];
  tables: ScannedTable[];
  tabs: ScannedTab[];
}

export interface ScannedLabel {
  text: string;
  for: string;
  selector: string;
}

export interface ScannedButton {
  text: string;
  type: string;
  selector: string;
}

export interface ScannedHeading {
  text: string;
  level: number;
  selector: string;
}

export interface ScannedInput {
  name: string;
  type: string;
  placeholder: string;
  selector: string;
  label: string;
}

export interface ScannedLink {
  text: string;
  href: string;
  selector: string;
}

export interface ScannedTable {
  caption: string;
  headers: string[];
  rowCount: number;
  selector: string;
}

export interface ScannedTab {
  text: string;
  selector: string;
}

/**
 * Get current page information entirely from the browser (no backend call).
 * Extracts URL, title, and screen ID from the DOM and window.location.
 */
export function getPageInfo(): PageInfo {
  const url = window.location.href;
  const title = document.title || '';

  // Extract screen ID from WordPress body classes
  // WordPress adds classes like: toplevel_page_woocommerce, settings_page_wc-settings, etc.
  let screenId = '';
  const body = document.body;
  if (body) {
    const classes = body.className.split(/\s+/);
    for (const cls of classes) {
      if (cls.startsWith('toplevel_page_') || cls.startsWith('settings_page_') ||
          cls.startsWith('dashboard_page_') || cls.startsWith('posts_page_') ||
          cls.startsWith('media_page_') || cls.startsWith('pages_page_') ||
          cls.startsWith('comments_page_') || cls.startsWith('appearance_page_') ||
          cls.startsWith('plugins_page_') || cls.startsWith('users_page_') ||
          cls.startsWith('tools_page_') || cls.startsWith('options_page_') ||
          cls.startsWith('admin_page_') || cls.startsWith('product_page_') ||
          cls.startsWith('shop_order_page_') || cls.startsWith('edit-php') ||
          cls.startsWith('post-php') || cls.startsWith('woocommerce_page_')) {
        screenId = cls;
        break;
      }
    }
    // Fallback: use wp-admin body class pattern
    if (!screenId) {
      const adminClass = classes.find(c => c.endsWith('_page_') || c.includes('-php') || c === 'wp-admin');
      if (adminClass) screenId = adminClass;
    }
  }

  const isAdmin = window.asdevsAiAssistant?.isAdmin ?? true;

  return { url, title, screenId, isAdmin };
}

/**
 * Scan the current page's DOM and return a structured summary.
 * Only scans admin content area to avoid noise.
 */
export function scanCurrentPage(): ScannedPage {
  // Target the main admin content area
  const contentArea =
    document.querySelector('#wpbody-content') ||
    document.querySelector('#wpcontent') ||
    document.body;

  const labels: ScannedLabel[] = [];
  const buttons: ScannedButton[] = [];
  const headings: ScannedHeading[] = [];
  const inputs: ScannedInput[] = [];
  const links: ScannedLink[] = [];
  const tables: ScannedTable[] = [];
  const tabs: ScannedTab[] = [];

  // Scan labels
  contentArea.querySelectorAll('label').forEach((el, i) => {
    const htmlEl = el as HTMLLabelElement;
    const text = htmlEl.innerText?.trim();
    if (text && text.length < 200) {
      const sel = buildSelector(el, i, 'label');
      labels.push({
        text,
        for: htmlEl.htmlFor || '',
        selector: sel,
      });
    }
  });

  // Scan buttons
  contentArea.querySelectorAll('button, .button, .wp-core-ui .button, [role="button"]').forEach((el, i) => {
    const htmlEl = el as HTMLElement;
    const text = htmlEl.innerText?.trim();
    if (text && text.length < 100) {
      buttons.push({
        text,
        type: (htmlEl as HTMLButtonElement).type || 'button',
        selector: buildSelector(el, i, 'button'),
      });
    }
  });

  // Scan headings
  contentArea.querySelectorAll('h1, h2, h3, h4, h5, h6').forEach((el, i) => {
    const text = el.textContent?.trim();
    if (text && text.length < 200) {
      headings.push({
        text,
        level: parseInt(el.tagName[1]),
        selector: buildSelector(el, i, el.tagName.toLowerCase()),
      });
    }
  });

  // Scan inputs
  contentArea.querySelectorAll('input, select, textarea').forEach((el, i) => {
    const input = el as HTMLInputElement;
    const id = input.id;
    let labelText = '';

    if (id) {
      const label = document.querySelector(`label[for="${CSS.escape(id)}"]`);
      if (label) {
        labelText = label.textContent?.trim() || '';
      }
    }

    const sel = buildSelector(el, i, input.tagName.toLowerCase());
    inputs.push({
      name: input.name || '',
      type: input.type || 'text',
      placeholder: input.placeholder || '',
      selector: sel,
      label: labelText,
    });
  });

  // Scan links inside admin content
  contentArea.querySelectorAll('a[href]').forEach((el, i) => {
    const anchor = el as HTMLAnchorElement;
    const text = anchor.innerText?.trim();
    const href = anchor.getAttribute('href') || '';
    // Only include admin links
    if (text && href && (href.startsWith(window.asdevsAiAssistant?.adminUrl || '/wp-admin') || href.startsWith('admin.php') || href.startsWith('/'))) {
      links.push({
        text,
        href,
        selector: buildSelector(el, i, 'a'),
      });
    }
  });

  // Scan tables
  contentArea.querySelectorAll('table.wp-list-table, table.form-table, table.widefat').forEach((el, i) => {
    const table = el as HTMLTableElement;
    const caption = table.caption?.textContent?.trim() || '';
    const headers: string[] = [];
    table.querySelectorAll('th').forEach((th) => {
      const hText = th.textContent?.trim();
      if (hText) headers.push(hText);
    });
    const rows = table.querySelectorAll('tbody tr').length;

    tables.push({
      caption,
      headers,
      rowCount: rows,
      selector: buildSelector(el, i, 'table'),
    });
  });

  // Scan tabs (common in WooCommerce, settings pages, etc.)
  contentArea.querySelectorAll('.nav-tab-wrapper .nav-tab, .wc-tabs .nav-tab, [role="tab"]').forEach((el, i) => {
    const text = el.textContent?.trim();
    if (text) {
      tabs.push({
        text,
        selector: buildSelector(el, i, 'tab'),
      });
    }
  });

  return {
    labels,
    buttons,
    headings,
    inputs,
    links,
    tables,
    tabs,
  };
}

/**
 * Build a unique-ish CSS selector for an element.
 */
function buildSelector(el: Element, index: number, tagName: string): string {
  const id = el.id;
  if (id) return `#${CSS.escape(id)}`;

  const classes = Array.from(el.classList)
    .filter((c) => !c.startsWith('asdevs-'))
    .slice(0, 3);
  if (classes.length > 0) {
    return `${tagName}.${classes.map((c) => CSS.escape(c)).join('.')}`;
  }

  const name = el.getAttribute('name');
  if (name) return `${tagName}[name="${CSS.escape(name)}"]`;

  return `${tagName}:nth-of-type(${index + 1})`;
}

/**
 * Get a text summary of the scanned page suitable for AI context.
 */
export function getPageScanSummary(): string {
  const scan = scanCurrentPage();
  const parts: string[] = [];

  if (scan.headings.length > 0) {
    const hText = scan.headings
      .slice(0, 10)
      .map((h) => `H${h.level}: "${h.text}"`)
      .join(', ');
    parts.push(`Headings: ${hText}`);
  }

  if (scan.labels.length > 0) {
    const lText = scan.labels
      .slice(0, 15)
      .map((l) => `"${l.text}"`)
      .join(', ');
    parts.push(`Labels: ${lText}`);
  }

  if (scan.buttons.length > 0) {
    const bText = scan.buttons
      .slice(0, 15)
      .map((b) => `"${b.text}"`)
      .join(', ');
    parts.push(`Buttons: ${bText}`);
  }

  if (scan.inputs.length > 0) {
    const iText = scan.inputs
      .filter((i) => i.label)
      .slice(0, 10)
      .map((i) => `"${i.label}"`)
      .join(', ');
    if (iText) parts.push(`Form fields: ${iText}`);
  }

  if (scan.tabs.length > 0) {
    const tText = scan.tabs.map((t) => `"${t.text}"`).join(', ');
    parts.push(`Tabs: ${tText}`);
  }

  if (scan.tables.length > 0) {
    parts.push(`Tables found: ${scan.tables.length}`);
  }

  return parts.join('\n');
}

/**
 * Get complete page context — page info + DOM scan summary.
 * This is the single entry point for the scan_current_page tool.
 * Everything runs in the browser; no backend calls.
 */
export function getFullPageContext(): { page: PageInfo; scan: ScannedPage; summary: string; contextText: string } {
  const page = getPageInfo();
  const scan = scanCurrentPage();
  const summary = getPageScanSummary();

  const lines: string[] = [];
  lines.push('=== CURRENT PAGE (from browser) ===');
  lines.push(`URL: ${page.url}`);
  lines.push(`Title: ${page.title}`);
  if (page.screenId) lines.push(`Screen ID: ${page.screenId}`);
  lines.push('');
  if (summary) {
    lines.push('=== PAGE CONTENT (DOM scan) ===');
    lines.push(summary);
  }

  return {
    page,
    scan,
    summary,
    contextText: lines.join('\n'),
  };
}
