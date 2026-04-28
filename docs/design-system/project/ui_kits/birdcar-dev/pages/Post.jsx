// Post.jsx — a single long-form blog post.

function Post({ onNav }) {
  return (
    <article className="bc-page-prose">
      <header className="bc-post-head">
        <div className="bc-mono-eyebrow">2026-03-14 · tax-tools · 6 min read</div>
        <h1>Notes on Drake export, again</h1>
        <p className="bc-lede">
          A short follow-up to last year's post, after a second tax season of
          watching a CPA firm wrestle one specific export into a shape that's
          actually reviewable.
        </p>
      </header>

      <div className="bc-prose">
        <p>
          Last March I wrote about a small tool I built for a CPA firm here in
          town. The shape of it: pull eighty-or-so returns out of Drake at the
          end of each evening, normalize them, and present them to the senior
          reviewer as a single queue with everything they need to either approve
          or send back.
        </p>
        <p>
          A year later the tool still works. It has been used for two seasons.
          It has not been rewritten. I want to write down the specific things
          that, in retrospect, made it survive.
        </p>
        <h2>Export is harder than the import</h2>
        <p>
          The temptation, when you're building one of these tools, is to put all
          of your effort into the part where the data comes in. That's the part
          that feels engineering-shaped. But the part that actually changes
          whether the tool gets used is the export — the moment the reviewer
          stamps the return and the data has to land back somewhere everyone
          else trusts.
        </p>
        <blockquote>
          Most of the time, "everyone else trusts" means a specific column in a
          specific spreadsheet, and the price of getting it slightly wrong is
          someone hand-editing eighty rows on a Friday.
        </blockquote>
        <p>
          I now spend roughly half of any small-tool budget on the export
          surface. CSV download. A button that copies a row. A weekly email with
          the day's stamps as a clean attachment. The boring half.
        </p>
        <h2>Code, briefly</h2>
        <pre><code>{`// the entire export endpoint, more or less
app.get('/exports/daily.csv', requireAuth, async (req, res) => {
  const rows = await db.query(REVIEWED_TODAY);
  res.setHeader('Content-Type', 'text/csv');
  res.setHeader('Content-Disposition',
    \`attachment; filename="reviews-\${today()}.csv"\`);
  res.send(toCsv(rows, COLUMNS));
});`}</code></pre>
        <p>
          That's the whole thing. The cleverness, such as it is, lives in
          <code>COLUMNS</code> — a hand-tuned list that took two seasons of feedback
          to get right.
        </p>
        <h2>What I'd change</h2>
        <p>
          Almost nothing. Which is the report I want to be writing about a
          two-year-old internal tool. The one thing I'd do differently is
          version the column order — once, near the top of the file, with a
          comment explaining why each column is where it is.
        </p>

        <hr className="bc-hr" />
        <p className="bc-mono-eyebrow">
          ← <a href="#" onClick={(e) => { e.preventDefault(); onNav('writing'); }}>back to writing</a>
        </p>
      </div>
    </article>
  );
}

window.Post = Post;
