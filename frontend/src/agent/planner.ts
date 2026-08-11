import { Agent, run } from '@openai/agents';
import { z } from 'zod';

/**
 * A short planning pass before the assistant starts working.
 *
 * The hard part of doing a job on someone's site is not calling the API — it is
 * deciding what the job actually is before touching anything. So a separate
 * agent, with no tools and nothing to execute, reads the request first and
 * writes down what it understands, what it would do, and what it is unsure
 * about. The working agent then starts from that instead of improvising while
 * also holding a REST schema in its head.
 *
 * Planning is advisory. If it fails, or the model returns something unusable,
 * the assistant carries on exactly as it would have without it.
 */

const Plan = z.object({
  understanding: z
    .string()
    .describe(
      "One sentence, in the person's own language, saying what they are actually asking for. Not a restatement of their words — what they want to be true when this is done."
    ),
  read_only: z
    .boolean()
    .describe('True when answering needs only reading. False when anything on the site would change.'),
  steps: z
    .array(
      z.object({
        intent: z
          .string()
          .describe('One concrete step, in plain words. "Find the draft posts from last month", not "call the API".'),
        why: z.string().describe('Why this step is needed for what they asked. One short clause.'),
      })
    )
    .describe('Between one and six steps, in the order they should happen. Fewer is better.'),
  unclear: z
    .array(z.string())
    .describe(
      'Anything genuinely ambiguous that would change what gets done. Empty when the request is clear enough to start. Do not invent doubts to seem careful.'
    ),
  caution: z
    .string()
    .describe(
      'The one thing most likely to go wrong or be irreversible here, or an empty string when nothing stands out.'
    ),
});

export type Plan = z.infer<typeof Plan>;

const INSTRUCTIONS = `You plan work on a WordPress site before another assistant carries it out. You never do the work yourself and you have no tools.

Read the request and the site briefing, then write the plan.

Rules:
- Plan only what was asked. Do not add steps the person did not ask for, however sensible they seem.
- Steps describe intent, not mechanics. The assistant doing the work knows the APIs; it needs to know the goal.
- Keep it to the fewest steps that finish the job. One step is a fine plan.
- Put something in "unclear" only when a reasonable person could read the request two ways and the two readings lead to different work. An empty list is the normal case.
- "caution" is for the one real risk: something irreversible, something affecting many items, something a person would want to be asked about. Empty string when there is none.
- Never guess at facts about the site. If the plan depends on what is there, make finding out the first step.`;

/** Whether a request is worth planning, or small enough to just answer. */
export function worthPlanning(request: string, mode: string): boolean {
  if (mode === 'ask') {
    return false;
  }

  const trimmed = request.trim();

  // Greetings, one-word follow-ups, and "yes" do not need a plan.
  return trimmed.length >= 40 || trimmed.split(/\s+/).length >= 8;
}

/**
 * Plan one request. Returns null when planning did not produce anything usable.
 *
 * @param request  What the person asked for.
 * @param briefing The site instructions the working agent will also receive.
 * @param model    Model id, or 'auto' to let the connector choose.
 * @param signal   Abort signal for the surrounding turn.
 */
export async function planFor(
  request: string,
  briefing: string,
  model: string,
  signal: AbortSignal
): Promise<Plan | null> {
  const planner = new Agent({
    name: 'Planner',
    instructions: `${INSTRUCTIONS}\n\n--- Site briefing ---\n${briefing}`,
    model: model === '' ? 'auto' : model,
    outputType: Plan,
    modelSettings: { temperature: 0.2 },
  });

  try {
    const result = await run(planner, request, { signal, maxTurns: 1 });
    const plan = result.finalOutput;

    return plan && plan.steps.length > 0 ? plan : null;
  } catch {
    // A plan is a help, not a requirement.
    return null;
  }
}

/** The plan as the working agent should read it: context, not orders. */
export function planAsContext(plan: Plan): string {
  const lines = [
    'A planning pass read this request before you. Treat it as a starting point, not as instructions — if the site turns out differently, follow what you find, not the plan.',
    '',
    `What they want: ${plan.understanding}`,
    plan.read_only ? 'This looks answerable by reading only.' : 'This will change something on the site.',
    '',
    'Suggested steps:',
    ...plan.steps.map((step, index) => `${index + 1}. ${step.intent} — ${step.why}`),
  ];

  if (plan.unclear.length > 0) {
    lines.push(
      '',
      'Possibly unclear (ask about these only if you cannot settle them by looking):',
      ...plan.unclear.map((item) => `- ${item}`)
    );
  }

  if (plan.caution.trim() !== '') {
    lines.push('', `Watch out for: ${plan.caution}`);
  }

  return lines.join('\n');
}
