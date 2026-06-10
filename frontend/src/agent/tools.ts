/**
 * LangChain-inspired Tool system.
 * Each tool has a name, description, parameter schema (for AI function calling),
 * and an execute method that calls WordPress REST API endpoints.
 *
 * Architecture per spec:
 * - AI NEVER accesses WordPress directly
 * - AI only interacts through Tools
 * - Each tool wraps a WordPress REST endpoint
 */

import { apiClient } from '../services/api';
import { getFullPageContext } from '../services/pageScanner';
import { highlightElement } from '../services/highlightService';
import { useNavigationStore } from '../stores/navigationStore';

export interface ToolParameterProperty {
  type: string;
  description: string;
  enum?: string[];
}

export interface ToolSchema {
  type: 'object';
  properties: Record<string, ToolParameterProperty>;
  required?: string[];
}

export interface ToolResult {
  success: boolean;
  data?: any;
  message?: string;
}

export class Tool {
  constructor(
    public name: string,
    public description: string,
    public schema: ToolSchema,
    public execute: (args: Record<string, any>) => Promise<ToolResult>,
  ) {}

  /**
   * Get OpenAI-compatible function definition.
   */
  toFunctionDefinition(): {
    type: 'function';
    function: {
      name: string;
      description: string;
      parameters: ToolSchema;
    };
  } {
    return {
      type: 'function',
      function: {
        name: this.name,
        description: this.description,
        parameters: this.schema,
      },
    };
  }
}

// ---- Tool Definitions ----

export const getThemeTool = new Tool(
  'get_theme',
  'Get information about the currently active WordPress theme including name, version, and template.',
  { type: 'object', properties: {} },
  async () => {
    try {
      const data = await apiClient.get<any>('/theme');
      return { success: true, data };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const getPluginsTool = new Tool(
  'get_plugins',
  'Get the list of all installed WordPress plugins and their active status. Use this to check if specific plugins like Elementor or WooCommerce are installed.',
  { type: 'object', properties: {} },
  async () => {
    try {
      const data = await apiClient.get<any>('/plugins');
      return { success: true, data };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const getMenusTool = new Tool(
  'get_menus',
  'Get the WordPress admin sidebar menu structure including all menu items and submenus with their URLs. Use this to find where specific settings pages are located.',
  { type: 'object', properties: {} },
  async () => {
    try {
      const data = await apiClient.get<any>('/menus');
      return { success: true, data };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const navigateUserTool = new Tool(
  'navigate_user',
  'Navigate the user to a specific WordPress admin page. Provide the admin page slug (relative path like "admin.php?page=wc-settings&tab=advanced" or "edit.php?post_type=page"). The backend will construct the full URL to avoid errors.',
  {
    type: 'object',
    properties: {
      slug: { type: 'string', description: 'The admin page slug (relative path from wp-admin, e.g. "admin.php?page=wc-settings&tab=advanced&section=features"). Get this from the get_menus tool url field.' },
      reason: { type: 'string', description: 'Brief explanation in the user\'s language for why you are taking them to this page' },
    },
    required: ['slug'],
  },
  async ({ slug, reason }) => {
    if (!slug) return { success: false, message: 'Slug is required.' };

    // Clean the slug: extract relative path from whatever the AI provides
    let cleanSlug = slug;

    // If it's a full URL, extract the relative path
    if (cleanSlug.startsWith('http://') || cleanSlug.startsWith('https://')) {
      try {
        const u = new URL(cleanSlug);
        cleanSlug = u.pathname.replace(/^\/?(wp-admin\/)?/, '');
        if (u.search) cleanSlug += u.search;
      } catch {
        // If URL parsing fails, try regex fallback
        cleanSlug = cleanSlug.replace(/^https?:\/\/[^\/]+\/?(wp-admin\/)?/, '');
      }
    } else {
      // Strip any wp-admin/ prefix
      cleanSlug = cleanSlug.replace(/^\/?(wp-admin\/)?/, '');
    }

    try {
      // Send only the slug to the backend — backend constructs the full URL
      const result = await apiClient.post<{ success: boolean; url: string }>('/navigate', { slug: cleanSlug });
      if (result.success) {
        useNavigationStore().prepareRedirect(result.url, reason || 'Navigating');
        return { success: true, data: { url: result.url, reason }, message: `Navigating to: ${result.url}` };
      }
      return { success: false, message: 'Navigation validation failed.' };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const highlightElementTool = new Tool(
  'highlight_element',
  'Visually highlight a specific element on the current admin page with a glowing yellow border and tooltip. ' +
  'MANDATORY: You MUST call this tool whenever the user asks "where is X?", "find X", "show me X", ' +
  'or any similar query looking for a specific setting, field, button, or option. ' +
  'Never skip highlighting — describing in text is NOT enough when the user wants to find something visually.' +
  '\n\nCRITICAL — CHOOSING THE RIGHT SELECTOR:' +
  '\n- Prefer targeting a VISIBLE PARENT CONTAINER (div, form row, field wrapper, card, panel) instead of the exact element. ' +
  'The highlight ring needs enough visible area to be seen — at least 10px wide/tall.' +
  '\n- For select2/SelectWoo/Chosen dropdowns: the original <select> is hidden! Target the visible .select2-container instead, ' +
  'or the parent .form-field / .cmb-row wrapper div that contains the whole field.' +
  '\n- For tiny elements (hidden inputs, small icons, inline spans): walk UP one or two levels to a parent div that provides a meaningful visual area.' +
  '\n- When scan_current_page returns a selector for a label or input, prefer the parent form row (e.g., tr.form-field, div.cmb-row, .form-table tr) ' +
  'so the entire field area lights up, not just the tiny input box.' +
  '\n- Good selectors look like: "#wpbody-content .form-table tr", "div.cmb-row", ".woocommerce-input-wrapper", "#postbox-container-2 .postbox".' +
  '\n- Bad selectors: hidden <select> elements, 0×0px containers, individual <input> elements that are only 20px tall.' +
  '\n\nIf unsure, use a broader parent selector — the system will automatically narrow to the best visible parent, ' +
  'but starting from a good parent gives the most reliable result.',
  {
    type: 'object',
    properties: {
      selector: { type: 'string', description: 'CSS selector for the element or parent container to highlight. Prefer a visible parent wrapper (div, form row, field container) over tiny/hidden elements. For select2/SelectWoo, use .select2-container or the parent .form-field div instead of the hidden <select>.' },
      message: { type: 'string', description: 'Helpful instruction for the user about this element (e.g. "This is where you change your site title. Type your new title here.")' },
      title: { type: 'string', description: 'Short tooltip title (e.g. "✅ Site Title", "📝 Here it is!"). Keep it under 30 characters.' },
    },
    required: ['selector'],
  },
  async ({ selector, message, title }) => {
    if (!selector) return { success: false, message: 'Selector is required.' };
    try {
      highlightElement({ selector, message: message || '', title: title || '' });
      return { success: true, data: { selector }, message: `Highlighted: ${selector}` };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const scanCurrentPageTool = new Tool(
  'scan_current_page',
  'Scan the current admin page entirely in the browser. Returns page info (URL, title, screen ID) plus all visible form fields, buttons, headings, labels, tabs, and tables. Use this to understand what page the user is on and what settings are available. No backend call is made — everything runs in the browser.',
  { type: 'object', properties: {} },
  async () => {
    try {
      const ctx = getFullPageContext();
      return {
        success: true,
        data: {
          page: ctx.page,
          contextText: ctx.contextText,
          summary: ctx.summary,
          labels: ctx.scan.labels.slice(0, 30),
          buttons: ctx.scan.buttons.slice(0, 20),
          headings: ctx.scan.headings.slice(0, 15),
          inputs: ctx.scan.inputs.slice(0, 20),
          tabs: ctx.scan.tabs.slice(0, 10),
        },
      };
    } catch (e: any) {
      return { success: false, message: e.message };
    }
  },
);

export const availableTools: Tool[] = [
  getThemeTool,
  getPluginsTool,
  getMenusTool,
  navigateUserTool,
  highlightElementTool,
  scanCurrentPageTool,
];

export function getToolByName(name: string): Tool | undefined {
  return availableTools.find((t) => t.name === name);
}
