// PostListItem.jsx — one row of the writing index. Mono date, serif title, mono tag.

function PostListItem({ date, title, tag, onClick }) {
  return (
    <a href="#" className="bc-post-row" onClick={(e) => { e.preventDefault(); onClick && onClick(); }}>
      <span className="bc-post-date">{date}</span>
      <span className="bc-post-title">{title}</span>
      <span className="bc-post-tag">{tag}</span>
    </a>
  );
}

window.PostListItem = PostListItem;
