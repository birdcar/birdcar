# The Jeremiad

The Jeremiad is an American rhetorical form descended from Puritan sermons, named after the Old Testament prophet Jeremiah. The structure is deceptively simple: invoke a shared ideal, show how the community has fallen from it, and call for return. What makes it distinctive is the moral energy — the jeremiad doesn't just argue that things could be better (that's the Sparkline), it argues that the community has *betrayed its own values* and must be called to account. The tone is prophetic, not analytical. The writer speaks from within the community, as one of its members, which is what gives the indictment its force. An outsider's critique is dismissible; a member's jeremiad is not, because the shame is shared.

For blog posts, this framework works when you're writing about practices your industry claims to value but consistently fails to honor. Support teams claim to center the customer but measure ticket velocity. Engineering orgs claim to value craft but optimize for shipping speed. Companies claim to care about employees but run them through doom cycles. The jeremiad names the gap between what we say and what we do, and it hits harder than a straightforward argument because it turns the reader's own stated values against their actual behavior. Nick's "Your metrics are bullshit" is a jeremiad: it invokes the ideal of genuinely serving customers, shows how the industry's metrics regime betrays that ideal, cites the academic authorities who've warned us (the prophets), and issues a call to return through SLI/SLO/SLA thinking.

## The Steps

### 1. Invoke the Covenant (The Shared Ideal)

Establish the ideal that everyone in the community claims to hold. This isn't your personal value — it's the value the community has publicly committed to. The reader should nod along: "Yes, of course we believe this." The covenant needs to feel specific and remembered, not abstract. Ground it in a moment when the ideal was alive — a small team, an early career experience, a company that got it right.

**In a post**: "You love the product and enjoy your team. You're eight equals working on a product you all believe in. When you talk about customer needs, everyone listens. When there's debate, you don't always win, but you feel heard. You accomplish more as a six-person team than the 20-person team at your previous gig."

**Section approach**: 1-2 H2 sections, 2-4 paragraphs. Use second-person "you" to pull the reader into the ideal. Paint the scene with enough detail that the reader recognizes it from their own experience — the specific feeling of a team that works, a product that serves, a culture that means what it says. Don't rush this. The weight of the indictment depends on how real the covenant feels.

**Writing techniques**: Extended scenario in second person, grounding the ideal in concrete experience rather than abstract principle. An `@aside` with an epigraph from an authority who articulated the ideal. The opening paragraph should feel warm, almost nostalgic — the reader should want to be in this world.

**BFM usage**: An `@aside title="Epigraph"` at the very top with a quote that captures the ideal. This is the jeremiad's traditional opening — the prophetic text that the community will be measured against.

### 2. Show the Fall (The Betrayal)

Now trace how the community fell from the ideal. This is the longest section and the emotional core of the post. The fall should feel *inevitable* — not the result of villainy, but of small, rational decisions that each seemed reasonable at the time. The doom cycle. The reader should watch themselves making the same choices, recognizing each step with growing discomfort.

**In a post**: "Like many catastrophes, it comes in the guise of good news: a $100M A-round! The Engineering and Product teams explode. The user base explodes. And, predictably, so does your support queue... You set the performance metrics you used at your last job... Slack goes silent as people live in DMs, keeping their heads down."

**Section approach**: 3-5 H2 sections with escalating pressure. Each section represents a stage of the fall — each one further from the covenant, each one more entrenched. Use the second person throughout to keep the reader inside the experience. Section headers should be punchy markers of the descent: "Welcome to the doom cycle," not "The Problem with Growth." Paragraph density should be high — the narrative should feel relentless, one pressure compounding the next.

**Writing techniques**: Repetition as a structural device — a recurring phrase or image that returns at each stage of the fall, each time worse ("*The queue continues to explode.*"). Italicized lines as rhythm breaks between escalation stages. Parallel structure showing the same pattern at different scales. No code in this section unless the code itself is evidence of the betrayal (a metric dashboard, a performance review template).

**BFM usage**: Avoid `@aside` during the fall — it would break the mounting pressure. Let the narrative do the work. A thematic break (`---`) can separate the fall from the indictment if the transition needs breathing room.

### 3. Cite the Prophets (The Authoritative Warning)

Introduce the voices that warned us this would happen. Academic sources, historical precedents, named authorities. The jeremiad's prophets serve a specific function: they prove that the fall was *knowable and known*. The community wasn't blindsided — it was warned, and it chose not to listen. This transforms the fall from "unfortunate" to "culpable."

**In a post**: "There are known and pernicious effects that come from misunderstanding numbers... Goodhart's Law says that when we take some benign observation and make a specific value a target, we destroy the usefulness of the observation... Campbell's Law: when a number determines whether you keep your job, you'll do anything — including cheat — to improve it... Surrogation equates 'the number went up' with 'fulfilling our mandate.'"

**Section approach**: 2-4 H2 sections, each centered on a different authority or principle. For each: the formal citation (blockquote with attribution), then the conversational translation ("Or more simply:"), then the application to the community's fall. The progression should escalate — each prophet's warning is more damning than the last. The final prophet should name the deepest failure: not just that we did the wrong thing, but that we *convinced ourselves the wrong thing was the right thing*.

**Writing techniques**: Blockquotes with full academic attribution for each authority. Conversational translation paragraphs that make the academic language concrete. Application paragraphs that connect the warning directly to the fall described in Step 2. The structure within each prophet section is: quote → translate → apply. Rhetorical questions as transitions between prophets: "How much more horrifying is it, then, that..."

**BFM usage**: Blockquotes are essential here — they carry the weight of authority. Each prophet gets their own blockquote with proper `-- Author (Year. Title)` attribution. Don't use `@aside` for the quotes; they belong in the main flow because they *are* the argument, not tangential to it.

### 4. Issue the Call (The Path of Return)

The jeremiad doesn't end with despair — it ends with a call to return to the covenant. The call must be specific, concrete, and achievable. It's not "do better" — it's "here's exactly how to realign your practices with the values you already claim to hold." The tone shifts from prophetic to practical. The indictment was emotional; the call is operational.

**In a post**: "Service Level Indicators are the raw numbers. Service Level Objectives are your targets — a range, not an absolute. Service Level Agreements come last, with involvement from sales, engineering, and legal... Start with objectives based on *your* users' realities and core needs."

**Section approach**: 2-4 H2 sections with concrete prescriptions. Each section addresses one dimension of the return — one practice to change, one metric to replace, one structure to adopt. Use bulleted or numbered lists for actionable steps. The prescriptions should echo the covenant from Step 1: "Remember how it felt when the team of six was doing incredible work? Here's how to protect that feeling at scale."

**Writing techniques**: Bulleted lists with italicized lead-ins for each concrete action. Concrete examples grounded in the reader's actual tools and workflows. A callback to the covenant scenario — show the reader the same world from Step 1, but now with the prescriptions applied. The closing paragraph should carry moral weight: not just "try this" but "your team deserves this."

**BFM usage**: `@callout type="tip"` for the most actionable prescriptions. The closing section should be clean prose — no directives. The final paragraph is the jeremiad's peroration: a direct address to the reader that carries the moral authority earned by the indictment.

### 5. The Peroration (The Moral Close)

The final paragraphs of a jeremiad are its most distinctive feature. They circle back to the covenant, but now the reader has been through the fall, heard the prophets, and received the call. The peroration restates the ideal — not as nostalgia, but as obligation. It often ends with a direct address: "If you take nothing else from this..."

**In a post**: "Your Support Professionals know what they're doing. They know what's wrong with the product, your Support Level, and the customer experience. They want to do good work, they want to see their business succeed. Listen to them. Trust them."

**Section approach**: 1 H2 section or no header at all — just the closing paragraphs. 2-4 paragraphs. The first returns to the world of the covenant. The second restates the ideal with the earned authority of everything that came before. The third issues the direct challenge. Keep it tight. The jeremiad's power at the close is in compression, not expansion.

**Writing techniques**: Second-person direct address. Short, declarative sentences. A callback to the opening scenario or epigraph. The final sentence should be quotable on its own — it should carry the full weight of the post even extracted from context.

**BFM usage**: None. The peroration should be unadorned prose. Every directive would dilute the moral authority.

## Tone and Delivery

The jeremiad's tone moves through four registers: **warm** (the covenant), **relentless** (the fall), **authoritative** (the prophets), and **direct** (the call and peroration). The transitions between registers should feel natural, not abrupt.

The most common failure mode is self-righteousness. The jeremiad works because the writer is *inside* the community being indicted. "We" failed, not "they" failed. The writer must implicate themselves: "I used to think this was how growth works," "I set the performance metrics I used at my last job." The moment the writer positions themselves above the community, the jeremiad becomes a lecture and loses its force.

The second failure mode is vagueness in the call. A jeremiad that's all indictment and no prescription is just complaining. The prophetic tradition demands that the call to return be as specific as the indictment was vivid. If you can indict with concrete scenarios, you must prescribe with concrete actions.

## Length Tier Mapping

### Short Post (800-1500 words)

Compressed jeremiad — one prophet, one prescription:
- **Covenant** (~150 words): Brief evocation of the ideal
- **Fall** (~300 words): One compressed doom cycle
- **Prophets** (~150 words): One authoritative citation with application
- **Call + Peroration** (~300 words): One concrete prescription and the moral close

Short jeremiads work when the ideal is universally known and the fall is immediately recognizable. Don't try to fit multiple prophets into a short post.

### Medium Post (1500-3000 words)

The natural home for the Jeremiad:
- **Covenant** (~350 words): Full scenario establishing the ideal
- **Fall** (~800 words): Multi-stage descent with escalating pressure
- **Prophets** (~500 words): 2-3 authorities with translations and applications
- **Call** (~400 words): 2-3 concrete prescriptions
- **Peroration** (~150 words): Moral close with callback

### Long Post (3000-5000 words)

Extended jeremiad with deep prophetic engagement:
- **Covenant** (~500 words): Rich scenario with multiple dimensions of the ideal
- **Fall** (~1200 words): Extended doom cycle with named stages, recurring motifs
- **Prophets** (~1000 words): 3-4 authorities, each with full citation, translation, and application
- **Call** (~800 words): Multiple prescriptions organized by role or priority
- **Peroration** (~300 words): Extended moral close circling back to covenant

"Your metrics are bullshit" at ~4,000 words is a long jeremiad.

## When to Use

- **Posts about industry practices that betray stated values**: When the gap between what we say and what we do is the point. Support metrics, hiring practices, DEI commitments, engineering culture.
- **Posts where moral authority matters more than novelty**: The jeremiad doesn't introduce new ideas — it holds the community accountable to ideas it already accepted. Use it when the problem isn't ignorance but hypocrisy.
- **Posts with strong prescriptive endings**: If you have concrete, actionable alternatives to the practices you're indicting, the jeremiad gives those prescriptions maximum force.
- **Posts written from inside the community**: You must be a member of the group you're indicting. An outsider's jeremiad is just criticism.

## When NOT to Use

- **Posts exploring a new idea**: The jeremiad is about return, not discovery. If you're introducing something genuinely novel, use The Montaigne or Converging Ideas.
- **Posts without a clear ideal to invoke**: If the community hasn't articulated the value it's failing to honor, you can't write a jeremiad. You'd need to establish the value first (use Sparkline for that).
- **Posts where the "fall" is someone else's fault**: If you're blaming an outgroup, the jeremiad structure will feel like a hit piece. The indictment must be self-implicating.
- **Lighthearted or humorous posts**: The jeremiad's moral register is heavy. If the topic doesn't warrant prophetic energy, use Comedian's Set or Catch-22 instead.

## Example Mapping

### "Your Metrics Are Bullshit" (Nick's Actual Post)

| Section | Words | Content |
|---------|-------|---------|
| Covenant | ~400 | "You're eight equals working on a product you believe in." The small startup where support worked. |
| Fall | ~800 | The doom cycle: growth → metrics → silence → firing → "do more with less." Recurring motif: "*The queue continues to explode.*" |
| Prophets | ~800 | Goodhart's Law, Campbell's Law, Surrogation — each quoted, translated, applied to support metrics |
| Call | ~1200 | SLIs, SLOs, SLAs — concrete framework with bulleted prescriptions for each level |
| Peroration | ~300 | "Your Support Professionals know what they're doing... Listen to them. Trust them." |

### "We Stopped Doing Code Reviews and Nothing Happened" — A Culture Post

| Section | Words | Content |
|---------|-------|---------|
| Covenant | ~300 | "Code review was supposed to be mentorship. Senior engineers teaching juniors by reading their code, asking questions, sharing context." |
| Fall | ~600 | Reviews became gatekeeping. 48-hour wait times. "LGTM" rubber stamps. Nit-picking style instead of catching logic errors. The mentorship disappeared but the ceremony remained. |
| Prophets | ~400 | Cite research on code review effectiveness (Microsoft study: reviews catch <15% of bugs). Quote the original rationale for code review from Fagan's 1976 paper — show how far we've drifted. |
| Call | ~400 | Replace review gates with pair programming sessions, automated linting, and post-merge review for learning. Specific tooling recommendations. |
| Peroration | ~150 | "The question was never 'should we review code.' It was 'are we willing to do the hard version of mentorship, or just the ceremonial one.'" |

## Combination Notes

- **Jeremiad + Sisyphean Arc**: The fall section of the jeremiad can use the Sisyphean Arc's repetition structure — showing the same failure cycle recurring at increasing scale. The jeremiad adds the moral frame that the Sisyphean Arc lacks: it's not just repetition, it's betrayal.
- **Jeremiad + Sparkline**: The "what could be" beats of the Sparkline can serve as the covenant, interwoven with the fall's "what is." The jeremiad adds prophetic authority and moral urgency to the Sparkline's oscillation.
- **Jeremiad + The Dialectic**: Present the community's defense of its practices as the thesis, the jeremiad's indictment as the antithesis, and the call as the synthesis. This works when you need to engage with "yes, but we have reasons for doing it this way" arguments.
- **Avoid**: Don't combine with Comedian's Set — the moral weight of the jeremiad and the irreverence of comedy work against each other. The reader can't take the indictment seriously if you're also going for laughs.
