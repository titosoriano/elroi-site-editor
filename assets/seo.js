/*
 * ElRoi Site Editor: Rank Math reads pages built from fields.
 *
 * The page body of a designed page is empty, so Rank Math would score an empty page. This posts the
 * form's current values to the page itself (a preview: nothing is saved), takes what visitors see
 * inside <main> and gives it to Rank Math through its `rank_math_content` filter. It runs when the
 * edit screen opens and again a moment after each change, so the score follows what is typed.
 */
(function () {
	'use strict';
	var cfg = window.elroiSeo;
	if (!cfg || !cfg.url || !window.wp || !wp.hooks || !window.fetch || !window.FormData || !window.DOMParser) {
		return;
	}

	var html = '';
	var timer = null;
	var ctrl = null;

	wp.hooks.addFilter('rank_math_content', 'elroi-site-editor/page-content', function (content) {
		return html || content;
	});

	function refresh(tries) {
		if (window.rankMathEditor && typeof window.rankMathEditor.refresh === 'function') {
			window.rankMathEditor.refresh('content');
		} else if (tries < 75) {
			setTimeout(function () { refresh(tries + 1); }, 200);
		}
	}

	// Only the page form's own fields travel; ticked boxes and chosen options, as on Update.
	function body() {
		var data = new FormData();
		var form = document.getElementById('post');
		if (form && document.getElementById('elroi-content')) {
			new FormData(form).forEach(function (value, name) {
				if (name.indexOf('elroi[') === 0) {
					data.append(name, value);
				}
			});
		}
		data.append('elroi_seo', cfg.nonce);
		data.append('elroi_seo_post', String(cfg.post));
		return data;
	}

	// What a reader sees: no scripts, icons, hidden or decorative copies (split words, marquee repeats).
	function visible(doc) {
		var main = doc.querySelector(cfg.selector || 'main') || doc.body;
		if (!main) {
			return '';
		}
		main.querySelectorAll('script, style, noscript, template, svg, [hidden], [aria-hidden="true"], [data-elroi-seo="skip"], #wpadminbar').forEach(function (n) {
			n.remove();
		});
		return main.innerHTML;
	}

	function load() {
		if (ctrl && ctrl.abort) {
			ctrl.abort();
		}
		ctrl = window.AbortController ? new AbortController() : null;
		fetch(cfg.url, { method: 'POST', credentials: 'same-origin', body: body(), signal: ctrl ? ctrl.signal : undefined })
			.then(function (r) { return r.ok ? r.text() : ''; })
			.then(function (text) {
				if (!text) {
					return;
				}
				html = visible(new DOMParser().parseFromString(text, 'text/html'));
				refresh(0);
			})
			.catch(function () {});
	}

	function later() {
		clearTimeout(timer);
		timer = setTimeout(load, 1200);
	}

	var box = document.getElementById('elroi-content');
	if (box) {
		box.addEventListener('input', later);
		box.addEventListener('change', later);
		// Adding, moving or removing items and choosing photos change the page without an input event.
		box.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('button, [data-elroi-add], [data-elroi-remove], [data-elroi-up], [data-elroi-down]')) {
				later();
			}
		});
		// The media picker closes outside the box.
		if (window.wp && wp.media && wp.media.view && wp.media.view.Modal) {
			document.addEventListener('click', function (e) {
				if (e.target.closest && e.target.closest('.media-modal .media-button-select')) {
					later();
				}
			});
		}
	}
	load();
})();
