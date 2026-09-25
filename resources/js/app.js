import { initAnalytics } from './analytics';
import { initBooking } from './booking';
import { initInteractions } from './interactions';
import { initJourney } from './journey';
import { initDiagrams } from './diagrams';
import { initFitCheck } from './fit-check';
import { initMiniature } from './miniature';

initInteractions();
initDiagrams();
initMiniature();
initJourney();
initAnalytics();
initFitCheck();
initBooking();
