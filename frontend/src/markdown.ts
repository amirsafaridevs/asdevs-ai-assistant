import DOMPurify from 'dompurify';
import { marked } from 'marked';

marked.setOptions({
  gfm: true,
  breaks: true,
});

export interface ChoiceQuestion {
  id: string;
  prompt: string;
  options: string[];
}

export interface ProcessedMarkdown {
  html: string;
  choices: ChoiceQuestion[];
  /** True when the source had choice markup but little/no remaining display text. */
  choiceOnly: boolean;
}

const ATTR_RE = /([a-zA-Z_][\w-]*)\s*=\s*"([^"]*)"/g;

/** Parse key="value" attributes from a tag opener. */
function parseAttrs(source: string): Record<string, string> {
  const attrs: Record<string, string> = {};
  ATTR_RE.lastIndex = 0;

  let match: RegExpExecArray | null;

  while ((match = ATTR_RE.exec(source)) !== null) {
    attrs[match[1]] = match[2];
  }

  return attrs;
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function sanitizeUrl(url: string): string {
  const trimmed = url.trim();

  if (/^(https?:|mailto:|\/|#)/i.test(trimmed)) {
    return trimmed;
  }

  return '#';
}

function renderLinkHtml(url: string, title: string): string {
  const href = escapeHtml(sanitizeUrl(url));
  const label = escapeHtml(title || url);

  return `<p><a class="asdevs-ai-md-link" href="${href}" target="_blank" rel="noopener noreferrer">${label}</a></p>\n`;
}

function renderImageHtml(src: string, alt: string): string {
  const safeSrc = escapeHtml(sanitizeUrl(src));
  const safeAlt = escapeHtml(alt || '');

  return `<p><img class="asdevs-ai-md-image" src="${safeSrc}" alt="${safeAlt}" loading="lazy" /></p>\n`;
}

function renderCalloutHtml(title: string | undefined, body: string): string {
  const titleHtml = title
    ? `<strong class="asdevs-ai-callout__title">${escapeHtml(title)}</strong>`
    : '';
  const text = escapeHtml(body).replace(/\n/g, '<br />');

  return `<aside class="asdevs-ai-callout">${titleHtml}<div class="asdevs-ai-callout__body">${text}</div></aside>\n`;
}

/** Split choice options from child tags, list lines, or inline "- " segments. */
function parseChoiceOptions(body: string): string[] {
  const fromTags: string[] = [];
  const optionTagRe = /<asdevs-option\b[^>]*>([\s\S]*?)<\/asdevs-option\s*>/gi;
  let tagMatch: RegExpExecArray | null;

  while ((tagMatch = optionTagRe.exec(body)) !== null) {
    const text = tagMatch[1].replace(/\s+/g, ' ').trim();

    if (text) {
      fromTags.push(text);
    }
  }

  if (fromTags.length > 0) {
    return fromTags;
  }

  const cleaned = body
    .replace(/<\/?asdevs-option\b[^>]*>/gi, ' ')
    .replace(/\r\n?/g, '\n')
    .trim();

  if (!cleaned) {
    return [];
  }

  const lines = cleaned.split('\n').map((line) => line.trim()).filter(Boolean);
  const listLines = lines
    .map((line) => line.replace(/^\s*(?:[-*+]|\d+[.)])\s+/, '').trim())
    .filter(Boolean);

  // Prefer real list lines when most lines look like bullets.
  if (lines.length > 1 && listLines.length === lines.length) {
    return listLines;
  }

  // Inline options smashed onto one line: `- A - B - C`
  const inline = cleaned
    .split(/\s+-\s+/)
    .map((part, index) => (index === 0 ? part.replace(/^\s*-\s+/, '') : part).trim())
    .filter(Boolean);

  if (inline.length > 1) {
    return inline;
  }

  return listLines.length > 0 ? listLines : cleaned ? [cleaned] : [];
}

function pushChoice(
  choices: ChoiceQuestion[],
  attrs: Record<string, string>,
  body: string
): void {
  const options = parseChoiceOptions(body);

  if (options.length === 0) {
    return;
  }

  choices.push({
    id: attrs.id || `q${choices.length + 1}`,
    prompt: attrs.prompt || 'Choose one',
    options,
  });
}

/**
 * Expand primary HTML-like custom tags into markdown-safe HTML / extracted choices.
 * Incomplete open tags (still streaming) hide the remainder.
 */
function expandHtmlLikeTags(source: string): { markdown: string; choices: ChoiceQuestion[] } {
  const choices: ChoiceQuestion[] = [];
  let result = '';
  let index = 0;

  const openRe = /<(asdevs-(?:choice|link|image|callout))\b([^>]*?)(\/?)>/gi;

  while (index < source.length) {
    openRe.lastIndex = index;
    const open = openRe.exec(source);

    if (!open || open.index === undefined) {
      result += source.slice(index);
      break;
    }

    result += source.slice(index, open.index);

    const tag = open[1].toLowerCase();
    const attrSource = open[2] || '';
    const selfClosing = open[3] === '/' || /\/\s*$/.test(attrSource);
    const attrs = parseAttrs(attrSource);
    const afterOpen = open.index + open[0].length;

    if (tag === 'asdevs-link' && selfClosing) {
      const url = attrs.url || attrs.href || '';

      if (url) {
        result += renderLinkHtml(url, attrs.title || url);
      }

      index = afterOpen;
      continue;
    }

    if (tag === 'asdevs-image' && selfClosing) {
      if (attrs.src) {
        result += renderImageHtml(attrs.src, attrs.alt || '');
      }

      index = afterOpen;
      continue;
    }

    const closeTag = `</${tag}>`;
    const closeAt = source.toLowerCase().indexOf(closeTag, afterOpen);

    if (closeAt === -1) {
      // Incomplete while streaming — hide the rest rather than flash raw tags.
      index = source.length;
      break;
    }

    const body = source.slice(afterOpen, closeAt);
    index = closeAt + closeTag.length;

    if (tag === 'asdevs-choice') {
      pushChoice(choices, attrs, body);
      continue;
    }

    if (tag === 'asdevs-callout') {
      result += renderCalloutHtml(attrs.title, body.trim());
      continue;
    }

    if (tag === 'asdevs-link') {
      const url = attrs.url || attrs.href || '';

      if (url) {
        result += renderLinkHtml(url, attrs.title || body.trim() || url);
      }

      continue;
    }

    if (tag === 'asdevs-image' && attrs.src) {
      result += renderImageHtml(attrs.src, attrs.alt || body.trim());
    }
  }

  return { markdown: result, choices };
}

/**
 * Legacy ::: fence syntax — kept for one release cycle.
 * Resilient to missing newlines and smashed-together blocks.
 */
function expandLegacyFences(source: string): { markdown: string; choices: ChoiceQuestion[] } {
  const choices: ChoiceQuestion[] = [];
  let result = '';
  let index = 0;

  while (index < source.length) {
    const open = source.indexOf(':::', index);

    if (open === -1) {
      result += source.slice(index);
      break;
    }

    result += source.slice(index, open);

    const afterOpen = open + 3;
    const rest = source.slice(afterOpen);
    const typeMatch = rest.match(/^\s*(choice|callout|link|image)\b/i);

    if (!typeMatch) {
      result += ':::';
      index = afterOpen;
      continue;
    }

    const type = typeMatch[1].toLowerCase();
    const afterType = afterOpen + typeMatch[0].length;
    const remainder = source.slice(afterType);
    const close = remainder.indexOf(':::');

    if (close === -1) {
      // Incomplete — hide rest while streaming.
      index = source.length;
      break;
    }

    const segment = remainder.slice(0, close);
    const nl = segment.indexOf('\n');
    let attrPart: string;
    let body: string;

    if (nl === -1) {
      // Everything on one line: attrs then optional " - a - b" body.
      const attrEnd = findAttrsEnd(segment);
      attrPart = segment.slice(0, attrEnd);
      body = segment.slice(attrEnd);
    } else {
      attrPart = segment.slice(0, nl);
      body = segment.slice(nl + 1);
    }

    const attrs = parseAttrs(attrPart);
    index = afterType + close + 3;

    if (type === 'choice') {
      pushChoice(choices, attrs, body);
      continue;
    }

    if (type === 'callout') {
      result += renderCalloutHtml(attrs.title, body.replace(/\n+$/, '').trim());
      continue;
    }

    if (type === 'link' && attrs.url) {
      result += renderLinkHtml(attrs.url, attrs.title || body.trim() || attrs.url);
      continue;
    }

    if (type === 'image' && attrs.src) {
      result += renderImageHtml(attrs.src, attrs.alt || body.trim());
    }
  }

  return { markdown: result, choices };
}

/** End of consecutive key="value" attributes (allows trailing spaces). */
function findAttrsEnd(segment: string): number {
  ATTR_RE.lastIndex = 0;
  let lastEnd = 0;
  let match: RegExpExecArray | null;
  const leading = segment.match(/^\s*/)?.[0].length ?? 0;
  let cursor = leading;

  while (cursor < segment.length) {
    ATTR_RE.lastIndex = cursor;
    match = ATTR_RE.exec(segment);

    if (!match || match.index !== cursor) {
      break;
    }

    lastEnd = match.index + match[0].length;
    cursor = lastEnd;
    const ws = segment.slice(cursor).match(/^\s*/)?.[0].length ?? 0;
    cursor += ws;
  }

  return lastEnd > 0 ? cursor : leading;
}

/** Last-resort scrub so users never see raw custom markup. */
function scrubLeftoverCustomMarkup(source: string): string {
  return source
    .replace(/<\/?asdevs-(?:choice|option|link|image|callout)\b[^>]*>/gi, '')
    .replace(/:::(?:choice|callout|link|image)\b[\s\S]*?(?:::|$)/gi, '')
    .replace(/:::/g, '');
}

/**
 * Expand custom blocks into HTML / placeholders before markdown runs.
 * Choice blocks are removed from display markup and returned separately.
 */
export function expandCustomBlocks(source: string): { markdown: string; choices: ChoiceQuestion[] } {
  const htmlPass = expandHtmlLikeTags(source);
  const legacyPass = expandLegacyFences(htmlPass.markdown);
  const markdown = scrubLeftoverCustomMarkup(legacyPass.markdown);

  return {
    markdown,
    choices: [...htmlPass.choices, ...legacyPass.choices],
  };
}

export function extractChoices(source: string): ChoiceQuestion[] {
  return expandCustomBlocks(source).choices;
}

function sanitizeHtml(raw: string): string {
  return DOMPurify.sanitize(raw, {
    USE_PROFILES: { html: true },
    ADD_TAGS: ['aside'],
    ADD_ATTR: ['target', 'rel', 'loading', 'class'],
  });
}

/** Turn model text into safe HTML the bubble can paint. */
export function renderMarkdown(source: string): string {
  const { markdown } = expandCustomBlocks(source);
  const trimmed = markdown.trim();

  if (!trimmed) {
    return '';
  }

  const raw = marked.parse(trimmed, { async: false }) as string;

  return sanitizeHtml(raw);
}

/** Render + extract choices in one pass (preferred for assistant bubbles). */
export function processAssistantMarkdown(source: string): ProcessedMarkdown {
  const { markdown, choices } = expandCustomBlocks(source);
  const trimmed = markdown.trim();
  const raw = trimmed ? (marked.parse(trimmed, { async: false }) as string) : '';
  const html = raw ? sanitizeHtml(raw) : '';

  return {
    html,
    choices,
    choiceOnly: choices.length > 0 && html.replace(/<[^>]+>/g, '').trim() === '',
  };
}
