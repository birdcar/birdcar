import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { computePosition, offset, flip, shift, arrow } from '@floating-ui/dom';
import { annotate } from 'rough-notation';
import type { RoughAnnotation } from 'rough-notation/lib/model';

gsap.registerPlugin(ScrollTrigger);

let shownAnnotations: RoughAnnotation[] = [];
let tocObserver: IntersectionObserver | null = null;
let activePopover: HTMLElement | null = null;
let popoverCleanup: (() => void) | null = null;
let popoverDismissHandler: ((e: MouseEvent) => void) | null = null;

function cleanup() {
  for (const a of shownAnnotations) {
    a.remove();
  }
  shownAnnotations = [];
  ScrollTrigger.getAll().forEach((t) => t.kill());
  // rough-notation caches its <style> element in window.__rno_kf_s and
  // only injects keyframes once. View transition swaps remove the <style>
  // from the DOM but the reference persists, so reset it to force re-injection.
  delete (window as any).__rno_kf_s;

  tocObserver?.disconnect();
  tocObserver = null;

  dismissFootnotePopover();
  if (popoverDismissHandler) {
    document.removeEventListener('click', popoverDismissHandler);
    popoverDismissHandler = null;
  }
}

function css(name: string): string {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function refreshAnnotations() {
  for (const a of shownAnnotations) {
    if (a.isShowing()) {
      a.animate = false;
      a.hide();
      a.show();
      a.animate = true;
    }
  }
  ScrollTrigger.refresh();
}


function initGSAPAnimations() {
  const heroEyebrow = document.querySelector('.hero__eyebrow');
  const heroTitle = document.querySelector('.hero__title');
  const heroDescription = document.querySelector('.hero__description');
  const heroElements = [heroEyebrow, heroTitle, heroDescription].filter(Boolean);

  if (heroElements.length) {
    gsap.set(heroElements, { opacity: 0, y: 30 });
    gsap.to(heroElements, {
      opacity: 1,
      y: 0,
      duration: 0.7,
      stagger: 0.15,
      ease: 'power3.out',
      delay: 0.1,
    });
  }

  const articleHeader = document.querySelector('.article__header');
  if (articleHeader) {
    gsap.set(articleHeader, { opacity: 0, y: 25 });
    gsap.to(articleHeader, {
      opacity: 1,
      y: 0,
      duration: 0.6,
      ease: 'power3.out',
      delay: 0.15,
    });
  }

  const postItems = document.querySelectorAll('.post-list__item');
  if (postItems.length) {
    gsap.set(postItems, { opacity: 0, y: 20 });
    ScrollTrigger.batch(postItems, {
      onEnter: (batch) =>
        gsap.to(batch, {
          opacity: 1,
          y: 0,
          duration: 0.5,
          stagger: 0.08,
          ease: 'power2.out',
        }),
      start: 'top 90%',
      once: true,
    });
  }

  const tagItems = document.querySelectorAll('.tags-grid li');
  if (tagItems.length) {
    gsap.set(tagItems, { opacity: 0, scale: 0.9 });
    ScrollTrigger.batch(tagItems, {
      onEnter: (batch) =>
        gsap.to(batch, {
          opacity: 1,
          scale: 1,
          duration: 0.4,
          stagger: 0.04,
          ease: 'back.out(1.4)',
        }),
      start: 'top 90%',
      once: true,
    });
  }

  const nav = document.querySelector('.site-header');
  if (nav && !sessionStorage.getItem('nav-animated')) {
    gsap.set(nav, { opacity: 0, y: -15 });
    gsap.to(nav, {
      opacity: 1,
      y: 0,
      duration: 0.5,
      ease: 'power2.out',
      delay: 0.3,
    });
    sessionStorage.setItem('nav-animated', '1');
  }

  const sectionHeadings = document.querySelectorAll('.section-heading');
  if (sectionHeadings.length) {
    gsap.set(sectionHeadings, { opacity: 0, y: 10 });
    ScrollTrigger.batch(sectionHeadings, {
      onEnter: (batch) =>
        gsap.to(batch, {
          opacity: 1,
          y: 0,
          duration: 0.5,
          ease: 'power2.out',
        }),
      start: 'top 85%',
      once: true,
    });
  }
}

function initRoughNotations(animate = true) {
  const sapphire = css('--ctp-sapphire');
  const teal = css('--ctp-teal');
  const peach = css('--ctp-peach');
  const lavender = css('--ctp-lavender');
  const red = css('--ctp-red');
  const yellow = css('--ctp-yellow');
  const flamingo = css('--ctp-flamingo');

  const heroEm = document.querySelector('.hero__title em');
  if (heroEm) {
    const a = annotate(heroEm as HTMLElement, {
      type: 'highlight',
      color: yellow + '55',
      animate,
      animationDuration: animate ? 1200 : 0,
      multiline: true,
    });
    if (animate) {
      setTimeout(() => { a.show(); shownAnnotations.push(a); }, 900);
    } else {
      a.show();
      shownAnnotations.push(a);
    }
  }

  const readingTime = document.querySelector('.article__reading-time');
  if (readingTime) {
    const a = annotate(readingTime as HTMLElement, {
      type: 'box',
      color: teal,
      strokeWidth: 1,
      padding: [2, 6],
      animate,
      animationDuration: animate ? 600 : 0,
    });
    if (animate) {
      setTimeout(() => { a.show(); shownAnnotations.push(a); }, 600);
    } else {
      a.show();
      shownAnnotations.push(a);
    }
  }

  document.querySelectorAll('.prose h2').forEach((el) => {
    const a = annotate(el as HTMLElement, {
      type: 'bracket',
      color: lavender,
      strokeWidth: 2,
      padding: 8,
      animate,
      animationDuration: animate ? 500 : 0,
      brackets: ['left'],
    });
    observeAndShow(el as HTMLElement, a, animate);
  });

  document.querySelectorAll('.prose h3').forEach((el) => {
    const a = annotate(el as HTMLElement, {
      type: 'underline',
      color: peach,
      strokeWidth: 2,
      animate,
      animationDuration: animate ? 500 : 0,
    });
    observeAndShow(el as HTMLElement, a, animate);
  });

  document.querySelectorAll('.prose blockquote').forEach((el) => {
    const a = annotate(el as HTMLElement, {
      type: 'bracket',
      color: flamingo,
      strokeWidth: 2,
      padding: 8,
      animate,
      animationDuration: animate ? 700 : 0,
      brackets: ['left', 'right'],
    });
    observeAndShow(el as HTMLElement, a, animate);
  });

  document.querySelectorAll('.task-item--done').forEach((el) => {
    const a = annotate(el as HTMLElement, {
      type: 'strike-through',
      color: red,
      strokeWidth: 1,
      animate,
      animationDuration: animate ? 400 : 0,
    });
    observeAndShow(el as HTMLElement, a, animate);
  });

  document.querySelectorAll('.task-item--irrelevant').forEach((el) => {
    const a = annotate(el as HTMLElement, {
      type: 'crossed-off',
      color: red,
      strokeWidth: 1,
      animate,
      animationDuration: animate ? 400 : 0,
    });
    observeAndShow(el as HTMLElement, a, animate);
  });
}

function observeAndShow(el: HTMLElement, annotation: RoughAnnotation, animate: boolean) {
  if (!animate) {
    annotation.show();
    shownAnnotations.push(annotation);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          annotation.show();
          shownAnnotations.push(annotation);
          observer.unobserve(el);
        }
      });
    },
    { threshold: 0.3, rootMargin: '0px 0px -50px 0px' },
  );
  observer.observe(el);
}

function initTocScrollSpy() {
  const toc = document.querySelector('.toc');
  if (!toc) return;

  const tocLinks = toc.querySelectorAll<HTMLAnchorElement>('a[href^="#"]');
  if (!tocLinks.length) return;

  const headingMap = new Map<string, HTMLAnchorElement>();
  for (const link of tocLinks) {
    const id = link.getAttribute('href')?.slice(1);
    if (id) headingMap.set(id, link);
  }

  const headings = Array.from(headingMap.keys())
    .map((id) => document.getElementById(id))
    .filter(Boolean) as HTMLElement[];

  if (!headings.length) return;

  tocObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) {
          for (const link of tocLinks) link.classList.remove('toc--active');
          headingMap.get(entry.target.id)?.classList.add('toc--active');
        }
      }
    },
    { rootMargin: '0px 0px -70% 0px', threshold: 0 },
  );

  for (const heading of headings) tocObserver.observe(heading);
}

/* ── Footnote Popovers (Floating UI + GSAP) ──────────────────────────── */

function dismissFootnotePopover() {
  if (!activePopover) return;
  const pop = activePopover;
  activePopover = null;
  popoverCleanup?.();
  popoverCleanup = null;

  gsap.to(pop, {
    opacity: 0,
    y: -4,
    duration: 0.15,
    ease: 'power2.in',
    onComplete: () => pop.remove(),
  });
}

async function positionPopover(ref: HTMLElement, popover: HTMLElement, arrowEl: HTMLElement) {
  const { x, y, placement, middlewareData } = await computePosition(ref, popover, {
    placement: 'bottom',
    middleware: [
      offset(12),
      flip({ fallbackPlacements: ['top'] }),
      shift({ padding: 12 }),
      arrow({ element: arrowEl }),
    ],
  });

  Object.assign(popover.style, { left: `${x}px`, top: `${y}px` });

  const side = placement.split('-')[0] as 'top' | 'bottom';
  const arrowX = middlewareData.arrow?.x;
  const arrowY = middlewareData.arrow?.y;

  const staticSide = { top: 'bottom', bottom: 'top' }[side] as string;
  Object.assign(arrowEl.style, {
    left: arrowX != null ? `${arrowX}px` : '',
    top: arrowY != null ? `${arrowY}px` : '',
    [staticSide]: '-6px',
    // Rotate arrow based on which side the popover is on
    transform: side === 'top' ? 'rotate(225deg)' : 'rotate(45deg)',
  });
}

function initFootnotePopovers() {
  const refs = document.querySelectorAll<HTMLAnchorElement>('a[data-footnote-ref]');
  if (!refs.length) return;

  const pillColors = [
    css('--ctp-sapphire'),
    css('--ctp-mauve'),
    css('--ctp-peach'),
    css('--ctp-green'),
    css('--ctp-pink'),
    css('--ctp-teal'),
    css('--ctp-lavender'),
    css('--ctp-flamingo'),
  ];

  refs.forEach((ref, i) => {
    const color = pillColors[i % pillColors.length];
    ref.style.background = color;
  });

  const footnotesSection = document.querySelector('.footnotes');
  if (footnotesSection) footnotesSection.classList.add('footnotes--has-popovers');

  for (const ref of refs) {
    ref.addEventListener('click', (e) => {
      e.preventDefault();

      if (activePopover && activePopover.dataset.fnId === ref.getAttribute('href')?.slice(1)) {
        dismissFootnotePopover();
        return;
      }

      dismissFootnotePopover();

      const targetId = ref.getAttribute('href')?.slice(1);
      if (!targetId) return;

      const footnoteLi = document.getElementById(targetId);
      if (!footnoteLi) return;

      const popover = document.createElement('div');
      popover.className = 'footnote-popover';
      popover.dataset.fnId = targetId;

      // Clone child nodes from the footnote (same-page trusted content)
      for (const child of footnoteLi.childNodes) {
        popover.appendChild(child.cloneNode(true));
      }

      const arrowEl = document.createElement('div');
      arrowEl.className = 'footnote-popover__arrow';
      popover.appendChild(arrowEl);

      document.body.appendChild(popover);
      activePopover = popover;

      // Position with Floating UI, then animate in with GSAP
      positionPopover(ref, popover, arrowEl).then(() => {
        popover.classList.add('is-visible');
        gsap.fromTo(popover,
          { opacity: 0, y: 4 },
          { opacity: 1, y: 0, duration: 0.2, ease: 'power2.out' },
        );
      });

      // Update position on scroll/resize
      const update = () => {
        if (activePopover === popover) positionPopover(ref, popover, arrowEl);
      };
      window.addEventListener('scroll', update, { passive: true });
      window.addEventListener('resize', update, { passive: true });
      popoverCleanup = () => {
        window.removeEventListener('scroll', update);
        window.removeEventListener('resize', update);
      };
    });
  }

  popoverDismissHandler = (e: MouseEvent) => {
    if (!activePopover) return;
    const target = e.target as HTMLElement;
    if (activePopover.contains(target) || target.closest('a[data-footnote-ref]')) return;
    dismissFootnotePopover();
  };
  document.addEventListener('click', popoverDismissHandler);
}

function init() {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  if (!prefersReducedMotion) {
    initGSAPAnimations();
    initRoughNotations();
  } else {
    initRoughNotations(false);
  }

  document.querySelectorAll('details').forEach((details) => {
    details.addEventListener('toggle', () => {
      requestAnimationFrame(refreshAnnotations);
    });
  });

  initTocScrollSpy();
  initFootnotePopovers();
}

document.addEventListener('astro:before-swap', cleanup);
document.addEventListener('astro:page-load', init);
