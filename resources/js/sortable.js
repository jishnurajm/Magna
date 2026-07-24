// Bundled via npm/Vite instead of loaded from a CDN (Stage 7/12 follow-up):
// a CDN <script> tag has no realistic way to get a correct integrity hash
// added without live network access to compute it, and every CDN request
// is a compromise/MITM surface for the admin panel's own origin regardless.
// Bundling pins the exact version via package-lock.json instead.
import Sortable from 'sortablejs';

window.Sortable = Sortable;
