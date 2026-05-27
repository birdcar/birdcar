---
title: "Just build it twice"
description: "AI didn't make me faster. It made me willing to throw away a draft I would have died defending."
date: 2026-05-26
tags:
  - ai
  - developer-tools
  - practice
  - agents
draft: false
---

I was reading [Hacker News](https://news.ycombinator.com) the other day, as one does, and a post titled ["Using AI to write better code more slowly"](https://nolanlawson.com/2026/05/25/using-ai-to-write-better-code-more-slowly/) had climbed near the top. I read it, and then I just kinda sat there chewing on it while I worked for the afternoon. Ultimately, I think Nolan has named something I'd been turning over for months without quite being able to articulate.

I've been writing about AI tools for a little while now, With the general tone being somewhere between [lightly skeptical and inquisitive](https://www.birdcar.dev/writing/your-ai-wrote-a-bug/). Right now I'm basically of the belief that AI leaves the hard problems exactly as hard as they were before. The easy stuff is inarguably easier, and boilerplate is functionally eliminated, but it isn't the society-ending inflection point everyone keeps fretting about.

What Nolan's post did was clear a path through the part of the question I hadn't worked out.

Before I'm branded a hater or in denial, let me be clear about where I'm standing: I'm enthusiastic about these tools. I use them every day, and I've built things in the last year (including a business) that I would not have started, much less finished, without an agent (or several) at my elbow. I regularly teach, encourage, and recommend the use of these tools at work and as a freelancer

Having said that, I also try very hard to think critically about my tools, which is the only way I know how to keep using anything in good conscience. So when I say the skeptics are wrong, I mean it. They're wrong, full stop. And when I say the hype people aren't quite right, I mean something more particular: they're not sure what they're actually right about. Somewhere between those two positions is a much smaller, much weirder claim, and that's the thing I want to talk about.

## Speed? Well.. kinda

The dominant narrative says AI makes engineers 10x faster, 30x faster, sometimes 100x faster, depending on which CEO is in front of a camera that day. The numbers vary, but the gospel is the same. Type a prompt, get code, ship feature, repeat. Whoever ships fastest wins, and the agent is the unlock.

I have been doing this long enough to know that the slow part of building software was never typing. It wasn't typing twenty years ago and it isn't typing now. The slow part is figuring out what to build, why to build it, and how to be confident that what you built actually does the thing you thought it would do. The slow part is the part where you sit with a half-working prototype and realize that the _real_ problem isn't the bug you're hunting, it's that the entire framing of the feature was wrong.

An agent _does_ help with that, kind of. Just not in the way that folks are talking about right now.

As many detractors point out, an agent will gleefully accelerate you into a wall. It will generate thousand-line refactors against (`{your, its}`) flawed premise with admirable confidence. I think anyone who uses an LLM even casually for development has watched this happen.

This is not new criticism, lots of smart people have said it. You know the agent makes you faster at the parts you were already doing thoughtlessly and confidently incorrect about the parts you needed to slow down on.

So I kept pulling at the thread. If the speed framing is wrong, and the agent doesn't help with the hard parts, what _is_ it doing that's worth my time? Because the honest answer, the one I kept landing on, is that there is something. I think critically about my tools. I would not still be reaching for these every day if I couldn't tell you, plainly and in one sentence, what they are actually doing for me. So let me tell you.

## Fail faster, but also more completely

The agent has lowered the cost of building the same thing twice.

That is the entire delta. That's where I'm actually faster. Everything I value about working with these tools collapses into that one sentence.

The slow, painful part of building something is not the typing. It's also not, exactly, the thinking. It's that for many problems, you have absolutely no idea what you're doing. The only way to figure out what you actually want is to build the wrong thing first and use it for a while. You don't know what ergonomics you want from a CLI until you've typed the wrong invocation seventeen times. You don't know what shape your data should have until you've written the query that exposes how badly you got it wrong. You don't know what a feature should feel like until you've shipped a version that feels off, and you can finally put a finger on _why_.

For most of my career, this was a luxury. Building software was expensive enough in terms of both time and money that "build it twice on purpose" was a catastrophic mistake. The kind of story you tell in your job interview about how you failed one time. In reality, most teams build it once, ship it, suffer with it, and then maybe in eighteen months get the budget for a "v2" that's usually still constrained by the bones of v1. The cost of throwing the first one away was high enough that nobody actually threw the first one away.

What agents have _actually_ made faster and cheaper is _being wrong_ and more importantly _learning_ that you're wrong. They have made me willing to throw a draft in the trash that I would have died defending six years ago. That is a different thing, and it is bigger than it sounds.

## You only learn what you want by using the wrong thing

I have a problem I haven't solved before. I don't really know what I want. I have a vibe, a constraint, maybe a half-formed sentence about what "good" looks like. In a previous era I would have done one of two things: stared at a blank file for two days writing pseudocode, or built a careful, deliberate v1 that I would then have been emotionally married to by the time I realized it was the wrong v1. (Technically, the third thing would be "preemptively give up and live with the problem sucking").

Now I do something different. I open up Claude or Codex, dump out everything I'm half-thinking, and let it interview me into a spec. The agent then does something I cannot easily do for myself via "rubber duck" talking or white-boarding, which is fill in the gaps in my own thinking. The places where my thinking was fuzzy, where I would have shrugged and waved a hand and said "we'll figure it out as we go," get filled in. Then the agent builds the thing the spec asks for, and I start trying to use it.

Here, finally, is where the work actually happens. I use the thing the agent built, and within an afternoon I can usually tell you in detail what is wrong with it. The code is ok-to-good. Bugs are absolutely present, and fixing them is often a clarifying experience, but what I _usually_ find out is wrong is the _shape_. Even with the gaps filled in, I was foundationally incorrect about what I wanted to build, and I could not see that until I had the working thing in front of me to use.

That clarity is impossible without the v0. I cannot predict in advance what will feel off about a system I have not yet used. I don't know that anybody can, reliably. The people who say they can are lying, or they're working on problems they have already solved (or mostly solved) before.

So I throw it out. I go back to to my tools with everything I now know that I didn't know yesterday. I write a new spec that is dramatically sharper than the first one, because the first one taught me what was actually load-bearing. I let the agent build the new thing. Two generations, two days, and I have a foundation I trust enough to stand on. That foundation is not a 10x productivity gain. It's something more valuable: a thing I would not have arrived at any other way.

## When in doubt

None of this is a productivity claim. I am not telling you agents will make your work twice as fast.

What I am telling you is this: for the class of problem where you don't yet know what you want, the cost of finding out has dropped enough to matter. By enough that I now build throwaway drafts on purpose, on a Tuesday afternoon, as a standard part of how I work. That's the change. That's the whole pitch.

The framing of "AI = speed" has been making me crazy because it sets up a thing to argue about that isn't the interesting thing. The interesting thing is that we now have a tool that makes the second-draft possible at the level of the individual engineer. We used to need a team of ten and an eighteen-month roadmap to rewrite something. Now I can rewrite it on a Tuesday afternoon, knowing what I know on Tuesday, not what I thought I knew on Monday morning.

So when in doubt: just build it twice.
