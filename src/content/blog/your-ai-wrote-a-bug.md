---
title: "Your AI wrote a bug"
description: "52 bug-fix loops in 6 months, and why the '10x developer' narrative is missing the point"
date: 2026-04-17
tags:
  - tools
  - data
draft: false
---

I'd been using Claude Code for about a week when I asked it to scaffold a Laravel authentication module. It generated the routes, the controllers, the middleware, the views. Clean code. Well-structured. Proper separation of concerns. I skimmed it, nodded, ran the tests.

Green across the board. I pushed to staging, opened the browser, typed in credentials, and watched it redirect me to a 500 error. The middleware was checking for a session token that the login controller never set. The tests passed because the test helper bypassed middleware entirely.

I fixed it in ten minutes. But the thing that stuck with me wasn't the bug. It was how convinced I'd been, for about thirty seconds, that the AI had just written a flawless auth system. The code *looked* right. The tests *said* it was right. The only thing that caught the problem was actually trying to log in.

I want to tell you this was a one-time thing, that I learned my lesson and it never happened again.

It happened 51 more times.

## The story we tell ourselves

There's a narrative around AI coding assistants that goes something like this: developer installs AI tool, developer becomes dramatically more productive, developer ships features at speed no human could match, developer is now a 10x engineer. The AI companies tell this story. The tech press tells this story. The LinkedIn thought leaders *love* this story.

And it's true, in the same way that saying "a car goes 80 miles per hour" is true. It omits the traffic, the fuel stops, the wrong turns, the flat tire in New Mexico, and the hour you spent in a rest stop parking lot arguing with Google Maps. The destination speed is real. The journey speed is different.

I've been [tracking my Claude Code usage](/blog/six-months-talking-to-a-machine/) for six months now. Buried in those almost four thousand prompts are 52 distinct bug-fix loops. Moments where Claude Code generated something, I accepted it, and then reality intervened. That's roughly one bug for every 75 prompts, or about two per week. Some were trivial. Some cost me hours.

Let me stop telling you the productivity story. Let me tell you the bug story instead.

## What actually happens

A bug-fix loop, as I've come to think of it, follows a depressingly predictable pattern.

I ask Claude Code to build something. It builds it. The output looks reasonable. If there are tests, they pass. I move on to the next thing. Then something breaks, or I notice an edge case, or a real user hits a path the tests don't cover. I go back. I read the code Claude Code wrote, often for the first time with real attention. I find the problem. I explain it. Claude Code fixes it. Sometimes it introduces a new problem in the fix. Sometimes the fix is perfect. Sometimes I fix it myself because explaining the issue would take longer than changing two lines.

The 52 loops break down roughly like this: about 20 were genuine logic errors where the generated code did the wrong thing. About 15 were integration failures where the code worked in isolation but broke when connected to real systems, databases, APIs, authentication layers. About 10 were subtle specification mismatches where Claude Code built what I asked for instead of what I meant. The rest were edge cases that nobody would catch without production traffic.

None of these are surprising. They're the same bugs humans write. The difference is in the *feeling*. When I write a bug myself, I have a mental model of the code that includes the bug. I know what I was thinking when I wrote it, so I can reason about where the thinking went wrong. When Claude Code writes a bug, I don't have that mental model. I have to build it retroactively, reading code I didn't write to understand decisions I didn't make. That's closer to debugging a colleague's code than debugging your own, and it requires a different set of skills.

## The gap in the mental model

This is the part I keep circling back to.

When you write code by hand, the bugs are yours. You understand them because you built the context they live in. When you direct an AI to write code, the bugs are still yours (you shipped them), but you lack the construction context. You're responsible for code you didn't think through, and the debugging requires you to think through it after the fact. You're held to account for decisions you never made.

In practice, this means AI-assisted development isn't faster in the way people think it is. The generation step is faster, yes. The code appears in seconds. But the verification step expands to fill the time the generation step saved. I don't spend less total time per feature. I spend the time differently: less on writing, more on reading. Less on construction, more on inspection. Less on "does this compile" and more on "does this actually do what I need it to do."

My dozens of scope-expansion follow-ups tell the same story from a different angle. These are prompts where I said "also add X" or "can you also handle Y" after Claude Code delivered something that technically met the spec but missed the fuller picture. The AI did what I asked. I hadn't asked for enough. The cost of underspecification is real, and it compounds, because each "also add" builds on a codebase that was designed without the addition in mind.

## What the numbers actually show

If I'm honest about what six months of data tells me, it's this: AI coding assistants make the easy parts trivially easy and leave the hard parts exactly as hard as they were.

Scaffolding a project? Trivial. Generating boilerplate? Instant. Writing tests for well-defined functions? Handled. Converting a Python script to TypeScript? Fifteen minutes, and honestly pretty good. These are the tasks that take time but don't take *thought*, and the AI eats them for breakfast.

Designing an architecture that will hold up under real usage? Exactly as hard. Catching the integration bug that only manifests when two subsystems interact under load? Harder, actually, because you have less intuition about code you didn't write. Knowing when the generated code is subtly wrong in a way the tests won't catch? That's a skill, and it's a new skill, and nobody's teaching it.

The 10x claim measures the easy parts. "Look how fast I generated this CRUD API!" Yes, you did. The CRUD API was never the bottleneck. The bottleneck was understanding the domain well enough to know which CRUD operations your users actually need, and what happens when two of them run concurrently, and where the validation belongs, and which error messages are helpful versus which ones will [generate support tickets](/blog/your-metrics-are-bullshit/).

Those questions didn't get easier. They got more important, because the AI removed all the other reasons to slow down and think.

## The skill nobody's measuring

I don't have a clean name for the competency that actually matters here. It's the ability to read AI-generated code critically, to spot the structural weaknesses, the false-positive test suites, the integration gaps, the specification mismatches. Before AI assistants, code review was something you did to other people's work. Now it's something you do to your own, constantly, because the "other person" generated your implementation and you need to verify it.

The developers who are genuinely more productive with AI tools aren't the ones who accept the output. They're the ones who've gotten fast at *reading* the output, knowing where the seams are, and testing the seams before they ship.

I'm still learning this. Fifty-two bugs in six months means I'm catching about two per week *after* they've gotten past my initial review. The ones I catch before they land, I don't count. I'm getting better at the before-they-land part, but better is relative, and the bugs are real.

## What I'd tell someone starting out

Don't trust green tests. Run the thing. Click the buttons. Hit the endpoints. The tests might be testing the wrong assertion, or bypassing the layer where the bug lives, or passing because the test helper sets up state that production never will.

Don't skip reading the code. The thirty seconds you save by not reading the implementation will cost you two hours when the bug surfaces in staging. AI-generated code is not self-documenting. It's plausible-looking, which is more dangerous.

Expect the iteration. Budget for it. Your first prompt will get you 80% of the way there, and the remaining 20% will take 80% of the time. This is just the Pareto principle wearing a new hat, and it applies to AI-assisted development the same way it applies to everything else.

The AI didn't make you a 10x developer. It made you a developer who generates code at 10x speed and verifies it at 1x speed. The bottleneck moved to you, to your ability to read what was generated and know whether it's right. If that sounds less exciting than the LinkedIn version, it's because it is. It's also true, and true is more useful than exciting when you're staring at a 500 error in staging wondering why the test suite is green.
