---
title: "Your metrics are bullshit"
description: "Breaking the doom cycle of support metrics with service level thinking"
date: 2024-05-01
tags:
  - support
  - metrics
---

@aside title="Epigraph"
Those who believe that what you cannot quantify does not exist also believe that what you *can* quantify, *does*.
— The Tyranny of Metrics
@endaside

The companies in this story are not real, but you've worked there. The people in this story are not real, but you know them.

## Welcome to the doom cycle

You're in support at Big Tech Company. You got this job after leaving Starbucks five years ago, worked your way up to senior support employee, and you're frustrated. Your leaders just told your team there was no new headcount for this year although you've lost some key folks and followed that up by increasing weekly ticket quotas by five. When you complain, you're told to "do more with less."

You love the product and enjoy your team, but this is intolerable. You start interviewing.

An authentication startup that just graduated Y-Combinator, closed its first round of funding, and is making its first "real" support hire looks promising. You get the gig; it's stressful in the way startups are, but you feel impactful. You're eight equals working on a product you all believe in. When you talk about customer needs, everyone listens. When there's debate over a particular solution, you don't always win, but you feel heard. You build a small team, collecting folks from the diaspora of talent scattered by the company you left, finding new folks who impress you. You're a team of five or six Support folks doing incredible work. The camaraderie is palpable. You communicate well, you solve problems as a unit. You are experts. You accomplish more as a six-person team than the 20-person team at your previous gig. Success!

But the gears of the doom cycle are creaking to life.

Like many catastrophes, it comes in the guise of good news: a $100M A-round! The Engineering and Product teams explode. The user base explodes. And, predictably, so does your support queue. Your response times -- one of the only things you're measuring -- slip a little, but your team rallies. You know it can't hold, but are cautiously optimistic.

*The queue continues to explode.*

You ask for headcount and your founders, aloof and stressed, tell you to hire whoever you need. Your team balloons to 50, and response times drop. You promote a bunch of your first hires. Many are first-time managers; they're nervous and inexperienced, but want to do a good job.

*The queue continues to explode*

Your new folks are struggling to learn and keep up. The glowing online reviews about your company's support turn nasty. Sales wants to guarantee enterprise customers four-hour resolutions.

*The queue continues to explode.*

The founders drag you into a meeting; they're seeing complaints everywhere and they don't understand. They want ambitious goals and marked improvement. They don't say "or else," but you hear it. So you and your managers set the performance metrics you used at your last job: Support Professionals will spend six-ish hours a day in the queue, and two-ish hours on the project work that needs doing in a still-growing organization.

Slack goes silent as people live in DMs, keeping their heads down. Your managers are frustrated, the numbers still aren't looking good. Product updates ship and no one tells Support. Your docs are out of date. Your backlog is in the hundreds and growing. You fire some folks who aren't meeting their ticket quotas, a first for you.

The numbers stabilize, but it's still not great. Everyone is frustrated. Everyone is angry. Your first hire leaves, taking a job at a new AI startup as their first support hire.

As things improve a little, you move a couple of people to focus on docs. You raise the weekly ticket quota by five. Your managers tell you that your team works overtime to keep their numbers looking good. They ask for more headcount.

You tell them they need to "do more with less."

## There are rules, but we consistently break them

Was that uncomfortable? A little close to home? I used to think this was how growth works. To get around it, I avoided any company I thought was at the end of the cycle.

But the doom cycle isn't inevitable: it's a known result of misusing metrics.

There are known and pernicious effects that come from misunderstanding numbers, especially when those numbers measure human effort and determine someone's continued employment or salary. They've been studied for decades across professions, cultures, and economies. There are rules that, when you break them, damage your team and turn people against each other.

The ways we measure and motivate Support Professionals violate almost all of these rules.

### Goodhart's Law

> Any observed statistical regularity will tend to collapse once pressure is placed upon it for control purposes.
>
> -- Charles Goodhart (1975. Problems of Monetary Management: The U.K. Experience)

Or more simply:

> When a measure becomes a target, it ceases to be a good measure.
>
> -- Marilyn Strathern (1997. Improving ratings': audit in the British university system)

We often approach support with the false assumption that all customers are broadly the same, so their support expectations are the same. Economics has this flawed idea of a "Homo Economicus," a universal stand-in for a person who always acts to maximize their self-interest. This person doesn't exist, and neither does "Homo Customericus."

Based on this false belief, we invent "industry standard" benchmarks and expectations that apply to every support team and customer experience, and then use them to set targets for and measure the success of individual Support Professionals. We measure how many tickets a person answers -- something customers don't know or care about -- as a proxy for their support experience. We measure average handle times, something users don't care about and which is highly variable. We obsess over finding "magic" single numbers that tell us if our support is good or if a Support Professional is good.

Goodhart's law says that when we do that -- when we take some benign observation of how a human-centered system operates and make a specific value of that measurement a target or a goal -- we destroy the usefulness of the observation.

### Campbell's Law

> The more any quantitative social indicator is used for social decision-making, the more subject it will be to corruption pressures and the more apt it will be to distort and corrupt the social processes it is intended to monitor.
>
> -- Donald T. Campbell (1979. Assessing the impact of planned social change)

When a number determines whether you keep your job, you'll do anything -- including cheat -- to improve it.

Support leaders focus a lot on Customer Satisfaction (CSAT) ratings. Almost no one really knows what their company's real CSAT rating is. Senior leaders get measured by whether that number goes up or down. Cherry-picking ensures it goes in the right direction:

"Oh, don't send the satisfaction survey to anyone who's gotten a refund."

"Make sure we don't send a survey to anyone from that company, they hate us."

Your ICs cherry-pick to reach their ticket quota. You do it to keep your CSAT up, and it's a rational strategy for inflating a number that's (1) not entirely within your control and (2) arbitrarily used to punish you. You know that the CSAT number doesn't tell the whole story and absolutely isn't within Support's control, so you find ways to minimize the damage. That's cheating. That's data corruption. Ultimately, this corruption pressure hurts both your Support organization and your customers.

### Surrogation

> The tendency for managers to lose sight of the strategic construct(s) the measures are intended to represent, and subsequently act as though the measures are the constructs.
>
> -- Willie Choi; Gary Hecht; William B. Tayler (2013. Strategy Selection, Surrogation, and Strategic Performance Measurement Systems)

> [A] manager charged with 'delighting the customer' who uses customer satisfaction surveys to gauge strategic success may begin to see maximizing survey results as the strategy, and behave accordingly.
>
> -- Willie Choi; Gary Hecht; William B. Tayler (2011. Lost in Translation: The Effects of Incentive Compensation on Strategy Surrogation)

Surrogation equates "Well, the number went up" with "fulfilling our mandate to empathize with and serve customers." We're incentivized to take measures that keep the number up even though they go against our goal of improving customer satisfaction, because we've decided that a good number *is* customer satisfaction. It's the final boss of the Support game, and it's why we don't question the obvious corruption of our measurements when we should: the measurement itself has become the goal.

How many Support teams have a CSAT rating above 80% but don't tell their company leadership what their customers say about their product on Reddit? We've mistaken our ability to improve that number (the measure) with our customer's actual satisfaction (the actual goal that measure is intended to represent).

We're tired, we're angry, we've seen our organizations decimated for not meeting arbitrary goals written by people who don't understand our work. There has to be a better way.

## Service agreements that serve us

I think most of us have heard of an SLA before -- a Service Level Agreement. SLAs are the *last* piece of a three-piece puzzle. Changing our approaches to all three levels has the potential to transform your Support org and your customer experience.

First come Service Level Indicators (SLIs). Service Level Indicators are the raw numbers that describe how your system is performing. Most Support metrics are SLIs. Second, you have Service Level Objectives (SLOs). This is your "target": a number -- preferably a range -- for a service that is measured by an SLI. If you have an SLI that shows your team is consistently and collectively responding to tickets in eight hours on average, your SLO will say that your 75th percentile response time is eight hours.

Third -- always last and with the involvement of the sales, engineering, and legal teams -- you have SLAs or Service Level Agreements. These are an explicit contract with your users that includes the consequences for missing certain SLOs.

You can define all three in ways that avoid the doom spiral and incentivize team effectiveness.

### Start with objectives - human-centered objectives

To design a system for measuring and understanding your service level, start with your objectives. Actively *avoid* starting with what you can measure;  this road leads straight to surrogation land. Start with objectives, based on  *your* users' realities and core needs.

I once did Support for a developer-focused platform. If we had chosen phone support as our main channel, most of our users would have revolted. They want email, and the emails they appreciate are, quite frankly, terse. They want to tell you about an issue, and then they don't want to hear anything at all until  the thing is fixed. Many were happy as hell with a simple "heard" or "fixed, thanks."

What we defined as "good" support was hyper-specific to *our* users. Your customers are *yours*. Think about them, not about standards or industry trends. How do *they* want to be talked to? What are *their* expectations for response times? What channels do *they* care about, and *how much* do they care about those channels? (And if you don't know the answers, you have an easy fix: *ask your customers*.)

How do you define specific, useful objectives? Start here:

- *Keep it concrete.* Don't say "we want every user to feel whole in their soul after contacting us." How would you ever really know?  Instead, get specific: "90% of users who write to support for a non-blocking issue should get a response in no more than eight hours." You know what your non-blocking issues are; you can keep a list (ideally publicly, so your users know what to expect from you.)
- *Avoid absolutes.* Notice how precise that example was? That's on purpose. It's tempting to provide absolutes like "everyone gets a response in four hours," but all systems have limits. Your Support team members are not a bunch of interchangeable cogs with the same skills, gifts, and speed. Avoid absolutes to create space for your diverse employees and users.
- *Don't have too many.* You want concrete objectives in that you want to focus on specific things your users care about, but you *also* want as few of those objectives as possible. Two or three is a good place to start.
- *Embrace imperfection.* You will not get this right the first time. Adjust objectives up or down, or scrap them and start over as you learn more. Last year's numbers mean nothing if your user base grew by 25% this year! Don't aim for perfect, aim for progress.
- *Build in buffers.* Your goal isn't a set of objectives that you have to work overtime to keep. Your goal is an error budget.If you know you can respond to everyone in eight hours, set your SLO as the third quartile of requests in that time. That gives you a buffer to experiment with ways of preventing tomorrow's tickets rather than struggling through today's and constantly straining as you grow.

### Make metrics with meaning

You have some objectives now, and now you need to figure out how to know if you've met them. This is where you start thinking about SLIs and metrics.

- *Track fewer, more meaningful indicators.* An objective *can* have multiple measurements associated with it, but I'm going to humbly suggest that you start associating objectives with specific measurements. And wherever possible, those measures should not be proxies for what you're trying to achieve. In other words, if you take the example of "the third quartile of conversations get a response from Support in eight hours", that has a direct measurement you can use: How long it takes for your team to respond to conversations. This isn't always possible, but it's important that your team can see how they're directly affecting things together and begin thinking as a team.
- *Use distributions, not averages.*  Averages are neat and clean, but their abstraction hides important nuance. If Jeff Bezos walks into a room of 100 people and we take an average, they're all billionaires. If we used that average to decide, "Everyone has to give 10 million dollars to charity every day until they die," is that doable? Did the average help you make a smart decision? Or did it lie to you and make things worse? What you want are distributions. "99.9% of people in this room have a net worth of $80k, and 0.01% have a net worth $161b" Distributions group data, show you outliers, and give you more information. Whenever possible, use distributions and avoid averages.
- *Resist corruption.* Nothing changes if you cherry-pick these numbers. You have to count *everything*; it's the only way you'll know the truth. This should be a less scary proposition now that you're using SLOs and SLIs that describe and measure distributions. Those difficult customers whose CSAT scores you don't want to see? They'll fall into that top 1%, and you've built a buffer for that.

### Lose your misleading leaderboards

Apple used to tell Genius Bar employees that if they wanted to, it could replace the entire Genius Bar with a vending machine that spits out replacement devices and reads the warranty off the device. It doesn't, because it's not just paying Geniuses to fix iPhones -— it's paying them to fix the relationship that the customer has *with Apple*.

Your customers aren't reacting to their experience with the wait time or the individual Support Professional they're talking to. They're reacting to their relationship with your product and your company being broken. They don't care how many tickets each person is answering, or even what your total headcount is. They just want help.

As a Support leader, it's tempting to lose that perspective by obsessing over each individual Support Professional's response numbers, but this is another form of surrogation. It leads you away from all of the levers that could improve your objectives and enable you to help folks and towards the belief that making everyone's response numbers roughly the same *is the same thing*. This keeps you from recognizing when the most impactful work is work that can't be measured directly.

With that in mind, you should:

- *Hide individualized data.* The only people who should be seeing individualized data are the individual employee and their direct manager. That's it. If you're not the person who the data is about and you are not the one managing that person, you should only be seeing team-level distribution data about *the system*. You do not have enough context to make judgments about individualized data. Hiding that data will bring teamwork back to your organization.
- *Prioritize celebrating team success over individual achievement.* Your objectives are set at the team level because that's how customers experience your support. So celebrate the team working together to solve a problem: One person answered 400 tickets in a day because someone else hopped out of the queue to do crisis communication and a third person was writing user-facing docs to stop the deluge of tickets. Celebrate them all for communicating and working together to solve the problem and meet the team's objectives. And not just in the moment — remember how they worked together when performance review season comes around.
- *Focus on team-based, differentiated solutions.* Now that objectives aren't individualized and it's not terrifying or career-ending to leave the queue and solve root issues, you have an incentive to work as a team to hit your objectives and maintain your service level. Some people are going to love answering tickets and hate being a liaison to product. Other folks are going to have a knack for writing docs that keep users from opening tickets at all. Let people be good at different things. Focus on hiring, compensating, and enabling team players with differentiated skills.

## Breaking the cycle

Nothing I've written here is that radical in practice, but it *is* a departure from the "best practices" and received wisdom we've been relying on for decades. This is a call to center the Support Professionals, the customers they support, and our end goal of providing the best possible support. The doom cycle is avoidable, but avoiding it takes effort, intention, and care.

At the beginning of the cycle, we're in the queue all day. We're painfully aware of the flaws with this system of individualized objectives. It keeps us from proactively helping customers and doing our best work. When we become leaders, however, we fall back to the systems we know, believing that we can tweak and tame it to produce a different outcome.

The problem is that the system spends all its time measuring a tiny picture of the effort of your individual employees. We have convinced ourselves that this measurement is a perfect surrogate for the objectives and business outcomes that we're trying to achieve, but as we've seen above, that surrogacy leads us away from true understanding.

True understanding is difficult to achieve. It requires us to think deeply about *our* customers, businesses, and goals. There are few standard metrics or formulas that can help us, and it's possible we aren't currently tracking the information we need. But we can't sidestep that work by focusing on the data that's easy to measure, like how many conversations each person answers in a day. That measurement doesn't actually tell us anything useful about the work of our team.

Your Support Professionals know what they're doing. They know what's wrong with the product, your Support Level, and the customer experience. They know the big-picture fixes that would provide relief if they just had the time to focus on anything other than the constantly ticking clock. They want to do good work, they want to see their business succeed, and they want customers to have a good experience from the moment they begin using your product or service. Listen to them. Trust them.

If you take nothing else from this, I want it to be that Support work is done by teams of people -- not a collection of individual queue crushers -- and it takes diverse skills, dispositions, focuses, and areas of expertise to do well. Make sure that the way you measure the work reflects that.
