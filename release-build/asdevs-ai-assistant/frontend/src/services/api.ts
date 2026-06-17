/**
 * API client for communicating with the WordPress REST API backend.
 */

const BASE_URL = window.asdevsAiAssistant?.apiUrl || '/wp-json/asdevs-ai-assistant/v1';
const NONCE = window.asdevsAiAssistant?.nonce || '';

interface RequestOptions {
  method?: string;
  body?: any;
  signal?: AbortSignal;
}

class ApiClient {
  private baseUrl: string;
  private nonce: string;

  constructor(baseUrl: string, nonce: string) {
    this.baseUrl = baseUrl;
    this.nonce = nonce;
  }

  async get<T>(endpoint: string, signal?: AbortSignal): Promise<T> {
    return this.request<T>(endpoint, { method: 'GET', signal });
  }

  async post<T>(endpoint: string, body: any, signal?: AbortSignal): Promise<T> {
    return this.request<T>(endpoint, { method: 'POST', body, signal });
  }

  private async request<T>(endpoint: string, options: RequestOptions): Promise<T> {
    const url = `${this.baseUrl}${endpoint}`;

    const headers: Record<string, string> = {
      'X-WP-Nonce': this.nonce,
    };

    if (options.body) {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
      method: options.method || 'GET',
      headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      signal: options.signal,
    });

    if (!response.ok) {
      const errorBody = await response.text();
      throw new Error(`API Error ${response.status}: ${errorBody}`);
    }

    return response.json();
  }
}

export const apiClient = new ApiClient(BASE_URL, NONCE);

/**
 * Get the current WordPress context from the backend.
 */
export async function fetchWordPressContext() {
  return apiClient.get<any>('/context');
}

/**
 * Send a navigation request to the backend using a relative slug.
 * The backend constructs the full admin URL to avoid double wp-admin issues.
 */
export async function requestNavigation(slug: string) {
  return apiClient.post<{ success: boolean; url: string }>('/navigate', { slug });
}
