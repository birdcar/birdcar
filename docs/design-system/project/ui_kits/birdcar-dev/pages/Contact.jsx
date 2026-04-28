// Contact.jsx — email-forward, with a short optional form.

function Contact() {
  const [submitted, setSubmitted] = React.useState(false);
  const [form, setForm] = React.useState({ name: '', email: '', what: '' });
  const update = (k) => (e) => setForm({ ...form, [k]: e.target.value });

  return (
    <div className="bc-page-prose">
      <header className="bc-page-head">
        <div className="bc-mono-eyebrow">Contact</div>
        <h1>Email is the best way.</h1>
      </header>

      <div className="bc-prose">
        <p className="bc-lede">
          The fastest way to reach me is{' '}
          <a href="mailto:hi@birdcar.dev">hi@birdcar.dev</a>. I read everything
          and reply within a few business days.
        </p>
        <p>
          If you'd rather use a form, this one goes to the same inbox. Tell me a
          paragraph or two about what's slow. The more specific you are, the
          more useful my reply will be.
        </p>
      </div>

      {submitted ? (
        <div className="bc-form-ok">
          <div className="bc-mono-eyebrow">Sent</div>
          <h2>Got it. I'll be in touch.</h2>
          <p className="bc-body">If you don't hear back within three business days, email me directly — sometimes things get caught.</p>
        </div>
      ) : (
        <form className="bc-form" onSubmit={(e) => { e.preventDefault(); setSubmitted(true); }}>
          <div className="bc-field-row">
            <label className="bc-field">
              <span className="bc-field-label">Your name</span>
              <input type="text" value={form.name} onChange={update('name')} required />
            </label>
            <label className="bc-field">
              <span className="bc-field-label">Email</span>
              <input type="email" value={form.email} onChange={update('email')} required />
            </label>
          </div>
          <label className="bc-field">
            <span className="bc-field-label">What are you trying to fix?</span>
            <textarea rows="5" value={form.what} onChange={update('what')} required />
          </label>
          <div className="bc-form-actions">
            <Button variant="primary" type="submit">Send</Button>
            <span className="bc-form-or">or just email <a href="mailto:hi@birdcar.dev">hi@birdcar.dev</a></span>
          </div>
        </form>
      )}
    </div>
  );
}

window.Contact = Contact;
