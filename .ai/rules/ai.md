---
paths:
  - 'app/Ai/**'
---

# Ai

## Native publishing agents and human approval
Publishing generation uses Laravel AI SDK role agents, the native OpenRouter provider, durable conversations, and native approvable tools; do not recreate a parallel chat/tool runtime. AskAuthor answers resume via SDK Decisions and an authorized queued application action. Native tool decisions never grant angle/plan/release approval or publication authority. Keep frozen input/revision checks, evidence grounding and owner authorization around every paid continuation. The application does not reserve, quote or reconcile spend: OpenRouter key/workspace limits own spend enforcement, and missing billing or usage metadata never blocks otherwise valid output.

## Agent request limits are runaway guards, not latency or cost targets
Publishing agent calls run in queued jobs, and the owner prefers quality over latency. `publishing_agents.http_timeout` and each role's `#[MaxTokens]` only guard against hung or runaway requests. Keep the timeout below the production worker `--timeout` / retry window with room for research fetches, never tie it to the unused 60s Horizon supervisor, and size MaxTokens so high-effort reasoning plus the full structured answer fits (`finish_reason: length` truncates the JSON). Do not switch role models or lower effort to fit a limit; a timeout pauses the activity as uncertain after the provider may already have billed it.
