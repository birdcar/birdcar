// Work.jsx — kinds of systems Birdcar builds, framed as problem patterns.

const PATTERNS = [
  {
    title: 'A review queue that replaces a spreadsheet',
    body: 'You have one or two senior people approving work — returns, invoices, draft contracts — and the queue lives in a shared spreadsheet that everyone fights over. I build a small web app where the work shows up, gets reviewed, and gets stamped. The spreadsheet goes away.',
    example: 'CPA firm, ~80 returns / week. Built in Next.js + Postgres, hosted on a small VPS.',
  },
  {
    title: 'A glue layer between two systems that almost talk',
    body: 'Your CRM and your accounting system don\'t share customer data. Or your scheduling tool and your payroll system. Someone is copy-pasting twice a week. I build a small service that watches one and writes to the other, with a simple admin page so you can see what\'s happening.',
    example: 'Mortgage brokerage, LOS → custom referral dashboard. ~12 hours / week of copy-paste removed.',
  },
  {
    title: 'An internal tool that shouldn\'t require a developer to change',
    body: 'You have a process that changes — pricing, fee tiers, who reviews what — and right now changing it means emailing the developer. I build internal tools with a thin admin surface, so the people who run the business can change the rules without writing a ticket.',
    example: 'Property-management firm, fee structure editable by ops lead.',
  },
  {
    title: 'A reporting page that actually answers the question',
    body: 'You\'re paying for a BI tool and nobody opens it. The reports that matter live in five tabs of a spreadsheet that one person updates manually. I build a single page that shows the few numbers that actually drive decisions, refreshed automatically, and nothing else.',
    example: 'Local agency, weekly Monday-morning dashboard.',
  },
];

function Work({ onNav }) {
  return (
    <div className="bc-page">
      <header className="bc-page-head">
        <div className="bc-mono-eyebrow">Work</div>
        <h1>The kinds of things I build.</h1>
        <p className="bc-lede">
          The practice is new, so I don't have a long list of case studies yet.
          Instead, here are the problem patterns I'm set up to solve well. If one
          of these sounds like your week, we should talk.
        </p>
      </header>

      <div className="bc-patterns">
        {PATTERNS.map(p => (
          <article key={p.title} className="bc-pattern">
            <h2>{p.title}</h2>
            <p className="bc-body">{p.body}</p>
            <div className="bc-pattern-example">
              <span className="bc-mono">Example</span>
              <span>{p.example}</span>
            </div>
          </article>
        ))}
      </div>

      <hr className="bc-hr" />

      <section className="bc-section bc-cta">
        <h2>Sound like something you have?</h2>
        <p className="bc-body">Email me a paragraph or two about what's slow. I'll tell you honestly whether I can help.</p>
        <div className="bc-cta-row">
          <Button variant="primary" onClick={() => onNav('contact')}>Email me</Button>
        </div>
      </section>
    </div>
  );
}

window.Work = Work;
