// Home.jsx
const POSTS = [
  { date: '2026-03-14', title: 'Notes on Drake export, again', tag: 'tax-tools' },
  { date: '2026-02-22', title: 'Support engineering, slowly', tag: 'work' },
  { date: '2026-01-30', title: 'A small CRM is fine, actually', tag: 'consulting' },
  { date: '2026-01-09', title: 'What I tell people who want to automate everything', tag: 'consulting' },
];

const CLIENT_TYPES = ['CPA firms', 'Mortgage brokers', 'Realtors', 'Property mgmt', 'Local agencies'];

function Home({ onNav }) {
  return (
    <div className="bc-page-home">
      <section className="bc-hero">
        <div className="bc-hero-grid">
          <div className="bc-hero-text">
            <h1 className="bc-display">
              I build internal tools and automations for small businesses in Fort Worth.
            </h1>
            <p className="bc-lede">
              I'm Nick Cannariato. By day I'm a solutions engineer at <a href="https://workos.com" target="_blank" rel="noopener">WorkOS</a>.
              On the side I take on a small number of consulting projects for
              local firms — usually CPAs, mortgage brokers, realtors, and operators in the
              $1–20M range — building the kind of internal tools that turn a
              wall of sticky notes into a single review queue.
            </p>
            <div className="bc-trust-strip">
              <span className="bc-trust-label">I work with</span>
              <span className="bc-trust-tags">
                {CLIENT_TYPES.map((c, i) => (
                  <React.Fragment key={c}>
                    {i > 0 && <span className="bc-trust-sep">·</span>}
                    <span>{c}</span>
                  </React.Fragment>
                ))}
              </span>
            </div>
          </div>
          <aside className="bc-hero-aside">
            <div className="bc-portrait">
              <div className="bc-portrait-frame">
                <div className="bc-portrait-fallback" aria-hidden="true">
                  <span className="bc-portrait-initials">NC</span>
                </div>
                <img
                  src="https://github.com/birdcar.png"
                  alt="Nick Cannariato"
                  className="bc-portrait-img"
                  loading="lazy"
                  onError={(e) => { e.currentTarget.style.display = 'none'; }}
                />
              </div>
              <div className="bc-portrait-cap">Nick Cannariato · Fort Worth, TX</div>
              <div className="bc-portrait-links">
                <a href="https://github.com/birdcar" target="_blank" rel="noopener">GitHub</a>
                <span aria-hidden="true">·</span>
                <a href="https://linkedin.com/in/birdcar" target="_blank" rel="noopener">LinkedIn</a>
              </div>
            </div>
            <div className="bc-now">
              <div className="bc-now-label">Now</div>
              <p>Wrapping a review queue for a Fort Worth CPA firm. Taking new project conversations for late spring.</p>
              <div className="bc-now-stamp">Updated 2026-04-12</div>
            </div>
          </aside>
        </div>
      </section>

      <hr className="bc-hr" />

      <section className="bc-section">
        <h2>How I work with clients</h2>
        <div className="bc-twocol">
          <div>
            <p className="bc-body">
              Project-based. Four to twelve weeks. Fixed scope, fixed price.
              I write the proposal; you approve it; I build the thing.
            </p>
            <p className="bc-body">
              Most engagements start with a paid two-week discovery — I sit with
              your team, learn the actual workflow, and write a short document
              describing what I'd build and what it would cost. If the numbers
              don't work, we stop there and you keep the document.
            </p>
          </div>
          <div className="bc-aside">
            <div className="bc-aside-row"><span className="bc-mono">Engagement</span><span>Project, fixed price</span></div>
            <div className="bc-aside-row"><span className="bc-mono">Length</span><span>4–12 weeks</span></div>
            <div className="bc-aside-row"><span className="bc-mono">Discovery</span><span>2 weeks, paid</span></div>
            <div className="bc-aside-row"><span className="bc-mono">Capacity</span><span>~3 clients / year</span></div>
          </div>
        </div>
      </section>

      <hr className="bc-hr" />

      <section className="bc-section">
        <div className="bc-section-head">
          <h2>Recent writing</h2>
          <a href="#" className="bc-link-arrow" onClick={(e) => { e.preventDefault(); onNav('writing'); }}>All writing ↗</a>
        </div>
        <div className="bc-postlist">
          {POSTS.map(p => (
            <PostListItem key={p.date} {...p} onClick={() => onNav('post')} />
          ))}
        </div>
      </section>

      <hr className="bc-hr" />

      <section className="bc-section bc-cta">
        <h2>Working on something?</h2>
        <p className="bc-body">
          If you've got a workflow that's mostly held together with spreadsheets
          and goodwill, I'd like to hear about it. Email is the best way to reach me.
        </p>
        <div className="bc-cta-row">
          <Button variant="primary" onClick={() => onNav('contact')}>Email me</Button>
          <Button variant="secondary" onClick={() => onNav('work')}>See the kinds of things I build</Button>
        </div>
      </section>
    </div>
  );
}

window.Home = Home;
