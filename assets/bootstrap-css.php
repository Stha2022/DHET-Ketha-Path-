<?php
/**
 * Khetha Path — shared Bootstrap CSS include, one canonical version
 * (5.3.8) for every Bootstrap-based page. assets/navbar.php already
 * include_once's this, so pages that include navbar.php don't need
 * to include this directly.
 */
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
<?php
/**
 * Retints Bootstrap's default blue to Khetha's teal palette (matching
 * assets/css/style.css's --teal/--navy/--mint) so every Bootstrap-based
 * page (dashboard, occupation, career-quiz, subject) reads as one brand
 * instead of stock Bootstrap blue. Targets the exact component classes
 * this app actually uses rather than redefining Bootstrap's --bs-primary
 * variable, since several compiled components (e.g. .btn-primary) bake
 * their colour in at build time and don't read that variable back.
 */
?>
<style>
:root{--bs-primary:#00a99d;--bs-primary-rgb:0,169,157;}
.btn-primary{--bs-btn-bg:#00a99d;--bs-btn-border-color:#00a99d;--bs-btn-hover-bg:#00897f;--bs-btn-hover-border-color:#00897f;--bs-btn-active-bg:#00786f;--bs-btn-active-border-color:#00786f;--bs-btn-disabled-bg:#00a99d;--bs-btn-disabled-border-color:#00a99d;}
.btn-outline-primary{--bs-btn-color:#00a99d;--bs-btn-border-color:#00a99d;--bs-btn-hover-bg:#00a99d;--bs-btn-hover-border-color:#00a99d;--bs-btn-active-bg:#00a99d;--bs-btn-active-border-color:#00a99d;--bs-btn-disabled-color:#00a99d;--bs-btn-disabled-border-color:#00a99d;}
.btn-check:checked+.btn-outline-primary,.btn-check:checked+.btn-outline-secondary{color:#fff;background-color:#00a99d;border-color:#00a99d}
.text-primary{color:#00a99d!important}
.border-primary{border-color:#00a99d!important}
.bg-primary-subtle{background-color:#e7f8f5!important}
.text-primary-emphasis{color:#087c73!important}
.badge.text-bg-primary{background-color:#00a99d!important;color:#fff!important}
.badge.text-bg-primary-subtle{background-color:#e7f8f5!important}
.accordion-button:not(.collapsed){color:#087c73;background-color:#e7f8f5}
.accordion-button:focus{border-color:#00a99d;box-shadow:0 0 0 .25rem rgba(0,169,157,.25)}
.progress-bar{background-color:#00a99d}
.form-check-input:checked{background-color:#00a99d;border-color:#00a99d}
.form-check-input:focus{border-color:#00a99d;box-shadow:0 0 0 .25rem rgba(0,169,157,.25)}
.list-group-item.active{background-color:#00a99d;border-color:#00a99d}
.alert-info{--bs-alert-bg:#e7f8f5;--bs-alert-border-color:#bfe9e4;--bs-alert-color:#087c73;}
.alert-link{color:#087c73!important}
a{color:#00a99d}
</style>
