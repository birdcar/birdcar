# Implementation Spec: Walkthrough Offer Copy and Funnel Signals - Phase 3

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Phase 3 is the copy. It closes the gap both frameworks found: the pages describe the reader's pain but never the after-state or the cost of leaving things alone, and they carry almost no proof. Every change below is a specific sentence in one of three templates: `resources/views/pages/index.blade.php`, `resources/views/pages/walkthrough.blade.php` (moved in Phase 2), and `resources/views/components/marketing/assessment-invitation.blade.php`. Two Pest tests make the reader-focus goal mechanical: a ratio test (you/your/yours words at least equal to I/me/my words on both pages) and a question-count test (the homepage asks at least two questions). Anchor assertions lock every commitment so none can be dropped silently.

Voice rules from `PRODUCT.md` Written Voice apply throughout: first-person singular (I, me, my), never corporate "we"; contractions and ordinary words; recognisable working scenes; no hype, no urgency vocabulary, no invented numbers. The only numbers allowed are "fifteen years" and "three business days". Reader focus comes from sentence subjects, not from abandoning first person: "You'll have the report within three business days" rather than "I'll send the report".

The phase ends by updating the records that would otherwise cause the next agent to revert the copy: `PRODUCT.md` (Approved Launch Copy and Proof, Open Decisions) and `.ai/rules/resources.md` (Approved marketing launch scope). Use the Laravel Boost `record-rule` tool for the rule file when it is available; otherwise edit the file directly, preserving its frontmatter.

## Decisions Considered and Rejected

_Carried from the contract; consult before making gap decisions._

- **Urgency is consequence copy only: another hire, another subscription, another hour of your evening** — rejected: publishing the real client capacity ceiling, and inventing a booking window. PRODUCT.md keeps capacity internal and forbids invented scarcity.
- **Commit to all four decision-gated copy elements: no-pitch promise, report turnaround, fit disqualifier, past employer names** — rejected: writing the copy around the open decisions.
- **Report turnaround of three business days** — rejected: within one week. The owner accepts the workload risk.
- **One sentence about the paid discovery week, without a price, in the after-report FAQ answer** — rejected: keeping it off the site, and a priced section.
- **Implement the reader-focus ratio and reader-question count as Pest tests** — they keep running after this project.
- **Rename the report's third component to Where I'd start** — rejected: keeping Something to work from, which restated the report.
- **Name the offer The Walkthrough** — Phase 2 did the rename; this phase must not reintroduce "assessment" as the offer's name in prose.
- **Employer names on the homepage personal note: GitHub, Heroku, and Zapier, with WorkOS deliberately omitted** — the owner is employed at WorkOS while building this business.

## Feedback Strategy

**Inner-loop command**: `php artisan test --compact tests/Feature/MarketingSiteTest.php`

**Playground**: Test suite, with the two new tests written before any copy changes so the ratio and question count are visible on every run. `php artisan dev` for a final visual read of the three pages.

**Why this approach**: The copy has fourteen anchor strings and two computed metrics. Pest renders both pages in about a second and prints the failing anchor or the ratio numbers, so the loop is "edit a sentence, run, read the counts".

## File Changes

### New Files

None.

### Modified Files

| File Path | Changes |
| --- | --- |
| `resources/views/pages/index.blade.php` | Hero explanation, `assessment-terms` note, assessment strip (two questions, turnaround), approach intro and three steps, personal note |
| `resources/views/pages/walkthrough.blade.php` | Opening (mechanism, reader-subject paragraph, no-pitch line, action note), report aside (Where I'd start), outcome section (consequence, solution question), steps two and three, FAQ (four rewritten answers, one new entry), closing |
| `resources/views/components/marketing/assessment-invitation.blade.php` | No-pitch sentence, action note with turnaround |
| `tests/Feature/MarketingSiteTest.php` | Anchor assertions; `speaks to the reader` ratio test; `asks the reader` question test |
| `PRODUCT.md` | Approved Launch Copy and Proof additions; Open Decisions pruned |
| `.ai/rules/resources.md` | Approved marketing launch scope rule updated |

### Deleted Files

None.

## Implementation Details

### Tests first

**Pattern to follow**: `tests/Feature/MarketingSiteTest.php` (plain `test()` functions, `assertSee` with the template's curly apostrophes).

```php
function marketingProse(string $html): string
{
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $html);

    return mb_strtolower(str_replace('’', "'", strip_tags($html)));
}

test('the conversion pages speak to the reader at least as much as about me', function (string $path) {
    $prose = marketingProse($this->get($path)->assertOk()->getContent());
    $reader = preg_match_all("/\\b(you|your|yours|you're|you'll|you'd|you've)\\b/u", $prose);
    $me = preg_match_all("/\\b(i|i'll|i've|i'd|i'm|me|my)\\b/u", $prose);

    expect($reader)->toBeGreaterThanOrEqual($me, "reader words {$reader} vs first-person words {$me} on {$path}");
})->with(['/', '/walkthrough']);

test('the homepage asks the reader at least two questions', function () {
    preg_match('#<main[^>]*>(.*)</main>#s', $this->get('/')->assertOk()->getContent(), $main);

    expect(substr_count(strip_tags($main[1]), '?'))->toBeGreaterThanOrEqual(2);
});
```

The test names must contain `speaks to the reader` and `asks the reader`; the contract's acceptance commands filter on those phrases. Scripts and styles are stripped because JSON-LD descriptions are metadata, not copy. FAQ questions written in the visitor's voice ("Do I need to know what I want built?") count against the ratio on purpose; the copy below is written to pass with them included.

Anchor assertions, added to the existing homepage and walkthrough tests (curly apostrophes as in the templates):

| Page | Anchor |
| --- | --- |
| `/` | `another hire, another subscription, or another hour of your evening` |
| `/` | `if the report assembled itself` |
| `/` | `GitHub, Heroku, and Zapier` |
| `/` | `within three business days` |
| `/walkthrough` | `I start with the people doing the work` |
| `/walkthrough` | `you’ll bring it up, not me` |
| `/walkthrough` | `within three business days` |
| `/walkthrough` | `I’m not the right fit` |
| `/walkthrough` | `the shape of the problem and where I’d look first` |
| `/walkthrough` | `paid discovery week` |
| `/walkthrough` | `Where I’d start` |
| `/walkthrough` | `the expensive version of standing still` |
| `/walkthrough` | `if the reminders sent themselves` |
| `/walkthrough` | `room for work that’s worth their time` |
| `/walkthrough` | `The conversation and the written report are free.` (existing, keep) |
| `/walkthrough` | `separate implementation engagement` (existing, keep) |
| `/` | `I help businesses untangle work`, `fifteen years` (existing, keep); `assertDontSee('more than fifteen years')`, `GHX`, `DataDash` (existing, keep) |

**Feedback loop**:

- **Playground**: the two new tests plus the anchor list, all failing before copy changes.
- **Experiment**: run once before edits and note the printed counts (expected today: homepage roughly 12 reader vs 16 first-person, walkthrough roughly 30 vs 33); after each section rewrite, run again and watch both numbers.
- **Check command**: the inner-loop command.

### Homepage copy

Replace the current sentences with these. Keep the surrounding markup, classes, and ids.

**Hero explanation** (`.hero-explanation`):
> You and the people doing the work show me where it gets stuck. I build the tools and processes that make it easier for you to handle.

**Hero action note** (`.assessment-terms`):
> About an hour. A free Walkthrough. A written report within three business days.

**Assessment strip** (heading `Let’s start with what’s painful.` stays). Three paragraphs:
> The reporting someone spends Friday assembling. The follow-up that depends on your memory. The process that keeps landing back on your desk.
>
> What does it cost when the only fix is another hire, another subscription, or another hour of your evening? And what would the week look like if the report assembled itself and the follow-up went out without you?
>
> I’ll spend about an hour with you, then write up what I’ve understood and the improvements I recommend. The Walkthrough is free. You’ll have the report within three business days, and it’s yours to keep.

**Approach introduction** (heading stays):
> A process can look perfectly reasonable until you ask someone to walk you through their Tuesday. Then you find the spreadsheet they keep open, the information they copy between tools, and the things they check because nobody quite trusts the system.
>
> That’s where the Walkthrough starts. Before suggesting an improvement, I need to understand what your people are already doing to keep things working.

**Understand**:
> You and your team walk me through the work. I follow it through the business and look for where it gets difficult. Together, you and I establish what an improvement would actually mean for the people involved.

**Build**:
> You and I agree on a manageable scope. I build the improvement and test it with the people who’ll use it. That might mean connecting the tools you already have, building something specific to your business, or changing how the work moves between people.

**Care**:
> You get a working system, documentation, and training. When continued help makes sense, I stay involved as your people learn the system and the business changes.

**Personal note** (heading stays):
> I’ve spent fifteen years working on customer-facing technical systems, including internal tools, solutions engineering, and building a software business. Some of that work was for GitHub, Heroku, and Zapier.
>
> I like making complicated things understandable, especially when that understanding makes someone’s working day better.

**Key decisions**:

- "Together, you and I" rather than "we": PRODUCT.md forbids corporate we; naming both parties keeps it collaboration.
- The employer sentence is factual background ("Some of that work was for"), not endorsement, matching PRODUCT.md's constraint on those associations.
- Both strip questions are NEPQ moves: the first is a consequence question, the second solution-awareness. They are questions, not claims, so they promise nothing.

**Feedback loop**: the tests-first loop above; the homepage ratio should land around 20 reader to 18 first-person. If it fails, convert another approach-step sentence to a "you" subject before touching the hero.

### Walkthrough page copy

**Opening** (`.offer-copy`; H1 and lead unchanged):
> When you’re the person keeping track of everything, it’s hard to step back far enough to see what needs to change. I’ll take a closer look with you. I start with the people doing the work, not with software.
>
> In a free Walkthrough, you spend about an hour showing me the work that’s giving you trouble. Afterward, you get the problems I’ve understood and the improvements I recommend, in writing, within three business days.
>
> The hour is about your work. If you want to talk about hiring me, you’ll bring it up, not me.

Button text stays `Book a free Walkthrough` (Phase 2). Action note:
> About an hour with me. A written report within three business days. Free.

**Report aside**: heading and label stay. Definition list:
> **What’s happening** — The problems in your work as I’ve understood them.
> **What I recommend** — Improvements worth considering for your business.
> **Where I’d start** — The first change I’d make for you, and why it comes first.

`Yours to keep.` sign-off stays.

**Outcome section** (heading `Bring the problem. I’ll bring the questions.` stays):
> Maybe onboarding a new client involves a dozen reminders. Maybe reporting eats an afternoon. Maybe the process works perfectly, as long as you’re there to keep it working. Left alone, that kind of work has one fix: another coordinator, another subscription, or another late night. That’s the expensive version of standing still.
>
> What would change if the reminders sent themselves and the report was ready before you asked for it? You don’t need to diagnose it first. Pick an example of work that’s harder than it ought to be, and I’ll help you look at what’s going on.

**Steps** (step one unchanged):
> **Walk me through it.** You walk me through how the work happens, who’s involved, and where it gets difficult. I ask questions until I understand the problem in its context.
>
> **Keep the report.** After the conversation, you get my observations and recommendations in writing, within three business days, to use on your own or with my help.

**FAQ** (heading `A few fair questions.` stays). Summaries and answers:

| Summary | Answer |
| --- | --- |
| Is the Walkthrough really free? | Yes. The conversation and the written report are free. You don’t have to buy implementation to receive or use the report, and I won’t raise it unless you do. |
| Do I need to know what I want built? | No. Show me what’s painful and how it works today; you’ll leave with a clearer view of what would make it better. |
| Is this a fit for my business? | If you have customers, people doing the work, and a process that takes too much chasing, copying, or remembering, there’s something useful to look at. The operational problem matters more than the industry. One condition: this works when I can talk to the people doing the work. If that isn’t possible, I’m not the right fit. |
| What happens after I get the report? | You decide what to do with it. If you’d like my help, I can discuss a separate implementation engagement with you. The free hour is also the first step of the same process I run as a paid discovery week, for businesses that want a deeper look before deciding. You can also use the recommendations yourself or leave it there. |
| What can an hour actually tell you? | Enough to see the shape of the problem and where I’d look first. That’s what you get in the report: what’s happening, what I recommend, and where I’d start. When something needs a closer look, the report says so. |
| Is this an AI project? | It’s a Walkthrough of your work. AI might be useful, and so might connecting the tools you already use or changing a process. I recommend what makes sense for the problem. |
| Is this about replacing my team? *(new, last)* | No. The point is to give the people you already have room for work that’s worth their time. That rules out surveillance, and projects whose whole purpose is cutting people regardless of the consequences. |

**Closing** (heading stays):
> Pick an hour that suits you. A conversation with me, then practical recommendations in writing, within three business days.<br>Yours to use however you choose.

**Key decisions**:

- The no-pitch promise appears twice with different wording: the anchor sentence before the CTA and the softer "I won't raise it unless you do" in the FAQ.
- The replacing-people answer is drawn from PRODUCT.md's Product Principles (exclude surveillance and pure headcount-cutting projects). It uses "that rules out" rather than two "I don't" clauses to protect the ratio.
- The reframed hour answer leads with what the hour delivers and keeps the honest caveat last.
- The paid-week sentence names no price, no duration beyond "week", and no report boundary.

**Feedback loop**: the tests-first loop. Expected landing: about 41 reader words to 37 first-person on `/walkthrough`. If it fails, the first candidates to flip are step two ("I ask questions until I understand" → "you get questions until the problem makes sense in its context") and the fit answer's second sentence.

### Closing invitation component

> **Show me the part that keeps getting stuck.** *(heading default stays; the Work page passes its own)*
>
> You don’t need to arrive with a software specification. An example of something that’s harder than it ought to be is a useful place to start. The hour is about your work, not a pitch.

Action note:
> About an hour. A written report within three business days. Yours to keep.

Trivial component; covered by the page tests.

### Records

**PRODUCT.md, section "Approved Launch Copy and Proof"**: append these bullets.

- The offer is named The Walkthrough. It lives at `/walkthrough` (`/assessment` and `/contact` redirect permanently) and books through `https://cal.com/birdcar/walkthrough`, namespace `walkthrough`.
- The written report is promised within three business days of the conversation.
- The hour is promised pitch-free: "If you want to talk about hiring me, you'll bring it up, not me." Honour it on every call.
- Fit disqualifier: the Walkthrough works when the owner can talk to the people doing the work; otherwise "I'm not the right fit."
- Past employers GitHub, Heroku, and Zapier may be named as background. WorkOS stays off the public site while the owner is employed there.
- The paid discovery week may be mentioned in one sentence with no price, as the deeper form of the same process.
- The report includes a "Where I'd start" section: the first change and why it comes first.
- Urgency is consequence copy only. No scarcity, capacity, or deadline language.

**PRODUCT.md, section "Open Decisions"**: remove the free report's turnaround and the offer name from the first two bullets (both decided); keep the paid report's boundaries, prices, guarantees, retainer, and the rest.

**.ai/rules/resources.md, "Approved marketing launch scope"**: replace the sentence beginning `Assessment booking uses` with:

> The Walkthrough (the renamed free assessment) lives at /walkthrough with permanent redirects from /assessment and /contact, books through https://cal.com/birdcar/walkthrough, and promises a written report within three business days and a pitch-free hour; name past employers GitHub, Heroku, and Zapier only; the paid discovery week gets one unpriced sentence; no invented scarcity, results, or VSL.

Prefer the Boost `record-rule` tool (glob `resources/**`, title matching the existing heading). If unavailable, edit the file and keep the frontmatter `paths` intact.

Trivial component; verify with `grep -n 'three business days' PRODUCT.md .ai/rules/resources.md`.

## Testing Requirements

### Feature Tests

| Test File | Coverage |
| --- | --- |
| `tests/Feature/MarketingSiteTest.php` | Fourteen anchors; reader-focus ratio on two pages; homepage question count; existing constraint assertions retained |

**Key test cases**: listed under Tests first.

### Manual Testing

- [ ] Read `/`, `/walkthrough`, and the invitation on `/work` in the browser against the seven copy-editing sweeps (clarity, voice and tone, so what, prove it, specificity, heightened emotion, zero risk) and PRODUCT.md Written Voice. This is the contract's judgment criterion; the owner performs it at the gate.
- [ ] Confirm no sentence uses corporate "we".

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
| --- | --- | --- | --- | --- |
| Ratio test | Passes for the wrong reason | Adding filler "you" phrases | Copy degrades | Only flip sentence subjects from the lists above; re-read aloud |
| Ratio test | Fails after Phase 2 strings | Nav "How I work" ×2 counted | Two extra first-person words | Accounted for in the expected counts; do not rename the nav |
| Anchor tests | Apostrophe mismatch | Straight `'` in test, curly `’` in template | False failure | Copy anchors from the template; the ratio helper normalises, the anchor assertions do not |
| Question test | Counts a `?` outside copy | A `?` in an attribute value inside `<main>` | False pass | `strip_tags` removes attributes; no query strings appear in main today |
| Voice | Hype leaks in | Reaching for intensifiers to "heighten emotion" | Violates PRODUCT.md | The consequence and solution-awareness lines are questions and plain nouns, no adjectives |
| Records | `record-rule` unavailable to the executor | Boost MCP not connected | Rule file drifts | Direct edit with frontmatter preserved is the documented fallback |

## Validation Commands

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact tests/Feature/MarketingSiteTest.php tests/Feature/MarketingDiscoveryTest.php tests/Feature/MarketingAnalyticsTest.php
php artisan test --compact tests/Feature/MarketingSiteTest.php --filter='speaks to the reader'
php artisan test --compact tests/Feature/MarketingSiteTest.php --filter='asks the reader'
! grep -rEil 'limited (spots|places|slots|availability)|only [0-9]+ (spots|clients|places|slots)|(few|handful of) (spots|places|slots)|offer ends|book before|closes on|spots? left|this week only|while (I|there is|there.s) (still )?(have )?room|before (the|this) (month|week|quarter) (ends|is out)' resources/views/pages resources/views/components/marketing
grep -n 'three business days' PRODUCT.md .ai/rules/resources.md
```

## Rollout Considerations

- **Feature flag**: none. Ships with Phases 1 and 2 in one release at the gate.
- **Monitoring**: booking rate over the following 30 days in the PostHog funnel insight is the trailing metric, not a gate.
- **Rollback plan**: revert the phase commit; the records changes revert with it.
