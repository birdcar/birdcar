// Button.jsx — primary (ink-filled), secondary (outline), link.

function Button({ variant = 'primary', children, onClick, href, type }) {
  const cls = 'bc-btn bc-btn-' + variant;
  if (href) {
    return <a href={href} className={cls} onClick={onClick}>{children}</a>;
  }
  return <button type={type || 'button'} className={cls} onClick={onClick}>{children}</button>;
}

window.Button = Button;
