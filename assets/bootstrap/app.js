// Entry for Twig pages styled with Bootstrap and driven by jQuery.
// Must never import anything from assets/vue (enforced by ESLint).
import './app.scss';
import $ from 'jquery';
import * as bootstrap from 'bootstrap';

window.$ = window.jQuery = $;
window.bootstrap = bootstrap;
