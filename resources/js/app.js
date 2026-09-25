import { initAnalytics } from './analytics';
import { initBooking } from './booking';
import { initInteractions } from './interactions';
import { initJourney } from './journey';
import { initDiagrams } from './diagrams';
import { initFindEssay } from './find-essay';
import { initFitCheck } from './fit-check';
import { initMiniature } from './miniature';
import { initSelfCheck } from './self-check';

initInteractions();
initDiagrams();
initMiniature();
initJourney();
initSelfCheck();
initFindEssay();
initAnalytics();
initFitCheck();
initBooking();
