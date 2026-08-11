import type { AgentInputItem } from '@openai/agents';
import type { Block, Message } from '../types';

/**
 * Translate between the stored conversation and what the SDK runs on.
 *
 * The saved shape (content blocks per message) stays exactly as it was: it is
 * what the server persists, what a reloaded page is rebuilt from, and what the
 * chat timeline is drawn from. The SDK works in flat protocol items instead, so
 * one conversion each way keeps both sides honest without changing storage.
 */

/**
 * Put back what the protocol has nowhere to carry.
 *
 * An attachment travels to the model as a bare URL, so its filename and type
 * do not survive the round trip. They still matter on screen — a chip reading
 * "report.pdf" is not the same as one reading "Attached file" — so they are
 * restored from the conversation the run started with.
 */
export function restoreAttachments(next: Message[], previous: Message[]): Message[] {
  const known = new Map<string, { mime_type: string; name?: string }>();

  for (const message of previous) {
    for (const block of message.content) {
      if (block.type === 'file' && block.url !== '') {
        known.set(block.url, { mime_type: block.mime_type, name: block.name });
      }
    }
  }

  if (known.size === 0) {
    return next;
  }

  for (const message of next) {
    for (const block of message.content) {
      if (block.type !== 'file') {
        continue;
      }

      const original = known.get(block.url);

      if (original) {
        block.mime_type = original.mime_type;
        block.name = original.name;
      }
    }
  }

  return next;
}

/** A tool answer can be plain text, one part, or several; the store keeps text. */
function outputText(output: unknown): string {
  if (typeof output === 'string') {
    return output;
  }

  if (Array.isArray(output)) {
    return output.map(outputText).join('');
  }

  if (output && typeof output === 'object') {
    const part = output as { type?: string; text?: string };

    return part.type === 'text' && typeof part.text === 'string' ? part.text : '';
  }

  return '';
}

/** Function-call ids must survive the round trip, or results lose their call. */
function callIdOf(id: string): string {
  return id !== '' ? id : `call_${Math.random().toString(36).slice(2, 10)}`;
}

/**
 * Stored messages → agent input items.
 */
export function toAgentInput(messages: Message[]): AgentInputItem[] {
  const items: AgentInputItem[] = [];

  for (const message of messages) {
    if (message.role === 'user') {
      const parts: Array<{ type: 'input_text'; text: string } | { type: 'input_image'; image: string }> = [];

      for (const block of message.content) {
        if (block.type === 'text' && block.text.trim() !== '') {
          parts.push({ type: 'input_text', text: block.text });
          continue;
        }

        if (block.type === 'file' && block.url !== '') {
          parts.push({ type: 'input_image', image: block.url });
          continue;
        }

        if (block.type === 'tool_result') {
          // A function response is its own item, not part of a user message.
          items.push({
            type: 'function_call_result',
            name: '',
            callId: callIdOf(block.tool_use_id),
            status: 'completed',
            output: { type: 'text', text: block.content },
          });
        }
      }

      if (parts.length > 0) {
        items.push({ type: 'message', role: 'user', content: parts });
      }

      continue;
    }

    for (const block of message.content) {
      if (block.type === 'thinking') {
        // Chat Completions carries the thought on rawContent; `content` stays
        // empty, which is what the SDK's own converter reads and writes.
        items.push({
          type: 'reasoning',
          content: [],
          rawContent: [{ type: 'reasoning_text', text: block.thinking }],
          providerData: block.signature !== '' ? { signature: block.signature } : undefined,
        });

        continue;
      }

      if (block.type === 'text' && block.text.trim() !== '') {
        items.push({
          type: 'message',
          role: 'assistant',
          status: 'completed',
          content: [{ type: 'output_text', text: block.text }],
        });

        continue;
      }

      if (block.type === 'tool_use') {
        items.push({
          type: 'function_call',
          callId: callIdOf(block.id),
          name: block.name,
          status: 'completed',
          arguments: JSON.stringify(block.input ?? {}),
        });
      }
    }
  }

  return items;
}

/**
 * Agent history → stored messages.
 *
 * Consecutive assistant-side items collapse into one message, the way the
 * timeline already expects to read a turn.
 */
export function fromAgentInput(items: AgentInputItem[]): Message[] {
  const messages: Message[] = [];

  const push = (role: Message['role'], block: Block): void => {
    const last = messages[messages.length - 1];

    if (last && last.role === role) {
      last.content.push(block);

      return;
    }

    messages.push({ role, content: [block] });
  };

  for (const item of items) {
    if (item.type === 'message' && item.role === 'user') {
      const content = item.content;

      if (typeof content === 'string') {
        push('user', { type: 'text', text: content });

        continue;
      }

      for (const part of content) {
        if (part.type === 'input_text') {
          push('user', { type: 'text', text: part.text });

          continue;
        }

        if (part.type === 'input_image' && typeof part.image === 'string') {
          push('user', { type: 'file', url: part.image, mime_type: '' });
        }
      }

      continue;
    }

    if (item.type === 'message' && item.role === 'assistant') {
      for (const part of item.content) {
        if (part.type === 'output_text') {
          push('assistant', { type: 'text', text: part.text });
        }
      }

      continue;
    }

    if (item.type === 'reasoning') {
      const raw = item.rawContent ?? [];
      const text = raw.length > 0 ? raw.map((part) => part.text).join('') : item.content.map((part) => part.text).join('');
      const signature = (item.providerData?.signature as string | undefined) ?? '';

      if (text !== '') {
        push('assistant', { type: 'thinking', thinking: text, signature });
      }

      continue;
    }

    if (item.type === 'function_call') {
      let input: Record<string, unknown> = {};

      try {
        const parsed: unknown = JSON.parse(item.arguments || '{}');
        input = parsed && typeof parsed === 'object' ? (parsed as Record<string, unknown>) : {};
      } catch {
        input = {};
      }

      push('assistant', { type: 'tool_use', id: item.callId, name: item.name, input });

      continue;
    }

    if (item.type === 'function_call_result') {
      const text = outputText(item.output);

      push('user', {
        type: 'tool_result',
        tool_use_id: item.callId,
        content: text,
        is_error: item.status === 'incomplete',
      });
    }
  }

  return messages;
}
