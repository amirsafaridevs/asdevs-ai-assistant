/**
 * LangChain-inspired AI Agent.
 *
 * Architecture (per spec):
 * - Frontend calls AI API directly (using API key from WordPress settings)
 * - AI decides which tool to call
 * - Each tool calls a WordPress REST API endpoint
 * - AI NEVER accesses WordPress directly
 *
 * This is a lightweight implementation of the LangChain tool-calling pattern
 * without the heavy LangChain.js dependency (keeps bundle under 150KB gzipped).
 */

import { useChatStore } from '../stores/chatStore';
import { useContextStore } from '../stores/contextStore';
import { useNavigationStore } from '../stores/navigationStore';
import { availableTools, getToolByName, type Tool } from './tools';
import { getFullPageContext } from '../services/pageScanner';

const SYSTEM_PROMPT = `You are the ASDevs AI Assistant — an expert WordPress administrator guide embedded directly in the WordPress admin panel.

=== YOUR IDENTITY ===
You are a friendly, professional, and knowledgeable WordPress GPS. Your sole purpose is to help the logged-in user navigate the WordPress admin area, locate settings, understand plugins, and configure their site. You speak the user's language fluently and adapt your tone to match theirs.

=== CRITICAL RULES (NEVER VIOLATE THESE) ===
1. READ-ONLY: You CANNOT modify any settings, files, database records, or WordPress options. You cannot activate/deactivate plugins, switch themes, or execute any code.
2. GUIDE ONLY: Your only power is navigation and explanation. You guide users TO settings, you never change them.
3. NO FALSE CLAIMS: Never claim you can do something you cannot. Be honest about your limitations.
4. SLUGS ONLY: When navigating, ALWAYS pass the exact relative slug from the menu (e.g. "admin.php?page=wc-settings"). NEVER construct full URLs. The backend builds the final URL.
5. USER'S LANGUAGE: Always respond in the same language the user writes in. Match their tone — casual for casual users, technical for developers.

=== AVAILABLE TOOLS ===
- get_theme: Get detailed active theme information
- get_plugins: Get the full list of installed plugins with active/inactive status
- get_menus: Get the complete admin sidebar menu structure with slugs
- navigate_user: Redirect the user to any WordPress admin page (takes a slug, NOT a full URL)
- highlight_element: Visually highlight a specific element on the current page
- scan_current_page: Scan the current page entirely in the browser — returns page info (URL, title, screen ID) plus all visible form fields, buttons, headings, labels, tabs, and tables. No backend call.

=== STANDARD WORKFLOW ===
When a user asks "Where is X?" or "How do I find Y?":
1. CHECK CONTEXT: You already have the full menu structure, plugin list, and theme info in your system prompt. Use it first before calling tools.
2. FIND THE SLUG: Locate the correct admin page slug from the menu structure already provided to you.
3. NAVIGATE: Call navigate_user with that exact slug. The backend constructs the URL.
4. AFTER ARRIVAL: The system will scan the page and show you what's visible. Explain it to the user.

=== WHEN YOU ALREADY KNOW THE ANSWER ===
If the context already contains the information the user needs (menu structure, plugin list, theme), answer directly without calling any tools. Only call tools when you genuinely need fresh data.

=== RESPONSE STYLE ===
- Be concise but thorough. No fluff.
- Use formatting: **bold** for emphasis, \`code\` for slugs/technical terms.
- When listing plugins or menu items, present them clearly.
- Always provide the exact slug so the user can navigate manually if needed.`;

const CONTINUATION_PROMPT = `=== CRITICAL: YOU ARE ALREADY ON THE TARGET PAGE ===
Navigation is COMPLETE. The user has been successfully redirected.
You are NOW viewing the correct WordPress admin page.
DO NOT call navigate_user, get_menus, or get_plugins again.
Your ONLY task is to explain what the user sees on this page.
Respond with text only. Be helpful and concise.`;

const TOOL_STATUS: Record<string, string> = {
  get_theme: '🔍 Fetching theme...',
  get_plugins: '🔍 Scanning plugins...',
  get_menus: '📋 Analyzing menus...',
  navigate_user: '🧭 Finding settings...',
  highlight_element: '✨ Highlighting...',
  scan_current_page: '🔎 Scanning page...',
};

interface AIToolCall {
  id: string;
  type: 'function';
  function: { name: string; arguments: string };
}

interface AIResponse {
  content: string | null;
  tool_calls?: AIToolCall[];
}

export class AIAgent {
  private abortController: AbortController | null = null;

  async processMessage(userInput: string): Promise<void> {
    const chatStore = useChatStore();
    const contextStore = useContextStore();
    const navStore = useNavigationStore();

    if (!window.asdevsAiAssistant?.apiKey) {
      chatStore.addMessage('assistant',
        '⚠️ AI Assistant is not configured. Please set your API key in **AI Assistant → Settings**.');
      return;
    }

    chatStore.addMessage('user', userInput);
    chatStore.loading = true;
    this.abortController = new AbortController();

    try {
      const messages = this.buildMessages(userInput);
      await this.agentLoop(messages, 0);
    } catch (error: any) {
      if (error.name !== 'AbortError') {
        chatStore.addMessage('assistant', `Error: ${error.message || 'Unknown'}`);
      }
    } finally {
      chatStore.loading = false;
      this.abortController = null;
    }
  }

  /**
   * Called after page reload when post-navigation continuation is needed.
   *
   * Strategy: Does NOT use the agent loop (which could trigger navigate_user again).
   * Instead, directly fetches page info + scans the page, then sends a single
   * completion to the AI with NO tools, so the AI can only respond with text.
   */
  async continueAfterNavigation(): Promise<void> {
    const chatStore = useChatStore();
    const navStore = useNavigationStore();

    if (!window.asdevsAiAssistant?.apiKey) {
      chatStore.addMessage('assistant',
        '⚠️ AI Assistant is not configured. Please set your API key in **AI Assistant → Settings**.');
      return;
    }

    // Clear the post-navigation flag
    navStore.clearPostNavigation();

    // Remove ALL intermediate tool status messages to avoid confusing the AI
    // Keep only the original user question and meaningful assistant responses
    const keep = chatStore.messages.filter((m) => {
      // Keep user messages
      if (m.role === 'user') return true;
      // Remove navigation cards
      if (m.navigationCard) return false;
      // Remove tool messages (their results are no longer relevant after navigation)
      if (m.role === 'tool') return false;
      // Remove tool status messages (✅, 🔄, etc.)
      if (m.role === 'assistant' && /^[🔍📋📍🧭✨🔎💭⚙️✅❌⚠️🔄]/.test(m.content)) return false;
      // Remove empty assistant messages (thinking placeholders)
      if (m.role === 'assistant' && !m.content.trim()) return false;
      // Keep meaningful assistant responses, but strip toolCalls since
      // corresponding tool results have been removed
      if (m.role === 'assistant') {
        // Remove stale toolCalls so the AI doesn't see orphaned function calls
        delete m.toolCalls;
        return true;
      }
      return false;
    });
    // Reactive-safe mutation of Pinia ref array
    chatStore.messages.length = 0;
    chatStore.messages.push(...keep);

    chatStore.loading = true;
    this.abortController = new AbortController();

    try {
      // STEP 1: Get complete page context (runs entirely in the browser, no backend call)
      let pageContextText = '';
      try {
        const { getFullPageContext } = await import('../services/pageScanner');
        const ctx = getFullPageContext();
        if (ctx.contextText) {
          pageContextText = ctx.contextText;
        }
      } catch {
        pageContextText = 'Unable to scan page';
      }

      // STEP 2: Build a single-shot completion with NO tools
      // The AI can only respond with text — it CANNOT call navigate_user
      const contextStore = useContextStore();
      let systemContent = SYSTEM_PROMPT + '\n\n' +
        CONTINUATION_PROMPT + '\n\n';
      if (pageContextText) {
        systemContent += 'PAGE CONTEXT (browser scan):\n' + pageContextText + '\n\n';
      }
      // Always include the full WordPress context
      const ctx = contextStore.getContextSummary();
      if (ctx) systemContent += '\n' + ctx;
      if (navStore.currentTask) systemContent += '\n\nACTIVE TASK: ' + navStore.currentTask;

      const messages: any[] = [{ role: 'system', content: systemContent }];

      // Add cleaned conversation history
      for (const msg of chatStore.messages.slice(-10)) {
        if (msg.role === 'user' || msg.role === 'assistant') {
          messages.push({ role: msg.role, content: msg.content });
        }
      }

      // Call AI WITHOUT tools — single completion with streaming, no agent loop
      const streamMsg = chatStore.addMessage('assistant', '');
      const response = await this.callAI(messages, false, (token: string) => {
        chatStore.updateMessage(streamMsg.id, {
          content: (chatStore.messages.find((m) => m.id === streamMsg.id)?.content || '') + token,
        });
      });

      const finalContent = chatStore.messages.find((m) => m.id === streamMsg.id)?.content || '';

      if (response && response.content) {
        // Streaming already filled the message — ensure final content is set
        chatStore.updateMessage(streamMsg.id, { content: response.content });
      } else if (!finalContent.trim()) {
        chatStore.updateMessage(streamMsg.id, {
          content: 'I arrived on the page. Let me know if you need help finding a specific setting.',
        });
      }
    } catch (error: any) {
      if (error.name !== 'AbortError') {
        chatStore.addMessage('assistant', `Error: ${error.message || 'Unknown'}`);
      }
    } finally {
      chatStore.loading = false;
      this.abortController = null;
    }
  }

  private async agentLoop(messages: any[], iteration: number): Promise<void> {
    const chatStore = useChatStore();
    if (iteration >= 5) {
      chatStore.addMessage('assistant', 'I completed several steps. Let me know if you need more help!');
      return;
    }

    // Create an empty assistant message that will be filled in by streaming tokens.
    // If streaming is not supported or fails, it will be updated with the full response at once.
    const streamMsg = chatStore.addMessage('assistant', '');

    // Call AI with streaming — tokens update the message in real-time
    const response = await this.callAI(messages, true, (token: string) => {
      chatStore.updateMessage(streamMsg.id, {
        content: (chatStore.messages.find((m) => m.id === streamMsg.id)?.content || '') + token,
      });
    });

    // Edge case: empty assistant message left if callAI returned null or empty streaming failed
    const currentContent = chatStore.messages.find((m) => m.id === streamMsg.id)?.content || '';

    if (!response) {
      if (!currentContent.trim()) {
        chatStore.updateMessage(streamMsg.id, { content: 'Sorry, I had trouble processing that.' });
      }
      return;
    }

    // Persist the final content + tool_calls
    chatStore.updateMessage(streamMsg.id, {
      content: response.content || currentContent,
      ...(response.tool_calls && response.tool_calls.length > 0
        ? { toolCalls: response.tool_calls as any }
        : {}),
    });

    // No tool calls = final answer
    if (!response.tool_calls || response.tool_calls.length === 0) return;

    // CRITICAL: Add assistant message with tool_calls to the messages array
    // BEFORE pushing tool results. The AI requires tool messages to follow
    // an assistant message with matching tool_calls.
    messages.push({
      role: 'assistant',
      content: response.content || '',
      tool_calls: response.tool_calls,
    });

    // Execute tools
    for (const tc of response.tool_calls) {
      const tool = getToolByName(tc.function.name);
      const statusMsg = chatStore.addMessage('assistant',
        TOOL_STATUS[tc.function.name] || `⚙️ Running ${tc.function.name}...`);

      if (!tool) {
        const errorResult = JSON.stringify({ success: false, message: 'Unknown tool' });
        messages.push({ role: 'tool', tool_call_id: tc.id, content: errorResult });
        // Also persist tool result to chatStore for future message context
        chatStore.addMessage('tool', errorResult, { toolCallId: tc.id });
        chatStore.updateMessage(statusMsg.id, { content: `❌ Unknown tool` });
        continue;
      }

      let args: Record<string, any> = {};
      try { args = JSON.parse(tc.function.arguments || '{}'); } catch {}

      const result = await tool.execute(args);
      const resultJson = JSON.stringify(result);
      messages.push({ role: 'tool', tool_call_id: tc.id, content: resultJson });

      // ALSO persist tool result to chatStore so the AI remembers it across messages
      chatStore.addMessage('tool', resultJson, { toolCallId: tc.id });

      if (result.success) {
        chatStore.updateMessage(statusMsg.id, { content: `✅ ${tool.name.replace(/_/g, ' ')} — done` });
      } else {
        chatStore.updateMessage(statusMsg.id, { content: `⚠️ ${tool.name.replace(/_/g, ' ')} — ${result.message || 'done'}` });
      }

      // Handle navigation
      if (tool.name === 'navigate_user' && result.success && result.data?.url) {
        chatStore.addMessage('assistant', '📍 Taking you there...', {
          navigationCard: {
            title: result.data.reason || 'Settings Page',
            path: result.data.url,
            url: result.data.url,
          },
        });
        chatStore.loading = false;
        return;
      }
    }

    // Continue the loop with tool results
    await this.agentLoop(messages, iteration + 1);
  }

  cancel(): void {
    this.abortController?.abort();
    this.abortController = null;
  }

  /**
   * Build messages array for the AI.
   * Includes system prompt, WordPress context, and conversation history.
   */
  private buildMessages(userInput: string, extraSystem?: string): any[] {
    const chatStore = useChatStore();
    const contextStore = useContextStore();
    const navStore = useNavigationStore();

    let systemContent = SYSTEM_PROMPT;
    const ctx = contextStore.getContextSummary();
    if (ctx) systemContent += `\n\nCURRENT WORDPRESS CONTEXT:\n${ctx}`;

    // Add browser-scanned page context so the AI knows what the user sees
    // (headings, buttons, form fields, tabs, tables — everything visible in the DOM)
    try {
      const pageCtx = getFullPageContext();
      if (pageCtx.contextText) {
        systemContent += `\n\n${pageCtx.contextText}`;
      }
    } catch {
      // Page scan failed — proceed without it
    }

    if (navStore.currentTask) systemContent += `\n\nACTIVE TASK: ${navStore.currentTask} (Step ${navStore.step})`;
    if (extraSystem) systemContent += `\n\n${extraSystem}`;

    const messages: any[] = [{ role: 'system', content: systemContent }];

    // Add recent history from chatStore
    // Now properly includes tool_calls and tool results so the AI remembers
    // what tools it used and what they returned in previous turns
    for (const msg of chatStore.messages.slice(-30)) {
      // Skip navigation cards (they're UI-only)
      if (msg.navigationCard) continue;

      // Skip UI-only status messages (they start with emojis)
      if (msg.role === 'assistant' && /^[🔍📋📍🧭✨🔎💭⚙️✅❌⚠️]/.test(msg.content)) continue;

      if (msg.role === 'tool') {
        // Include tool results with tool_call_id so the AI can match them to tool_calls
        messages.push({
          role: 'tool',
          tool_call_id: msg.toolCallId || '',
          content: msg.content,
        });
      } else if (msg.role === 'assistant') {
        const aiMsg: any = { role: 'assistant', content: msg.content };
        // CRITICAL: Include tool_calls so the AI sees the connection between
        // its function calls and the tool results that follow
        if (msg.toolCalls && msg.toolCalls.length > 0) {
          aiMsg.tool_calls = msg.toolCalls;
        }
        messages.push(aiMsg);
      } else if (msg.role === 'user') {
        messages.push({ role: 'user', content: msg.content });
      }
      // system messages are not included in history (only the fresh system prompt)
    }

    return messages;
  }

  /**
   * Call the AI API directly from the frontend with SSE streaming.
   * Uses OpenAI-compatible chat completions format.
   * Streams tokens in real-time via the onToken callback for a responsive UI.
   *
   * This is the core LangChain pattern: frontend → AI → tools → WP REST API.
   *
   * @param messages  The messages array to send
   * @param withTools Whether to include tool definitions (default: true). Set false for text-only responses.
   * @param onToken   Optional callback invoked with each content token as it arrives.
   */
  private async callAI(
    messages: any[],
    withTools: boolean = true,
    onToken?: (token: string) => void,
  ): Promise<AIResponse | null> {
    const config = window.asdevsAiAssistant;
    const endpoint = config?.aiEndpoint || 'https://api.openai.com/v1/chat/completions';
    const apiKey = config?.apiKey || '';
    const model = config?.aiModel || 'gpt-5.4-mini';

    if (!apiKey) throw new Error('API key not configured');

    const body: any = {
      model,
      messages,
      temperature: 0.7,
      max_tokens: 1000,
      stream: true,
    };

    // Only include tools if requested
    if (withTools) {
      const toolDefs = availableTools.map((t) => t.toFunctionDefinition());
      if (toolDefs.length > 0) {
        body.tools = toolDefs;
        body.tool_choice = 'auto';
      }
    }

    const resp = await fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${apiKey}`,
      },
      body: JSON.stringify(body),
      signal: this.abortController?.signal,
    });

    if (!resp.ok) {
      const errText = await resp.text();
      throw new Error(`AI API error (${resp.status}): ${errText}`);
    }

    // If no onToken callback, fall back to non-streaming behaviour
    // (reads the full body as JSON — used when caller doesn't need streaming)
    if (!onToken) {
      const data = await resp.json();
      const choice = data.choices?.[0];
      if (!choice) return null;
      return {
        content: choice.message?.content || null,
        tool_calls: choice.message?.tool_calls || undefined,
      };
    }

    // --- SSE Streaming path ---
    const reader = resp.body?.getReader();
    if (!reader) {
      // Fallback: read as JSON if streaming body is unavailable
      const data = await resp.json();
      const choice = data.choices?.[0];
      if (!choice) return null;
      const content = choice.message?.content || null;
      if (content) onToken(content);
      return {
        content,
        tool_calls: choice.message?.tool_calls || undefined,
      };
    }

    const decoder = new TextDecoder();
    let fullContent = '';
    const toolCallsAcc: Map<number, { id: string; name: string; arguments: string }> = new Map();
    let buffer = '';

    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      // Keep the last potentially incomplete line in the buffer
      buffer = lines.pop() || '';

      for (const line of lines) {
        const trimmed = line.trim();
        if (!trimmed || !trimmed.startsWith('data: ')) continue;

        const jsonStr = trimmed.slice(6);
        if (jsonStr === '[DONE]') continue;

        let chunk: any;
        try {
          chunk = JSON.parse(jsonStr);
        } catch {
          continue; // Skip malformed chunks
        }

        const delta = chunk.choices?.[0]?.delta;
        if (!delta) continue;

        // --- Content token ---
        if (delta.content) {
          fullContent += delta.content;
          onToken(delta.content);
        }

        // --- Tool call deltas ---
        if (delta.tool_calls) {
          for (const tc of delta.tool_calls) {
            const idx = tc.index ?? 0;
            let entry = toolCallsAcc.get(idx);
            if (!entry) {
              entry = { id: '', name: '', arguments: '' };
              toolCallsAcc.set(idx, entry);
            }

            if (tc.id) entry.id = tc.id;
            if (tc.function?.name) entry.name = tc.function.name;
            if (tc.function?.arguments) entry.arguments += tc.function.arguments;
          }
        }
      }
    }

    // Build the final AIResponse
    const toolCalls: AIToolCall[] = [];
    for (const [, entry] of toolCallsAcc) {
      toolCalls.push({
        id: entry.id,
        type: 'function',
        function: { name: entry.name, arguments: entry.arguments },
      });
    }

    return {
      content: fullContent || null,
      tool_calls: toolCalls.length > 0 ? toolCalls : undefined,
    };
  }
}

export const agent = new AIAgent();
