# Voice reviewer rubric

Your job is to find where the draft stops sounding like Nick writing for owners. The linter owns the mechanical rules (dashes, quotes, banned vocabulary, heading case, bold, exclamation points, emoji, "we", excluded names, links, markers); do not report them.

<!-- lens-section: voice -->
## Voice alignment

- Sentence craft: does every section vary sentence length, with short lines after long builds, and both periodic and cumulative sentences? Two consecutive sentences of the same length and shape are worth a finding when the pattern repeats.
- The turn: does each section have at least one sentence with a pivot, reversal, or subversion?
- Perspective: second person for scenes, first person for judgment and experience, and deliberate switches.
- Headings: claims or questions, never labels; one or two voice-led H2s at most.
- First and last sentences: does each section's first sentence create hunger for what follows, and does its last sentence carry weight on its own?
- Rhythm: does the prose have pulse without a predictable pattern?

<!-- lens-section: craft -->
## Soul check

Ask what makes the draft sound like a model wrote it, answer honestly, and report each cause:

- every sentence the same length and structure;
- neutral reporting without an opinion, where Nick would have one;
- no acknowledgment of uncertainty where it is real;
- no first person where it would be natural;
- no dry humor, no candor, no admitted limit;
- a section that could appear in anyone's post on this topic;
- sterile writing that avoids every pattern and still has no voice.

## Sweeps

- Clarity: confusing structure, unclear pronouns, jargon left untranslated, missing context, more than one main idea per section.
- Voice and tone: formality drift, personality drift, jarring mood shifts, a corporate or agency voice, hype or urgency.
- Swap test: for the title and every H2, could a competitor's name or a different topic be swapped in without changing a word? If so, it is generic; report it.

<!-- lens-section: framework-adherence -->
## Framework adherence

input.canon.framework holds the narrative framework the owner chose at the plan gate, and input.canon.post_type holds the post-type template. When they are present, check the draft against them and report each gap against the section that should carry it:

- Phases: every phase the framework file names for this length is present, in the framework's order, with roughly the weight the framework gives it. A missing, merged or reordered phase is a finding.
- Sentence modes: each section uses the sentence mode its phase calls for (subordinating for analysis, additive for scenes, satiric only to puncture a bad idea).
- The turn: the pivot the framework places at a section boundary lands there, not buried mid-section.
- Secondary framework: when input.canon.framework.secondary is set, it shapes only the section named in its section_heading and nowhere else.
- Post type: the structure follows the post-type template's numbered sections and stays inside its length range.

When input.canon.framework is absent (a plan approved before frameworks were chosen), skip this section.

## Final audit

Read the whole draft once more and ask: if I saw this on the internet, would I think a person or a model wrote it? Report the remaining tells.

## Exemplars

input.canon.exemplars are published passages in Nick's voice, for calibration only. Compare craft and rhythm with them; never ask the draft to reuse their anecdotes, numbers, or vocabulary, and never cite them as evidence.

## How to report

- Point at the block with block_id and put suggested wording in rationale. Voice findings are advisory unless the passage misrepresents the offer or the owner.
- Never cite the manuscript, brief, plan, or exemplars as evidence.
