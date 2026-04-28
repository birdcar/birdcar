// Writing.jsx — sectioned blog index.

const SECTIONS = [
  {
    name: 'Consulting & local business',
    posts: [
      { date: '2026-01-30', title: 'A small CRM is fine, actually', tag: 'consulting' },
      { date: '2026-01-09', title: 'What I tell people who want to automate everything', tag: 'consulting' },
      { date: '2025-11-18', title: 'Discovery, but two weeks of it', tag: 'consulting' },
      { date: '2025-09-04', title: 'Why I price by the project', tag: 'consulting' },
    ],
  },
  {
    name: 'Tax-tools & internal apps',
    posts: [
      { date: '2026-03-14', title: 'Notes on Drake export, again', tag: 'tax-tools' },
      { date: '2025-12-22', title: 'Building a review queue in a weekend', tag: 'tax-tools' },
      { date: '2025-10-12', title: 'CSV is a serious format', tag: 'tax-tools' },
    ],
  },
  {
    name: 'Solutions engineering & WorkOS',
    posts: [
      { date: '2026-02-22', title: 'Support engineering, slowly', tag: 'work' },
      { date: '2025-12-02', title: 'The bug report I would actually want', tag: 'work' },
      { date: '2025-08-19', title: 'On reading other people\'s code in anger', tag: 'work' },
    ],
  },
  {
    name: 'AI, tooling, and the small stuff',
    posts: [
      { date: '2026-02-05', title: 'LLMs as the world\'s most patient junior', tag: 'ai' },
      { date: '2025-11-02', title: 'A short list of tools I keep paying for', tag: 'tooling' },
    ],
  },
];

const ALL_TAGS = ['all', 'consulting', 'tax-tools', 'work', 'ai', 'tooling'];

function Writing({ onNav }) {
  const [filter, setFilter] = React.useState('all');
  const filtered = SECTIONS
    .map(s => ({ ...s, posts: s.posts.filter(p => filter === 'all' || p.tag === filter) }))
    .filter(s => s.posts.length > 0);

  return (
    <div className="bc-page">
      <header className="bc-page-head">
        <div className="bc-mono-eyebrow">Writing</div>
        <h1>Posts, sectioned.</h1>
        <p className="bc-lede">
          I write about consulting work, the tools I build, and the stranger
          corners of being a solutions engineer. Filter by tag if you want only
          one of those.
        </p>
      </header>

      <div className="bc-filterbar">
        {ALL_TAGS.map(t => (
          <button
            key={t}
            className={"bc-filter" + (filter === t ? ' is-active' : '')}
            onClick={() => setFilter(t)}
          >
            {t}
          </button>
        ))}
      </div>

      <div className="bc-sections">
        {filtered.map(s => (
          <section key={s.name} className="bc-blog-section">
            <h2 className="bc-section-name">{s.name}</h2>
            <div className="bc-postlist">
              {s.posts.map(p => (
                <PostListItem key={p.date} {...p} onClick={() => onNav('post')} />
              ))}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
}

window.Writing = Writing;
