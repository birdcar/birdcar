---
title: "The tools that build the tools"
description: "The productivity gains from AI coding tools are real. The hidden overhead is also real. Here's what happens when you try to fix it."
date: 2026-04-10
tags:
  - tools
  - culture
draft: false
---

@aside title="Epigraph"
We shape our tools and thereafter our tools shape us.
@endaside

You install an AI coding assistant. You type a prompt. It writes some code. You fix the parts it got wrong. You type another prompt. This is the loop, and for most people it stays the loop forever. Prompt, generate, fix, repeat.

I did this for about three months. It was useful. I built things faster than I would have alone. I explored codebases I didn't know. I generated documentation nobody else was going to write. The tool was good at its job, and I was good at asking it to do that job, and that was the whole story.

Then in December something shifted, and the story stopped being about a tool and started being about a system.

## Thirty-three skills and a problem I didn't know I had

I now have a custom skill ecosystem inside Claude Code. Custom slash commands that trigger multi-step workflows. Custom agents that handle specialized tasks. Custom hooks that enforce rules before and after every tool call. If I type `/commit`, it doesn't just commit. It runs a semantic analysis of the changes, generates a conventional commit message, validates it against the project's style, runs pre-commit hooks, and only then creates the commit. If I type `/new-post`, it loads my writing voice profile, selects a narrative framework from a catalog of 25, walks through a structured content pipeline, runs a humanization audit against an AI-slop blacklist, and writes a blog post to the right file path with correct frontmatter.

This sounds like overengineering. It probably is overengineering, depending on who's counting. But the economics changed in a way I didn't anticipate.

## What is: life without skills

Before the ecosystem, every session with Claude Code started from zero context. I'd open a project, explain what I was working on, remind it of the conventions, specify the file paths, describe the output format I wanted. Every. Single. Time.

For a customer reply at work, this meant: find the relevant codebase, search for the API endpoint, read the implementation, check the public docs, cross-reference with any recent changes, understand the customer's specific configuration, and then draft a response in the right voice for a developer audience. That's forty minutes of context-building before I write a single sentence.

For a blog post, this meant: explain my writing voice, describe the narrative structure I wanted, list the things I didn't want (no em dashes, no "delve", no thesis-at-the-top), specify the frontmatter format, point to example posts. Every time. The same instructions, rephrased slightly, hoping this time it would stick.

The repetition wasn't Claude Code's fault. It's an LLM. It doesn't remember. But the repetition was my problem, and I was spending 20-30% of my working time on it.

## What could be: the first skill

The first skill I built was `/customer-reply`. It's a workflow that researches across the codebase at work, Slack threads, Zendesk tickets, and public docs, then drafts a response in my voice. I spent maybe three hours building it. It runs in under two minutes now, and the output is good enough that I edit rather than rewrite.

Three hours of investment. Two minutes per use. I send maybe 10-15 customer replies per week. The math is trivially obvious in retrospect, but I didn't think of it as math at the time. I built the skill because I was annoyed by the repetition, not because I'd calculated the ROI.

But after that first skill worked, something clicked. The repetition wasn't just annoying. It was a tax on every session, and the tax was compounding because I was using Claude Code more every week.

## What is: the hidden cost of unstructured AI work

This is the part most people don't see. The productivity story around AI coding assistants focuses on the generation: how fast it writes code, how many lines per minute, how it compares to a human. Nobody measures the overhead.

I went back through my history and looked at prompts that were pure setup. Context-setting and convention-reminding prompts that didn't produce any artifact. They were 15-20% of my total prompts in October and November. By January, as I used Claude Code for more complex tasks, the percentage was climbing toward 25%. A quarter of my prompts were about telling the tool how to do its job, not about the job itself.

This is the same pattern I've [written about in support organizations](/writing/your-metrics-are-bullshit/). The metric that looks good (prompts per day, tickets per hour) hides the metric that matters (what percentage of effort goes to the actual work versus the machinery around it). The volume goes up, and you feel productive, and the overhead goes up too, and you don't notice because you're busy feeling productive.

## What could be: the multiplier nobody's measuring

After `/customer-reply`, I built `/commit`, `/new-post`, `/review-pr`. Then I started building skills for skills: a skill-forge that generates new skills from brain dumps, a skill-validator that checks quality, a skill-optimizer that improves descriptions for better trigger accuracy. Meta-tools. Tools that build tools.

My skill usage went from 1% of prompts in October to 30% at peak in February. That 30% isn't just "I used a shortcut instead of typing a long prompt." Each skill invocation replaces a sequence of 5-15 prompts that would have included the context-setting, the convention-reminding, the format-specifying overhead. The compression ratio is roughly 10:1 when you account for everything a skill encodes.

The part I didn't anticipate: the overhead didn't just shrink. It moved. Instead of spending 25% of my time telling Claude Code how to do things, I spend maybe 5% of my time maintaining and improving the skills that tell it for me. The maintenance is also more pleasant, because it's a design problem (how do I encode my preferences?) rather than a repetition problem (how do I say the same thing again?).

## What is: the obstacle nobody talks about

The obstacle is this: building an AI skill ecosystem requires the same kind of systems thinking that most people using AI assistants don't think they need. You have to notice the patterns in your own work. You have to identify which repetitions are worth encoding and which are one-offs. You have to design abstractions over your own workflows, which means understanding your workflows well enough to see their shape. And then you have to sit down and build something, which is slower than just typing the same prompt again.

Most developers can do this. It's the same skill as refactoring code: spot the duplication, extract the pattern, parameterize the variation. But most developers don't think to apply it to their AI interactions because the AI interaction feels like a conversation, not a system. You don't refactor a conversation.

Except you can. And when you do, the conversation stops being a conversation and starts being an interface, a programmable surface that you shape to match how you actually work.

## The flywheel

The reason this compounds isn't just time savings. It's that each skill I build teaches Claude Code more about how I work, which makes the next skill easier to build, which means I build more skills, which means Claude Code knows more about how I work. The context I used to provide manually is now encoded permanently. The conventions I used to repeat are now enforced automatically. The workflows I used to manage in my head are now executable.

In February, I built a blog post from brain dump to finished draft in 45 minutes. Not because Claude Code is fast at writing. Because the skill encoded my voice, my narrative preferences, my formatting rules, my quality checks, and my file structure. I wasn't directing a generative model. I was running a pipeline that happened to use a generative model as one of its components.

That's the shift. You go from *using an AI tool* to *building a system that uses an AI tool*. The tool gets better because you made it better, not because the model improved. The next model improvement will compound on top of what you've already built, rather than starting from scratch.

I don't think most people using AI coding assistants will build a 33-skill ecosystem. I don't think they need to. But I think the gap between "I use Claude Code" and "I've built workflows on Claude Code" is going to be the gap that separates productive AI-assisted developers from the rest. Not prompt engineering. Not model selection. Whether you're using the tool, or whether you've made the tool yours.
