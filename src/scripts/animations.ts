import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { annotate } from 'rough-notation';
import type { RoughAnnotation } from 'rough-notation/lib/model';

gsap.registerPlugin(ScrollTrigger);

let shownAnnotations: RoughAnnotation[] = [];

function cleanup() {
  for (const a of shownAnnotations) {
    a.remove();
  }
  shownAnnotations = [];
  ScrollTrigger.getAll().forEach((t) => t.kill());
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

  const heroEm = document.querySelector('.hero__title em');
  if (heroEm) {
    const a = annotate(heroEm as HTMLElement, {
      type: 'highlight',
      color: sapphire + '40',
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
      type: 'box',
      color: peach,
      strokeWidth: 2,
      padding: 6,
      animate,
      animationDuration: animate ? 700 : 0,
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
}

document.addEventListener('astro:before-swap', cleanup);
document.addEventListener('astro:page-load', init);
