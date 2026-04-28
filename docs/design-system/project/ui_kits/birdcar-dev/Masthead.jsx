// Masthead.jsx — left-aligned brand + sentence-case nav, hairline rule below.

const NAV = [
  { id: 'home',    label: 'Home' },
  { id: 'about',   label: 'About' },
  { id: 'work',    label: 'Work' },
  { id: 'writing', label: 'Writing' },
  { id: 'contact', label: 'Contact' },
];

function Masthead({ current, onNav }) {
  return (
    <header className="bc-mast">
      <a href="#" className="bc-brand" onClick={(e) => { e.preventDefault(); onNav('home'); }}>
        Birdcar<span className="bc-brand-dot">.</span>
      </a>
      <nav className="bc-nav">
        {NAV.map(item => (
          <a
            key={item.id}
            href="#"
            className={"bc-nav-link" + (current === item.id ? ' is-current' : '')}
            onClick={(e) => { e.preventDefault(); onNav(item.id); }}
          >
            {item.label}
          </a>
        ))}
      </nav>
    </header>
  );
}

window.Masthead = Masthead;
