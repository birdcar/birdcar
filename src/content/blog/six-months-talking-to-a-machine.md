---
title: "Six months of talking to a machine"
description: "What 4,000 prompts taught me about the thing I was actually learning"
date: 2026-04-03
tags:
  - culture
  - tools
draft: false
---

I keep a file called `history.jsonl` on my laptop. I didn't put it there. Claude Code did, logging every prompt I've sent since October. The other day I counted the lines: 3,976. Six months, almost four thousand requests, across a dozen projects and at least five programming languages. That's roughly 23 prompts per day if you average it, though [averages lie](/writing/your-metrics-are-bullshit/), and I should know better than to trust them.

I wasn't looking for this file. I was looking for something else entirely, the way you find an old journal in a drawer when you're hunting for a screwdriver. But once I started reading, I couldn't stop. Not because the prompts were interesting on their own. Most of them are mundane. But the *shape* of them, the way they change from October to March, told a story I hadn't noticed while I was living it.

## The long-message era

My first prompts average almost 300 characters. That's roughly two solid sentences, sometimes three. They're polite, contextual, and careful. "Given the database schema located in './packages/api/db/data/schema.sql', generate a mermaidjs diagram explaining the database entity structure and all relationships." I'm specifying file paths. I'm explaining what I want and why. I'm framing the task before delegating it.

Reading them now, they sound like emails to a new contractor. Here's the work, here's where to find things, here's what I need back. There's a formality to it, a hedging, that I recognize from every first week at a new job. You over-explain because you haven't calibrated trust yet. You don't know what the other party knows, so you fill the gaps preemptively.

October and November were almost entirely exploration. "Tell me about the service in this directory." "Explain how it gets configured for an end user." I was mapping territory, using Claude Code the way I'd use a senior engineer who happened to have read every file in the codebase. The questions were genuine. The answers were useful. But I was doing the thing people do when they get a new tool: I was testing it, not trusting it.

## Something shifts in December

Thirty-three prompts in October. Six in November. Then almost 200 in December and nearly 900 in January. The volume change is dramatic, but the character of the prompts shifts in a way the numbers don't capture. My December prompts start telling Claude Code to *build* things. Not explain, not describe, not diagram. Build. "Create a plugin that does X." "Generate tests for Y." "Scaffold this project."

I don't remember making a conscious decision to change how I worked. That's what interests me about reading the history. The shift happened gradually, prompt by prompt, as I accumulated evidence that the tool could carry more weight. There wasn't a moment where I thought "okay, I trust this now." There was just a slow erosion of the hedging. I stopped specifying file paths for things it already knew about. I stopped explaining context it could infer. I started writing prompts that assumed a shared understanding we'd built over weeks.

By January I'm building a [plugin ecosystem](/writing/the-tools-that-build-the-tools/). Custom skills, custom commands, custom hooks. I'm not asking Claude Code to help me do my work. I'm asking it to help me build tools that change how I do my work. That's a different relationship entirely.

## Trust encodes as brevity

By March my average prompt is about 150 characters. Half the length of October. A third of my prompts are under 50 characters. "fix the tests." "use the commit skill." "check types." Some of them are just slash commands, five characters invoking a workflow I spent hours designing in January.

The popular narrative is that prompt engineering is a skill you learn, a set of techniques you apply, a craft you refine. And maybe it is, for people using ChatGPT for one-off questions. But in a sustained working relationship with a coding assistant, what actually happens is closer to what happens with any colleague: you develop shared context, and the shared context makes the communication shorter.

I started writing paragraph-length messages to a stranger. I ended sending five-word commands to a collaborator. The compression isn't laziness. It's trust.

## The work migrates

Something happened that I didn't expect, and I'm still working through what it means.

In October I was using Claude Code to understand code. In March I was using it to build tools that build tools. The ratio of "explain this to me" to "build this for me" inverted completely. But the skills I'm exercising as a developer haven't atrophied. If anything, they've shifted upward. I spend less time writing boilerplate and more time thinking about system design. Less time debugging syntax and more time debugging architecture.

The work doesn't disappear. It migrates. I used to spend an hour writing a test suite. Now I spend that hour deciding what the test suite needs to prove. I used to spend a morning scaffolding a project. Now I spend that morning designing the project's constraints.

I notice this most in the burst projects. I built a full-stack salary negotiation tool in two and a half days. A working fine-grained authorization demo with role hierarchies, resource policies, and a live permissions playground in two days. These aren't prototypes or proof-of-concepts. They're applications with real data models, real auth flows, real UI. The prompt counts (about 150 and 100, respectively) just show the scale of the conversation, not the thing that mattered. What mattered was that I was thinking at the architectural level the entire time, deciding what the app needed to *be* rather than wrestling with how to make it *work*. I'm not faster because Claude Code types for me. I'm faster because I can hold the whole system in my head and direct its construction without the cognitive interruption of implementation details.

## What I'm not sure about

I want to be honest about the parts I haven't figured out.

I worry about the exploration I'm not doing. When I wrote code by hand, I stumbled into things. I'd be implementing a feature and notice an edge case, or discover a library function I didn't know existed, or realize the data model was wrong in a way that only became visible when I tried to wire things up. Some of my best architectural insights came from the friction of implementation. When you delegate the friction, do you lose the insights?

I don't know. The history file is full of [bug-fix loops](/writing/your-ai-wrote-a-bug/), moments where Claude Code did the thing wrong and I had to dig in and course-correct. Those moments are still learning moments. But they're reactive, not exploratory. I'm finding bugs, not finding ideas.

There's also the language question. I've written meaningful code in Laravel, Next.js, Python, and Rust over these six months. That's a breadth I wouldn't have attempted without an AI assistant. But the depth is different. I can direct a Rust implementation. I can read one. Can I *think* in Rust? I'm not sure I can, and I'm not sure this tool is teaching me to. It's teaching me to think *about* Rust, which is useful but isn't the same thing.

## What the history file actually shows

Counting lines in a file is a productivity story, and on the surface this is one. Four thousand prompts, a dozen projects, measurable compression in communication overhead. The numbers are real.

But that's not what the history file actually shows. What it shows is a relationship forming. The gradual accumulation of shared context. The slow calibration of trust. The shift from formal requests to shorthand. The evolution from "help me understand this" to "help me build this" to "you know what to do." These are the same patterns you see in any productive working relationship. The medium is different. The dynamics are not.

I think about this when people ask me whether AI coding tools are worth adopting. The question assumes the value is in the output, the code produced, the tasks completed. And the output is real. But the more interesting thing is what happens to *you* over six months of sustained collaboration. How you think changes. What you spend your attention on changes. The boundary between your ideas and your implementation dissolves in a way that is genuinely new, at least to me, in twenty years of writing software.

I don't know what the next six months look like. My March prompts are already so compressed that there's not much room left to shorten them. The skill ecosystem I've built is sophisticated enough that I'm maintaining it more than extending it. Maybe the next phase is something I can't predict from the current trajectory, the way October-me couldn't have predicted the plugin ecosystem of January.

I'll keep counting the lines, though. The file keeps growing, and the shape of it keeps telling me things I didn't know I was learning.
