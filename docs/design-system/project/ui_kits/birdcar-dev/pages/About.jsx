// About.jsx

function About({ onNav }) {
  return (
    <div className="bc-page-prose">
      <header className="bc-page-head">
        <div className="bc-mono-eyebrow">About</div>
        <h1>A short, honest version.</h1>
      </header>

      <div className="bc-prose">
        <p>
          I'm Nick Cannariato. Birdcar is a nickname I've been called for long enough that
          it's the name on my email, my domain, and most things I sign.
        </p>
        <p>
          I'm a solutions engineer at <a href="https://workos.com" target="_blank" rel="noopener">WorkOS</a>. The job is real engineering — I write
          code, file PRs, and own a corner of the product — but my title has
          "support" in it on purpose. I like sitting next to the customer's
          problem. The best engineers I know all spent some time there.
        </p>
        <p>
          On the side, I take on a small number of consulting projects for
          businesses in and around Fort Worth. The practice is new — I'm being
          deliberately slow about it. Most of my clients are CPAs, mortgage brokers,
          realtors, or operators running businesses between one and twenty million in
          revenue. They have spreadsheets that have gotten too important to be
          spreadsheets. I build the thing that replaces the spreadsheet.
        </p>
        <h2>What I care about</h2>
        <p>
          I care about software that respects the person using it. Most
          small-business software is sold by salespeople to people who don't
          have time to evaluate it. The result is usually fine and rarely good.
          I think a careful person, working closely with the team that will
          actually use the tool, can do much better in eight weeks than a
          procurement process can do in eight months.
        </p>
        <p>
          I write at <a href="#" onClick={(e) => { e.preventDefault(); onNav('writing'); }}>birdcar.dev/writing</a>. I'm,
          against my better judgment, on LinkedIn.
        </p>
        <h2>Where I am</h2>
        <p>Fort Worth, Texas. Central time. I take meetings in person if you're nearby.</p>
      </div>
    </div>
  );
}

window.About = About;
