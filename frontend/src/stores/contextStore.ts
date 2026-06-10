import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiClient } from '../services/api';
import { getPageInfo } from '../services/pageScanner';

export interface ThemeInfo {
  name: string;
  version: string;
  template: string;
  stylesheet: string;
  author: string;
}

export interface PluginInfo {
  name: string;
  slug: string;
  version: string;
  active: boolean;
}

export interface MenuItem {
  title: string;
  slug: string;
  url: string;
  children?: MenuItem[];
}

export interface CurrentPageInfo {
  screenId: string;
  pageTitle: string;
  pageUrl: string;
  isAdmin: boolean;
}

export interface SiteInfo {
  name: string;
  url: string;
  adminUrl: string;
  version: string;
  language: string;
}

export interface UserInfo {
  displayName: string;
  username: string;
  email: string;
  roles: string[];
  isAdmin: boolean;
}

export interface ContextData {
  site: SiteInfo;
  user: UserInfo;
  theme: ThemeInfo;
  allPlugins: PluginInfo[];
  activePlugins: PluginInfo[];
  inactivePlugins: PluginInfo[];
  totalPlugins: number;
  activeCount: number;
  menus: MenuItem[];
  currentPage: CurrentPageInfo;
}

export const useContextStore = defineStore('context', () => {
  const site = ref<SiteInfo | null>(null);
  const user = ref<UserInfo | null>(null);
  const theme = ref<ThemeInfo | null>(null);
  const plugins = ref<PluginInfo[]>([]);
  const inactivePlugins = ref<PluginInfo[]>([]);
  const menus = ref<MenuItem[]>([]);
  const currentPage = ref<CurrentPageInfo | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const activePlugins = computed(() => plugins.value.filter((p) => p.active));
  const activeCount = computed(() => activePlugins.value.length);
  const totalPlugins = computed(() => plugins.value.length);

  /**
   * Fetch all WordPress context from the backend REST API.
   *
   * The backend provides site/user/theme/plugins/menus reliably,
   * but currentPage from the backend is unreliable during REST requests
   * because get_current_screen() is null outside admin page loads.
   *
   * We augment currentPage with browser-side detection via getPageInfo()
   * which reads window.location and document.title directly.
   */
  async function fetchContext(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
      const data = await apiClient.get<ContextData>('/context');
      site.value = data.site || null;
      user.value = data.user || null;
      theme.value = data.theme || null;
      plugins.value = data.allPlugins || data.activePlugins || [];
      inactivePlugins.value = data.inactivePlugins || [];
      menus.value = data.menus || [];

      // Backend currentPage is unreliable during REST API requests
      // because get_current_screen() returns null outside admin page loads.
      // Use browser-side detection as the authoritative source.
      const backendPage = data.currentPage;
      const browserPage = getPageInfo();

      // Merge: prefer browser values, fall back to backend if browser is empty
      currentPage.value = {
        screenId: browserPage.screenId || backendPage?.screenId || '',
        pageTitle: browserPage.title || backendPage?.pageTitle || '',
        pageUrl: browserPage.url || backendPage?.pageUrl || '',
        isAdmin: backendPage?.isAdmin ?? browserPage.isAdmin,
      };
    } catch (e: any) {
      error.value = e.message || 'Failed to fetch context';
      // Even if backend fails, try to populate currentPage from browser
      try {
        const browserPage = getPageInfo();
        currentPage.value = {
          screenId: browserPage.screenId,
          pageTitle: browserPage.title,
          pageUrl: browserPage.url,
          isAdmin: browserPage.isAdmin,
        };
      } catch {
        // Browser detection failed — currentPage stays null
      }
    } finally {
      loading.value = false;
    }
  }

  async function refreshContext(): Promise<void> {
    await fetchContext();
  }

  /**
   * Get a comprehensive text summary of ALL context for the AI system prompt.
   * This is injected into every system prompt so the AI always knows the full picture.
   */
  function getContextSummary(): string {
    const lines: string[] = [];

    // ---- SITE INFO ----
    if (site.value) {
      lines.push('=== SITE INFO ===');
      lines.push(`Site Name: ${site.value.name}`);
      lines.push(`Site URL: ${site.value.url}`);
      lines.push(`Admin URL: ${site.value.adminUrl}`);
      lines.push(`WordPress Version: ${site.value.version}`);
      lines.push(`Language: ${site.value.language}`);
      lines.push('');
    }

    // ---- CURRENT USER ----
    if (user.value) {
      lines.push('=== CURRENT USER ===');
      lines.push(`Name: ${user.value.displayName}`);
      lines.push(`Username: ${user.value.username}`);
      lines.push(`Roles: ${user.value.roles.join(', ')}`);
      lines.push('');
    }

    // ---- CURRENT PAGE ----
    if (currentPage.value) {
      lines.push('=== CURRENT PAGE ===');
      lines.push(`Title: ${currentPage.value.pageTitle}`);
      lines.push(`Screen ID: ${currentPage.value.screenId}`);
      lines.push(`URL: ${currentPage.value.pageUrl}`);
      lines.push('');
    }

    // ---- THEME ----
    if (theme.value) {
      lines.push('=== ACTIVE THEME ===');
      lines.push(`Name: ${theme.value.name} (v${theme.value.version})`);
      if (theme.value.author) lines.push(`Author: ${theme.value.author}`);
      lines.push('');
    }

    // ---- PLUGINS (ALL) ----
    if (plugins.value.length > 0) {
      lines.push('=== ALL INSTALLED PLUGINS ===');
      const activeList = plugins.value.filter(p => p.active);
      const inactiveList = plugins.value.filter(p => !p.active);

      if (activeList.length > 0) {
        lines.push(`Active (${activeList.length}):`);
        for (const p of activeList) {
          lines.push(`  - ${p.name} v${p.version} [ACTIVE]`);
        }
      }
      if (inactiveList.length > 0) {
        lines.push(`Inactive (${inactiveList.length}):`);
        for (const p of inactiveList) {
          lines.push(`  - ${p.name} v${p.version} [INACTIVE]`);
        }
      }
      lines.push(`Total: ${plugins.value.length} plugins (${activeList.length} active, ${inactiveList.length} inactive)`);
      lines.push('');
    }

    // ---- ADMIN SIDEBAR MENU ----
    if (menus.value.length > 0) {
      lines.push('=== ADMIN SIDEBAR MENU ===');
      for (const menu of menus.value) {
        appendMenuItem(menu, '', lines);
      }
      lines.push('');
    }

    return lines.join('\n');
  }

  /**
   * Recursively append a menu item and all its children to the lines array.
   * Handles any nesting depth — WordPress core uses 2 levels but plugins
   * may add deeper submenus (e.g., WooCommerce → Settings → Advanced → Features).
   */
  function appendMenuItem(
    item: MenuItem,
    indent: string,
    lines: string[],
  ): void {
    // Top-level items use ▸, nested items use ↳
    const prefix = indent === '' ? '▸' : '↳';
    lines.push(`${indent}${prefix} ${item.title} → ${item.url}`);

    if (item.children && item.children.length > 0) {
      const childIndent = indent + '    ';
      for (const child of item.children) {
        appendMenuItem(child, childIndent, lines);
      }
    }
  }

  return {
    site,
    user,
    theme,
    plugins,
    inactivePlugins,
    menus,
    currentPage,
    loading,
    error,
    activePlugins,
    activeCount,
    totalPlugins,
    fetchContext,
    refreshContext,
    getContextSummary,
  };
});
