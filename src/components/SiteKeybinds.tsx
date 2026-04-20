import { useEffect, useMemo, useRef, useState } from "react";
import { AnimatePresence, motion } from "framer-motion";

interface Entry {
  id: string;
  title: string;
  description: string;
  tags: string[];
  date: string;
}

function matches(entry: Entry, query: string): number {
  if (!query) return 0;
  const q = query.toLowerCase();
  const haystacks = [
    { text: entry.title.toLowerCase(), weight: 3 },
    { text: entry.description.toLowerCase(), weight: 1 },
    { text: entry.tags.join(" ").toLowerCase(), weight: 2 },
  ];
  let score = 0;
  for (const { text, weight } of haystacks) {
    if (text.includes(q)) score += weight;
  }
  return score;
}

function isTypingTarget(el: EventTarget | null): boolean {
  if (!(el instanceof HTMLElement)) return false;
  const tag = el.tagName;
  return (
    tag === "INPUT" ||
    tag === "TEXTAREA" ||
    tag === "SELECT" ||
    el.isContentEditable
  );
}

export default function SiteKeybinds() {
  const [isOpen, setIsOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [activeIndex, setActiveIndex] = useState(0);
  const [entries, setEntries] = useState<Entry[] | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Lazy-load the index the first time the user triggers the modal
  useEffect(() => {
    if (!isOpen || entries !== null) return;
    let cancelled = false;
    fetch("/search-index.json")
      .then((res) => res.json())
      .then((data: Entry[]) => {
        if (!cancelled) setEntries(data);
      })
      .catch(() => {
        if (!cancelled) setEntries([]);
      });
    return () => {
      cancelled = true;
    };
  }, [isOpen, entries]);

  const results = useMemo(() => {
    if (!entries) return [];
    if (!query.trim()) return entries.slice(0, 8);
    return entries
      .map((e) => ({ entry: e, score: matches(e, query.trim()) }))
      .filter(({ score }) => score > 0)
      .sort((a, b) => b.score - a.score)
      .slice(0, 8)
      .map(({ entry }) => entry);
  }, [entries, query]);

  useEffect(() => {
    setActiveIndex(0);
  }, [query, isOpen]);

  // Imperative open via custom event (used by mobile nav + 404 page)
  useEffect(() => {
    const onOpen = () => setIsOpen(true);
    document.addEventListener("birdcar:open-search", onOpen);
    return () => document.removeEventListener("birdcar:open-search", onOpen);
  }, []);

  // Global shortcuts
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      const cmdK = (e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k";
      const slash = e.key === "/" && !isTypingTarget(e.target);
      if (cmdK || slash) {
        e.preventDefault();
        setIsOpen(true);
        return;
      }
      if (e.key === "Escape" && isOpen) {
        e.preventDefault();
        setIsOpen(false);
      }

      // j/k on blog pages for next/prev post card focus
      if (
        !isOpen &&
        !isTypingTarget(e.target) &&
        (e.key === "j" || e.key === "k" || e.key === "J" || e.key === "K")
      ) {
        const path = window.location.pathname;
        if (!path.startsWith("/blog") && !path.startsWith("/tags")) return;
        const cards = Array.from(
          document.querySelectorAll<HTMLAnchorElement>(".post-card__link"),
        );
        if (!cards.length) return;
        e.preventDefault();
        const active = document.activeElement as HTMLElement | null;
        const currentIdx = cards.findIndex((c) => c === active);
        const delta = e.key === "j" || e.key === "J" ? 1 : -1;
        const nextIdx =
          currentIdx === -1
            ? delta > 0
              ? 0
              : cards.length - 1
            : Math.max(0, Math.min(cards.length - 1, currentIdx + delta));
        cards[nextIdx]?.focus();
        cards[nextIdx]?.scrollIntoView({ block: "center", behavior: "smooth" });
      }
    };
    document.addEventListener("keydown", onKeyDown);
    return () => document.removeEventListener("keydown", onKeyDown);
  }, [isOpen]);

  // Focus input when modal opens
  useEffect(() => {
    if (isOpen) {
      // small delay so framer-motion mounts the input before we grab focus
      const id = window.setTimeout(() => inputRef.current?.focus(), 20);
      return () => window.clearTimeout(id);
    }
  }, [isOpen]);

  // Lock body scroll while open
  useEffect(() => {
    const prev = document.body.style.overflow;
    if (isOpen) {
      document.body.style.overflow = "hidden";
    }
    return () => {
      document.body.style.overflow = prev;
    };
  }, [isOpen]);

  const handleNav = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setActiveIndex((i) => Math.min(results.length - 1, i + 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setActiveIndex((i) => Math.max(0, i - 1));
    } else if (e.key === "Enter") {
      const target = results[activeIndex];
      if (target) {
        e.preventDefault();
        window.location.href = `/blog/${target.id}/`;
      }
    }
  };

  return (
    <>
      <button
        type="button"
        className="search-cue"
        onClick={() => setIsOpen(true)}
        aria-label="Search posts (press slash or command-K)"
        title="Search — press / or ⌘K"
      >
        <kbd>⌘K</kbd>
        <kbd>/</kbd>
        <span>search</span>
      </button>
    <AnimatePresence>
      {isOpen && (
        <motion.div
          className="search-modal__backdrop"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.15 }}
          onClick={(e) => {
            if (e.target === e.currentTarget) setIsOpen(false);
          }}
        >
          <motion.div
            role="dialog"
            aria-label="Search posts"
            className="search-modal"
            initial={{ opacity: 0, y: -12, scale: 0.98 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: -8, scale: 0.98 }}
            transition={{ duration: 0.18, ease: [0.25, 1, 0.5, 1] }}
          >
            <div className="search-modal__input-row">
              <svg
                width="18"
                height="18"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
                className="search-modal__input-icon"
              >
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg>
              <input
                ref={inputRef}
                type="search"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={handleNav}
                placeholder="Search posts, tags, ideas…"
                className="search-modal__input"
                aria-autocomplete="list"
              />
              <kbd className="search-modal__hint">esc</kbd>
            </div>

            <ul className="search-modal__results" role="listbox">
              {entries === null && (
                <li className="search-modal__empty">Loading…</li>
              )}
              {entries !== null && results.length === 0 && (
                <li className="search-modal__empty">
                  {query.trim()
                    ? `Nothing matching “${query}”.`
                    : "No posts yet."}
                </li>
              )}
              {results.map((r, i) => (
                <li key={r.id} role="option" aria-selected={i === activeIndex}>
                  <a
                    href={`/blog/${r.id}/`}
                    className={
                      i === activeIndex
                        ? "search-modal__result is-active"
                        : "search-modal__result"
                    }
                    onMouseEnter={() => setActiveIndex(i)}
                  >
                    <span className="search-modal__result-title">{r.title}</span>
                    {r.description && (
                      <span className="search-modal__result-desc">
                        {r.description}
                      </span>
                    )}
                    <span className="search-modal__result-meta">
                      {r.tags.slice(0, 4).map((t) => (
                        <span key={t} className="search-modal__result-tag">
                          #{t}
                        </span>
                      ))}
                    </span>
                  </a>
                </li>
              ))}
            </ul>

            <div className="search-modal__footer">
              <span>
                <kbd>↑</kbd>
                <kbd>↓</kbd> navigate
              </span>
              <span>
                <kbd>↵</kbd> open
              </span>
              <span>
                <kbd>j</kbd>
                <kbd>k</kbd> next/prev on blog
              </span>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
    </>
  );
}
