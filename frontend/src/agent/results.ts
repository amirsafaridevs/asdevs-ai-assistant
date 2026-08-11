import type { Outcome } from '../types';

/** How much of a tool result the model is given back. */
export const MAX_RESULT_CHARS = 6000;

/** One tool answer, small enough to read and short enough to afford. */
export function toolText(payload: unknown): string {
  const content = typeof payload === 'string' ? payload : JSON.stringify(payload);

  return content.length > MAX_RESULT_CHARS
    ? `${content.slice(0, MAX_RESULT_CHARS)}\n…(truncated)`
    : content;
}

/** Keep the model's view of a result small and relevant — but never strip display URLs. */
export function compact(outcome: Outcome): Record<string, unknown> {
  return {
    status: outcome.status,
    message: outcome.message,
    kind: outcome.kind,
    total: outcome.total ?? null,
    data: shrinkPayload(outcome.data),
    assessment: outcome.assessment,
  };
}

function shrinkPayload(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.slice(0, 20).map(shrinkPayload);
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const source = value as Record<string, unknown>;
  const isMedia =
    typeof source.source_url === 'string' ||
    source.media_type !== undefined ||
    source.mime_type !== undefined ||
    (typeof source.type === 'string' && source.type === 'attachment');

  const keep = isMedia
    ? [
        'id',
        'title',
        'alt_text',
        'caption',
        'description',
        'slug',
        'status',
        'date',
        'link',
        'source_url',
        'mime_type',
        'media_type',
        'media_details',
      ]
    : ['id', 'title', 'name', 'slug', 'status', 'date', 'link', 'email', 'roles', 'count', 'total', 'excerpt'];

  const kept: Record<string, unknown> = {};

  for (const key of keep) {
    if (!(key in source)) {
      continue;
    }

    const field = source[key];

    if (key === 'media_details') {
      kept[key] = shrinkMediaDetails(field);
      continue;
    }

    kept[key] = unwrapRendered(field);
  }

  return Object.keys(kept).length > 0 ? kept : source;
}

function unwrapRendered(field: unknown): unknown {
  if (field && typeof field === 'object' && 'rendered' in (field as Record<string, unknown>)) {
    return (field as { rendered: unknown }).rendered;
  }

  return field;
}

/** Keep width/height and usable image URLs only. */
function shrinkMediaDetails(field: unknown): unknown {
  if (!field || typeof field !== 'object') {
    return field;
  }

  const details = field as Record<string, unknown>;
  const out: Record<string, unknown> = {};

  for (const key of ['width', 'height', 'file'] as const) {
    if (key in details) {
      out[key] = details[key];
    }
  }

  const sizes = details.sizes;

  if (sizes && typeof sizes === 'object') {
    const shrunk: Record<string, unknown> = {};

    for (const [name, size] of Object.entries(sizes as Record<string, unknown>)) {
      if (!size || typeof size !== 'object') {
        continue;
      }

      const row = size as Record<string, unknown>;

      if (typeof row.source_url === 'string') {
        shrunk[name] = {
          source_url: row.source_url,
          width: row.width,
          height: row.height,
        };
      }
    }

    if (Object.keys(shrunk).length > 0) {
      out.sizes = shrunk;
    }
  }

  return out;
}
