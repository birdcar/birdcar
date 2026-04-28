// Footer.jsx — double-rule, plain text, small lucide icons.

function Footer({ onNav }) {
  return (
    <footer className="bc-footer">
      <div className="bc-rule-double" />
      <div className="bc-footer-row">
        <div className="bc-footer-col">
          <div className="bc-footer-brand">Birdcar</div>
          <div className="bc-footer-tag">Fort Worth · TX</div>
        </div>
        <div className="bc-footer-col">
          <a href="mailto:hi@birdcar.dev" className="bc-footer-link">hi@birdcar.dev</a>
          <a href="#" className="bc-footer-link" onClick={(e) => { e.preventDefault(); onNav('writing'); }}>Subscribe via RSS</a>
        </div>
        <div className="bc-footer-col bc-footer-meta">
          <div>© 2026 Birdcar</div>
          <div>Built carefully, left alone.</div>
        </div>
      </div>
    </footer>
  );
}

window.Footer = Footer;
