---
paths:
  - 'resources/js/**'
---

# Js

## Packets are rare and staggered; dotted routes march slowly
Owner direction 2026-09-25, site-wide: constant packets from every desk read as too much. Dotted routes keep a slow march (they show direction); packets launch one path at a time in a shuffled order at uneven intervals, usually one in flight and never more than two (packetsInFlight in resources/js/miniature.js). Scroll-driven packets (the /work route) move only with scroll. Reduced motion shows resting packets, no loops.

## Routes and packets never cross text
Owner feedback 2026-09-25: on mobile the /work route and packet ran through story paragraphs and hurt readability. Animated routes and packets stay inside the art: when a layout stacks into one column, break the route into per-room (per-figure) segments and hide scroll packets (resources/js/journey.js). Verify by sampling points along the path against text boxes at each breakpoint.
