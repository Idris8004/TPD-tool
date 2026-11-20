/**
 * TPD native LMS: lesson player inside the advisor dashboard (Resources & Tools).
 * Opens from any .tpd-lms-launch button (data-redirect = course slug).
 * Talks to includes/lms/class-tpd-lms-student.php. Every value shown is escaped,
 * except lesson/question HTML, which the server has already cleaned with wp_kses_post.
 */
(function ($) {
	'use strict';

	if (typeof tpdLmsStudent === 'undefined') return;

	var $player, $hub;
	var st = { outline: null, lesson: null, attempt: null, timer: null, saveT: null };
	var TYPE_ICON = { text: 'fa-file-lines', video: 'fa-circle-play', pdf: 'fa-file-pdf', audio: 'fa-headphones', download: 'fa-file-arrow-down', multimedia: 'fa-window-restore', presentation: 'fa-person-chalkboard', live: 'fa-tower-broadcast', quiz: 'fa-list-check', survey: 'fa-clipboard-list', assignment: 'fa-file-pen', exam: 'fa-graduation-cap' };

	function esc(v) {
		return String(v === null || v === undefined ? '' : v)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function api(action, data) {
		data = $.extend({ action: 'tpd_lms_s_' + action, nonce: tpdLmsStudent.nonce }, data || {});
		return $.ajax({ url: tpdLmsStudent.ajaxUrl, type: 'POST', data: data, dataType: 'json' })
			.then(function (res) {
				if (res && res.success) return res.data;
				return $.Deferred().reject(res && res.data && res.data.message ? res.data.message : 'Something went wrong. Try again.').promise();
			}, function (xhr) {
				var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				return $.Deferred().reject(msg || 'The server could not be reached. Check your connection and try again.').promise();
			});
	}

	function flash(msg, kind) {
		var $n = $player.find('.tpd-lms-p-notice');
		$n.removeClass('is-error is-ok').addClass(kind === 'error' ? 'is-error' : 'is-ok').text(msg).prop('hidden', false);
		clearTimeout(flash.t);
		flash.t = setTimeout(function () { $n.prop('hidden', true); }, kind === 'error' ? 8000 : 3500);
	}

	function stopTimer() {
		if (st.timer) clearInterval(st.timer);
		st.timer = null;
	}

	/* ------------------------------------------------------------------ */
	/* Open / close                                                        */
	/* ------------------------------------------------------------------ */

	function openCourse(slug, lessonId) {
		stopTimer();
		$hub.children().not($player).hide();
		$player.prop('hidden', false).html('<div class="tpd-card-box tpd-lms-p-loading"><i class="fa-solid fa-spinner fa-spin"></i> Opening course…</div>');
		window.scrollTo({ top: $hub.offset().top - 90, behavior: 'smooth' });
		api('outline', { course: slug }).then(function (outline) {
			st.outline = outline;
			renderShell();
			var target = lessonId || outline.resume || firstOpenLesson();
			if (target) openLesson(target);
			else renderOverview();
		}, function (msg) {
			closePlayer();
			window.alert(msg);
		});
	}

	function closePlayer() {
		stopTimer();
		$player.prop('hidden', true).empty();
		$hub.children().not($player).show();
		// Keep the sub-tab panes as they were: only the active one is visible.
		$hub.find('.tpd-res-pane').not('.active').hide();
		if (st.dirty) window.location.reload(); // Progress changed: refresh the course cards.
	}

	function firstOpenLesson() {
		var id = null;
		st.outline.chapters.some(function (ch) {
			return ch.lessons.some(function (l) { if (!l.locked) { id = l.id; return true; } return false; });
		});
		return id;
	}

	function flatLessons() {
		var out = [];
		st.outline.chapters.forEach(function (ch) { ch.lessons.forEach(function (l) { out.push(l); }); });
		return out;
	}

	/* ------------------------------------------------------------------ */
	/* Shell: header + curriculum path + content area                      */
	/* ------------------------------------------------------------------ */

	function renderShell() {
		var o = st.outline, c = o.course;
		$player.html(
			'<div class="tpd-lms-p-head tpd-card-box">' +
			'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-lms-close><i class="fa-solid fa-arrow-left"></i> Back to resources</button>' +
			'<div class="tpd-lms-p-title"><h2>' + esc(c.title) + '</h2>' + (c.subtitle ? '<p>' + esc(c.subtitle) + '</p>' : '') + '</div>' +
			renderHeadStatus() +
			'</div>' +
			'<div class="tpd-lms-p-notice" role="status" aria-live="polite" hidden></div>' +
			'<div class="tpd-lms-p-grid">' +
			'<nav class="tpd-card-box tpd-lms-path" aria-label="Course curriculum">' + renderPath() + '</nav>' +
			'<section class="tpd-card-box tpd-lms-p-main" aria-live="polite"></section>' +
			'</div>'
		);
	}

	function renderHeadStatus() {
		var o = st.outline;
		if (o.preview) return '<span class="tpd-lms-badge is-draft">Course team preview</span>';
		if (!o.enrolled) return '<div class="tpd-lms-p-progress">' + enrollButton() + '</div>';
		if (o.expired) return '<span class="tpd-lms-badge is-error">Access ended</span>';
		var cert = o.certificate ? ' <a class="tpd-btn tpd-btn-xs tpd-btn-outline" target="_blank" rel="noopener" href="' + esc(o.certificate.url) + '"><i class="fa-solid fa-certificate text-gold"></i> Your certificate</a>' : '';
		return '<div class="tpd-lms-p-progress"><div class="tpd-lms-p-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + o.progress + '" aria-label="Course progress"><span style="width:' + o.progress + '%"></span></div>' +
			'<strong>' + o.progress + '% complete</strong>' + cert + '</div>';
	}

	function enrollButton() {
		var c = st.outline.course;
		var label = c.included_with_plan ? 'Included with your plan: enroll' : (c.is_free ? 'Enroll free' : 'Enroll for $' + Number(c.price).toFixed(2));
		return '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-open-checkout-btn" data-item-type="lms_course" data-item-id="' + esc(c.slug) + '" data-item-title="' + esc(c.title) + '" data-item-price="' + esc(c.price) + '" style="background:#00798c;border-color:#00798c;">' +
			'<i class="fa-solid ' + (c.is_free ? 'fa-bolt' : 'fa-cart-shopping') + '"></i> ' + esc(label) + '</button>';
	}

	function renderPath() {
		var current = st.lesson ? st.lesson.id : null;
		return st.outline.chapters.map(function (ch) {
			var items = ch.lessons.map(function (l) {
				var state = l.done ? 'is-done' : (l.locked ? 'is-locked' : '');
				var icon = l.done ? 'fa-circle-check' : (l.locked ? 'fa-lock' : (TYPE_ICON[l.type] || 'fa-file-lines'));
				return '<li class="tpd-lms-path-item ' + state + (l.id === current ? ' is-current' : '') + '">' +
					'<button type="button" data-lms-lesson="' + l.id + '"' + (l.id === current ? ' aria-current="step"' : '') + (l.locked ? ' title="' + esc(l.lock) + '"' : '') + '>' +
					'<i class="fa-solid ' + icon + '" aria-hidden="true"></i>' +
					'<span class="tpd-lms-path-text">' + esc(l.title) +
					'<small>' + (l.done ? 'Completed' : l.locked ? esc(l.lock) : (l.is_preview && !st.outline.enrolled ? 'Free preview' : (l.minutes ? l.minutes + ' min' : esc(typeLabel(l.type))))) + '</small></span>' +
					'</button></li>';
			}).join('');
			return '<div class="tpd-lms-path-chapter"><h3>' + esc(ch.title) + '</h3><ol>' + items + '</ol></div>';
		}).join('');
	}

	function typeLabel(t) {
		return { text: 'Reading', video: 'Video', pdf: 'PDF', audio: 'Audio', download: 'Download', multimedia: 'Interactive', presentation: 'Presentation', live: 'Live session', quiz: 'Quiz', survey: 'Survey', assignment: 'Assignment', exam: 'Final exam' }[t] || 'Lesson';
	}

	function refreshPath() {
		$player.find('.tpd-lms-path').html(renderPath());
		$player.find('.tpd-lms-p-head > :last-child').replaceWith(renderHeadStatus());
	}

	function renderOverview() {
		var c = st.outline.course;
		$player.find('.tpd-lms-p-main').html(
			'<h3 class="tpd-lms-p-h3">About this course</h3>' +
			'<div class="tpd-lms-content">' + (c.description || '<p>Open a free preview lesson from the list, or enroll to start.</p>') + '</div>' +
			(!st.outline.enrolled && !st.outline.preview ? '<div class="tpd-lms-p-actions">' + enrollButton() + '</div>' : '')
		);
	}

	/* ------------------------------------------------------------------ */
	/* Lessons                                                             */
	/* ------------------------------------------------------------------ */

	function openLesson(id) {
		stopTimer();
		deck = null; // Stops slide narration from the previous lesson.
		$player.find('audio, video').each(function () { try { this.pause(); } catch (e) {} });
		var $main = $player.find('.tpd-lms-p-main');
		$main.html('<div class="tpd-lms-p-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading lesson…</div>');
		api('lesson', { lesson_id: id }).then(function (lesson) {
			st.lesson = lesson;
			refreshPath();
			renderLesson();
		}, function (msg) {
			$main.html('<div class="tpd-lms-locked"><i class="fa-solid fa-lock" aria-hidden="true"></i><p>' + esc(msg) + '</p>' +
				(!st.outline.enrolled && !st.outline.preview ? enrollButton() : '') + '</div>');
		});
	}

	function renderLesson() {
		var l = st.lesson;
		var body = '';
		if (l.video) {
			if (l.video.kind === 'embed') body += '<div class="tpd-lms-video"><iframe src="' + esc(l.video.src) + '" title="' + esc(l.title) + '" allow="accelerometer; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe></div>';
			else if (l.video.kind === 'file') body += '<div class="tpd-lms-video"><video controls preload="metadata" src="' + esc(l.video.src) + '"></video></div>';
			else body += '<p><a class="tpd-btn tpd-btn-sm tpd-btn-outline" target="_blank" rel="noopener" href="' + esc(l.video.src) + '"><i class="fa-solid fa-circle-play"></i> Watch the video</a></p>';
		}
		(l.files || []).forEach(function (f) {
			body += '<div class="tpd-lms-file"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i><span>' + esc(f.name) + (f.size ? ' <small class="tpd-lms-muted">' + esc(f.size) + '</small>' : '') + '</span>' +
				'<a class="tpd-btn tpd-btn-sm tpd-btn-darkblue" href="' + esc(f.url) + '"><i class="fa-solid fa-download"></i> Download</a></div>';
		});
		if (l.type === 'pdf') {
			body += l.pdf ? '<div class="tpd-lms-pdf" data-pdf-pages><p class="tpd-lms-muted"><i class="fa-solid fa-spinner fa-spin"></i> Loading the PDF…</p></div>' +
				(l.pdf.allow_download ? '<p><a class="tpd-btn tpd-btn-sm tpd-btn-outline" href="' + esc(l.pdf.url) + '&download=1"><i class="fa-solid fa-download"></i> Download the PDF</a></p>' : '') :
				'<p class="tpd-lms-muted">The PDF for this lesson hasn’t been added yet.</p>';
		}
		if (l.type === 'audio') {
			body += l.audio ? '<div class="tpd-lms-audio"><i class="fa-solid fa-headphones" aria-hidden="true"></i><audio controls preload="metadata" src="' + esc(l.audio.src) + '"></audio></div>' :
				'<p class="tpd-lms-muted">The audio for this lesson hasn’t been added yet.</p>';
		}
		if (l.type === 'multimedia') {
			body += l.embed ? '<div class="tpd-lms-embed" style="height:' + Number(l.embed.height) + 'px"><iframe src="' + esc(l.embed.src) + '" title="' + esc(l.title) + '" allow="autoplay; fullscreen; clipboard-write; encrypted-media; picture-in-picture" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>' +
				'<p class="tpd-lms-muted">Not loading? <a href="' + esc(l.embed.src) + '" target="_blank" rel="noopener">Open it in a new tab</a>.</p>' :
				'<p class="tpd-lms-muted">The content for this lesson hasn’t been added yet.</p>';
		}
		if (l.type === 'presentation') body += renderPresentationShell(l.presentation);
		if (l.type === 'live') body += renderLive(l.live);
		if (l.content) body += '<div class="tpd-lms-content">' + l.content + '</div>';
		if (l.type === 'assignment') body += renderAssignment(l.assignment);

		var footer = '';
		if (l.quiz) {
			body += renderQuizIntro(l.quiz);
		} else if (l.can_mark) {
			var gated = l.type === 'presentation' && l.presentation && l.presentation.require_all_slides && !l.done;
			footer = l.done ?
				'<span class="tpd-lms-done"><i class="fa-solid fa-circle-check"></i> Completed</span>' + nextButton() :
				'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-complete style="background:#00798c;border-color:#00798c;"' + (gated ? ' disabled' : '') + '><i class="fa-solid fa-check"></i> Mark complete and continue</button>' +
				(gated ? '<span class="tpd-lms-muted" data-slides-left>View every slide to continue.</span>' : '');
		} else if (l.type !== 'assignment' || l.done) {
			footer = (l.done ? '<span class="tpd-lms-done"><i class="fa-solid fa-circle-check"></i> Completed</span>' : '') + nextButton();
		}

		$player.find('.tpd-lms-p-main').html(
			'<div class="tpd-lms-p-lesson-head"><span class="tpd-lms-muted"><i class="fa-solid ' + (TYPE_ICON[l.type] || 'fa-file-lines') + '"></i> ' + esc(typeLabel(l.type)) + (l.minutes ? ' · ' + l.minutes + ' min' : '') + '</span>' +
			'<h3 class="tpd-lms-p-h3">' + esc(l.title) + '</h3></div>' +
			body +
			(footer ? '<div class="tpd-lms-p-actions">' + footer + '</div>' : '')
		);
		if (l.type === 'pdf' && l.pdf) renderPdfPages(l.pdf.url);
		if (l.type === 'presentation' && l.presentation) startPresentation(l.presentation);
	}

	/* PDF and presentation (PDF.js, loaded on demand) ---------------------- */

	var pdfjsLoading = null;
	function loadPdfJs() {
		if (window.pdfjsLib) return $.Deferred().resolve(window.pdfjsLib).promise();
		if (!pdfjsLoading) {
			pdfjsLoading = $.Deferred();
			var sc = document.createElement('script');
			sc.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
			sc.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'; pdfjsLoading.resolve(window.pdfjsLib); };
			sc.onerror = function () { pdfjsLoading.reject(); };
			document.head.appendChild(sc);
		}
		return pdfjsLoading.promise();
	}

	function openPdf(url) {
		return loadPdfJs().then(function (lib) { return lib.getDocument({ url: url, withCredentials: true }).promise; });
	}

	function drawPage(pdf, n, canvas, width) {
		return pdf.getPage(n).then(function (page) {
			var base = page.getViewport({ scale: 1 });
			var ratio = window.devicePixelRatio || 1;
			var view = page.getViewport({ scale: (width / base.width) * ratio });
			canvas.width = view.width; canvas.height = view.height;
			canvas.style.width = '100%';
			return page.render({ canvasContext: canvas.getContext('2d'), viewport: view }).promise;
		});
	}

	/** PDF lesson: every page, drawn as it scrolls into view. */
	function renderPdfPages(url) {
		var $wrap = $player.find('[data-pdf-pages]');
		openPdf(url).then(function (pdf) {
			var width = $wrap.width() || 800;
			$wrap.html('');
			var io = window.IntersectionObserver ? new IntersectionObserver(function (entries) {
				entries.forEach(function (en) {
					if (en.isIntersecting && !en.target.dataset.done) { en.target.dataset.done = '1'; drawPage(pdf, Number(en.target.dataset.page), en.target, width); }
				});
			}, { rootMargin: '400px' }) : null;
			for (var n = 1; n <= pdf.numPages; n++) {
				var c = document.createElement('canvas');
				c.dataset.page = n;
				c.setAttribute('aria-label', 'Page ' + n + ' of ' + pdf.numPages);
				$wrap.append(c);
				if (io) io.observe(c); else drawPage(pdf, n, c, width);
			}
		}, function () {
			$wrap.html('<p class="tpd-lms-muted">The PDF couldn’t be shown here. Check your connection and reload.</p>');
		});
	}

	function renderPresentationShell(p) {
		if (!p) return '<p class="tpd-lms-muted">The slides for this lesson haven’t been added yet.</p>';
		return '<div class="tpd-lms-deck" data-deck tabindex="0" aria-roledescription="slide deck">' +
			'<div class="tpd-lms-deck-stage"><canvas data-deck-canvas aria-live="polite"></canvas><div class="tpd-lms-deck-loading"><i class="fa-solid fa-spinner fa-spin"></i></div></div>' +
			'<div class="tpd-lms-deck-bar">' +
			'<button type="button" class="tpd-lms-icon-btn" data-deck-prev aria-label="Previous slide"><i class="fa-solid fa-chevron-left"></i></button>' +
			'<span data-deck-count>…</span>' +
			'<button type="button" class="tpd-lms-icon-btn" data-deck-next aria-label="Next slide"><i class="fa-solid fa-chevron-right"></i></button>' +
			'<span class="tpd-lms-deck-spacer"></span>' +
			(p.allow_download ? '<a class="tpd-lms-icon-btn" href="' + esc(p.pdf) + '&download=1" aria-label="Download the slides" title="Download the slides"><i class="fa-solid fa-download"></i></a>' : '') +
			'<button type="button" class="tpd-lms-icon-btn" data-deck-full aria-label="Full screen" title="Full screen"><i class="fa-solid fa-expand"></i></button>' +
			'</div>' +
			'<div class="tpd-lms-deck-audio" data-deck-audio hidden></div>' +
			'<div class="tpd-lms-deck-text tpd-lms-content" data-deck-text></div>' +
			'</div>';
	}

	var deck = null;
	function startPresentation(p) {
		deck = { p: p, pdf: null, n: 1, seen: {}, total: 0 };
		openPdf(p.pdf).then(function (pdf) {
			deck.pdf = pdf;
			deck.total = pdf.numPages;
			showSlide(1);
		}, function () {
			$player.find('[data-deck]').html('<p class="tpd-lms-muted">The slides couldn’t be shown here. Check your connection and reload.</p>');
		});
	}

	function showSlide(n) {
		if (!deck || !deck.pdf) return;
		n = Math.max(1, Math.min(deck.total, n));
		deck.n = n;
		deck.seen[n] = true;
		var $d = $player.find('[data-deck]');
		var canvas = $d.find('[data-deck-canvas]')[0];
		$d.find('.tpd-lms-deck-loading').show();
		drawPage(deck.pdf, n, canvas, $d.find('.tpd-lms-deck-stage').width() || 800).then(function () { $d.find('.tpd-lms-deck-loading').hide(); });
		$d.find('[data-deck-count]').text('Slide ' + n + ' of ' + deck.total);
		$d.find('[data-deck-prev]').prop('disabled', n === 1);
		$d.find('[data-deck-next]').prop('disabled', n === deck.total);
		var slide = deck.p.slides[n - 1] || {};
		var $audio = $d.find('[data-deck-audio]');
		if (slide.audio) {
			$audio.prop('hidden', false).html('<i class="fa-solid fa-volume-high" aria-hidden="true"></i><audio controls autoplay preload="auto" src="' + esc(slide.audio) + '"></audio>');
			if (deck.p.auto_advance) {
				$audio.find('audio').on('ended', function () { if (deck && deck.n === n && n < deck.total) showSlide(n + 1); });
			}
		} else {
			$audio.prop('hidden', true).empty();
		}
		$d.find('[data-deck-text]').html(slide.text || '');
		var left = deck.total - Object.keys(deck.seen).length;
		if (left <= 0) {
			$player.find('[data-lms-complete]').prop('disabled', false);
			$player.find('[data-slides-left]').remove();
		} else {
			$player.find('[data-slides-left]').text(left + (left === 1 ? ' slide' : ' slides') + ' left to view.');
		}
	}

	/* Live session --------------------------------------------------------- */

	function renderLive(v) {
		if (!v || !v.starts_at) {
			return '<div class="tpd-lms-live"><p class="tpd-lms-muted">The date for this session hasn’t been set yet.</p>' +
				(v && v.join_url ? '<a class="tpd-btn tpd-btn-sm tpd-btn-darkblue" target="_blank" rel="noopener" href="' + esc(v.join_url) + '"><i class="fa-solid fa-video"></i> Join the session</a>' : '') + '</div>';
		}
		var start = new Date(v.starts_at), end = new Date(v.ends_at);
		var skew = new Date(v.now).getTime() - Date.now(); // Server clock decides.
		var now = Date.now() + skew;
		var opens = start.getTime() - 15 * 60 * 1000;
		var when = start.toLocaleString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' });
		var state = now < opens ? 'soon' : (now <= end.getTime() ? 'live' : 'over');
		var action = '';
		if (state === 'live' && v.join_url) action = '<a class="tpd-btn tpd-btn-sm tpd-btn-darkblue" style="background:#b91c1c;border-color:#b91c1c;" target="_blank" rel="noopener" href="' + esc(v.join_url) + '"><i class="fa-solid fa-tower-broadcast"></i> Join now</a>';
		else if (state === 'soon') action = '<span class="tpd-lms-muted">The join button appears 15 minutes before the start.</span>' + (v.join_url ? ' <a href="' + esc(v.join_url) + '" target="_blank" rel="noopener">Meeting link</a>' : '');
		else if (state === 'over') action = v.recording_url ? '<a class="tpd-btn tpd-btn-sm tpd-btn-darkblue" target="_blank" rel="noopener" href="' + esc(v.recording_url) + '"><i class="fa-solid fa-circle-play"></i> Watch the recording</a>' : '<span class="tpd-lms-muted">This session has ended. A recording will appear here if one is shared.</span>';
		return '<div class="tpd-lms-live is-' + state + '"><div class="tpd-lms-live-when"><i class="fa-regular fa-calendar" aria-hidden="true"></i><div><strong>' + esc(when) + '</strong>' +
			'<span class="tpd-lms-muted">' + (state === 'live' ? 'Happening now' : state === 'over' ? 'Finished' : 'Shown in your time zone') + '</span></div></div>' + action + '</div>';
	}

	/* Assignment ----------------------------------------------------------- */

	function renderAssignment(a) {
		if (!a) return '';
		if (!st.outline.enrolled) return '<p class="tpd-lms-muted">Enroll in the course to hand in this assignment.</p>';
		var last = a.latest;
		var status = '';
		if (last) {
			var labels = { pending: ['is-draft', 'Waiting for review'], approved: ['is-published', 'Approved'], returned: ['is-error', 'Returned for changes'] };
			var lb = labels[last.status] || ['is-draft', last.status];
			status = '<div class="tpd-lms-submission"><p><span class="tpd-lms-badge ' + lb[0] + '">' + lb[1] + '</span> <span class="tpd-lms-muted">Handed in ' + esc(new Date(last.created_at.replace(' ', 'T') + 'Z').toLocaleString()) + '</span></p>' +
				(last.file_url ? '<p><a href="' + esc(last.file_url) + '"><i class="fa-solid fa-paperclip"></i> ' + esc(last.file_name) + '</a></p>' : '') +
				(last.text ? '<div class="tpd-lms-answer">' + last.text + '</div>' : '') +
				(last.feedback ? '<div class="tpd-lms-feedback"><strong>Feedback</strong><p>' + esc(last.feedback) + '</p></div>' : '') + '</div>';
		}
		if (last && (last.status === 'pending' || last.status === 'approved')) return status;
		var accept = a.extensions.map(function (e) { return '.' + e; }).join(',');
		return status + '<form class="tpd-lms-assign-form" data-lms-assign enctype="multipart/form-data">' +
			'<h4 class="tpd-lms-p-h4">' + (last ? 'Send it again' : 'Hand in your work') + '</h4>' +
			(a.submission !== 'file' ? '<label class="tpd-lms-block">Your answer<textarea name="answer" class="tpd-input" rows="6"></textarea></label>' : '') +
			(a.submission !== 'text' ? '<label class="tpd-lms-block">File<input type="file" name="file" class="tpd-input" accept="' + esc(accept) + '"></label>' +
				'<p class="tpd-lms-muted">PDF, Word, PowerPoint, Excel, text or image. Up to ' + a.max_mb + ' MB.</p>' : '') +
			'<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-paper-plane"></i> Hand in</button>' +
			(a.require_approval ? ' <span class="tpd-lms-muted">The lesson completes when the course team approves it.</span>' : '') +
			'</form>';
	}

	function nextLesson() {
		var list = flatLessons();
		var i = list.findIndex(function (x) { return st.lesson && x.id === st.lesson.id; });
		for (var j = i + 1; j < list.length; j++) if (!list[j].locked) return list[j];
		return null;
	}

	function nextButton() {
		var n = nextLesson();
		if (n) return '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-lms-lesson="' + n.id + '">Next: ' + esc(n.title) + ' <i class="fa-solid fa-arrow-right"></i></button>';
		// End of the curriculum: point to the separate exam card's exam once it is unlocked.
		var x = st.outline && st.outline.exam_card;
		if (x && !x.locked && !x.passed && (!st.lesson || st.lesson.id !== x.lesson_id)) {
			return '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-lesson="' + x.lesson_id + '" style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-graduation-cap"></i> Go to the ' + esc(x.title) + '</button>';
		}
		return '';
	}

	/* ------------------------------------------------------------------ */
	/* Quizzes                                                             */
	/* ------------------------------------------------------------------ */

	function renderQuizIntro(q) {
		if (q.is_survey) {
			var sAction;
			if (!st.outline.enrolled) sAction = st.outline.preview ? '<p class="tpd-lms-muted">Course team preview: enroll a test account to answer the survey.</p>' : enrollButton();
			else if (q.attempts_used && !q.can_attempt) sAction = '<p class="tpd-lms-done"><i class="fa-solid fa-circle-check"></i> Thanks, your answers were sent.</p>' + nextButton();
			else if (q.question_count === 0) sAction = '<p class="tpd-lms-muted">This survey has no questions yet.</p>';
			else sAction = '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-quiz-start style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-play"></i> ' + (q.has_open ? 'Continue the survey' : 'Start the survey') + '</button>';
			return '<div class="tpd-lms-quiz-intro"><ul class="tpd-lms-facts"><li>' + q.question_count + (q.question_count === 1 ? ' question' : ' questions') + '</li><li>No right or wrong answers</li></ul>' +
				'<div class="tpd-lms-p-actions tpd-lms-p-actions-col">' + sAction + '</div></div>';
		}
		var facts = [];
		facts.push(q.question_count + (q.question_count === 1 ? ' question' : ' questions'));
		facts.push('Pass mark ' + q.passing_score + '%');
		if (q.time_limit) facts.push(q.time_limit + ' minute time limit');
		facts.push(q.max_attempts === null ? 'Unlimited attempts' : (q.max_attempts - q.attempts_used) + ' of ' + q.max_attempts + ' attempts left');
		var best = q.best_score === null ? '' : '<p class="tpd-lms-best ' + (q.best_score >= q.passing_score ? 'is-pass' : '') + '">Your best score: <strong>' + q.best_score + '%</strong>' + (q.best_score >= q.passing_score ? ' · Passed' : '') + '</p>';
		var action;
		if (!st.outline.enrolled) action = st.outline.preview ? '<p class="tpd-lms-muted">Course team preview: enroll a test account to try the quiz.</p>' : enrollButton();
		else if (q.question_count === 0) action = '<p class="tpd-lms-muted">This quiz has no questions yet.</p>';
		else if (!q.can_attempt) action = '<p class="tpd-lms-muted">You’ve used all your attempts.</p>';
		else {
			var access = '';
			if (q.needs_access) {
				var isCode = q.access_mode === 'code';
				access = '<div class="tpd-lms-access"><label for="tpd-lms-access-code">' + (isCode ? 'One-time exam code' : 'Exam password') + '</label>' +
					'<input type="' + (isCode ? 'text' : 'password') + '" id="tpd-lms-access-code" class="tpd-input" autocomplete="off"' + (isCode ? ' placeholder="ABCD-EFGH" style="text-transform:uppercase"' : '') + '>' +
					'<p class="tpd-lms-muted">' + (isCode ? 'Your proctor enters this for you when you’re ready to begin.' : 'Your proctor gives you the password.') + '</p></div>';
			}
			action = access + '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-quiz-start style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-play"></i> ' + (q.has_open ? 'Continue your attempt' : (q.attempts_used ? 'Try again' : (q.is_final ? 'Start the exam' : 'Start the quiz'))) + '</button>';
		}
		return '<div class="tpd-lms-quiz-intro"><ul class="tpd-lms-facts">' + facts.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul>' + best +
			(q.time_limit ? '<p class="tpd-lms-muted">The timer keeps running if you leave the page. Answers are saved as you go.</p>' : '') +
			'<div class="tpd-lms-p-actions tpd-lms-p-actions-col">' + action + '</div></div>' +
			(st.outline.enrolled && q.access_mode === 'code' && q.can_attempt ? renderProctorPanel(q) : '');
	}

	function renderProctorPanel(q) {
		var r = q.proctor_request;
		if (r && r.status === 'pending') {
			return '<div class="tpd-lms-proctor"><h4 class="tpd-lms-p-h4">Your proctor request</h4>' +
				'<p><span class="tpd-lms-badge is-draft">Waiting for approval</span> Proctor: <strong>' + esc(r.proctor) + '</strong>' + (r.preferred ? ' · ' + esc(r.preferred) : '') + '</p>' +
				'<p class="tpd-lms-muted">We’ll email you when the course team decides. Once approved, your proctor gets the code by email.</p>' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-lms-proctor-cancel="' + r.id + '">Cancel this request</button></div>';
		}
		if (r && r.status === 'approved' && r.code_status === 'active') {
			return '<div class="tpd-lms-proctor"><h4 class="tpd-lms-p-h4">Your proctor</h4>' +
				'<p><span class="tpd-lms-badge is-published">Approved</span> We emailed <strong>' + esc(r.proctor) + '</strong> your exam code. When you meet, open this exam and they’ll enter it.</p>' +
				(r.note ? '<p class="tpd-lms-muted">Note from the course team: ' + esc(r.note) + '</p>' : '') + '</div>';
		}
		var intro = r && r.status === 'declined' ? '<p><span class="tpd-lms-badge is-error">Not approved</span> ' + esc(r.note) + '</p><p class="tpd-lms-muted">You can send a new request.</p>' :
			'<p class="tpd-lms-muted">This exam needs a proctor: someone who watches you take it, such as a colleague at your host agency. Tell us who, and we’ll send them a one-time code once approved.</p>';
		var tz = (Intl.DateTimeFormat && Intl.DateTimeFormat().resolvedOptions().timeZone) || 'UTC';
		return '<form class="tpd-lms-proctor" data-lms-proctor-form><h4 class="tpd-lms-p-h4">Request a proctor</h4>' + intro +
			'<div class="tpd-lms-proctor-grid">' +
			'<label>Proctor’s name<input type="text" name="proctor_name" class="tpd-input" required maxlength="120"></label>' +
			'<label>Proctor’s email<input type="email" name="proctor_email" class="tpd-input" required maxlength="190"></label>' +
			'<label>Phone (optional)<input type="tel" name="proctor_phone" class="tpd-input" maxlength="40"></label>' +
			'<label>How you know them<input type="text" name="proctor_relationship" class="tpd-input" maxlength="120" placeholder="e.g. Colleague at my host agency"></label>' +
			'<label>Preferred date and time<input type="datetime-local" name="preferred_at" class="tpd-input"></label>' +
			'<input type="hidden" name="timezone" value="' + esc(tz) + '">' +
			'</div>' +
			'<label class="tpd-lms-block">Anything else we should know? (optional)<textarea name="student_notes" class="tpd-input" rows="2" maxlength="1000"></textarea></label>' +
			'<p class="tpd-lms-muted">Times are in your time zone (' + esc(tz) + ').</p>' +
			'<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-paper-plane"></i> Send request</button></form>';
	}

	function startQuiz() {
		var $b = $player.find('[data-lms-quiz-start]').prop('disabled', true);
		api('quiz_start', { lesson_id: st.lesson.id, access_code: $player.find('#tpd-lms-access-code').val() || '' }).then(function (attempt) {
			st.attempt = attempt;
			renderAttempt();
		}, function (msg) { $b.prop('disabled', false); flash(msg, 'error'); });
	}

	function renderAttempt() {
		var a = st.attempt;
		var qs = a.questions.map(function (q, i) {
			var given = a.answers[q.id];
			var input;
			if (q.type === 'short') {
				input = '<input type="text" class="tpd-input" data-q="' + q.id + '" value="' + esc(typeof given === 'string' ? given : '') + '" aria-label="Your answer to question ' + (i + 1) + '" autocomplete="off">';
			} else {
				var multi = q.type === 'multiple';
				var picked = (Array.isArray(given) ? given : (given !== undefined && given !== '' ? [given] : [])).map(Number);
				input = '<div class="tpd-lms-choices" role="' + (multi ? 'group' : 'radiogroup') + '" aria-labelledby="tpd-lms-q-' + q.id + '">' + q.options.map(function (o) {
					return '<label class="tpd-lms-choice"><input type="' + (multi ? 'checkbox' : 'radio') + '" name="q' + q.id + '" data-q="' + q.id + '" value="' + o.id + '"' + (picked.indexOf(o.id) !== -1 ? ' checked' : '') + '> <span>' + esc(o.text) + '</span></label>';
				}).join('') + '</div>';
				if (multi) input = '<p class="tpd-lms-muted">Select every correct answer.</p>' + input;
			}
			return '<fieldset class="tpd-lms-q"><legend id="tpd-lms-q-' + q.id + '"><span class="tpd-lms-q-n">' + (i + 1) + '</span><span class="tpd-lms-q-prompt">' + q.prompt + '</span></legend>' + input + '</fieldset>';
		}).join('');

		$player.find('.tpd-lms-p-main').html(
			'<div class="tpd-lms-p-lesson-head"><h3 class="tpd-lms-p-h3">' + esc(st.lesson.title) + '</h3>' +
			(a.seconds_remaining !== null ? '<div class="tpd-lms-timer" role="timer" aria-live="off"><i class="fa-regular fa-clock"></i> <span data-lms-timer></span></div>' : '') + '</div>' +
			'<form data-lms-attempt>' + qs +
			'<div class="tpd-lms-p-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-paper-plane"></i> Submit answers</button>' +
			'<span class="tpd-lms-muted" data-lms-saved></span></div></form>'
		);
		if (a.seconds_remaining !== null) startTimer(a.seconds_remaining);
	}

	function startTimer(seconds) {
		var deadline = Date.now() + seconds * 1000;
		function tick() {
			var left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
			var m = Math.floor(left / 60), s = left % 60;
			$player.find('[data-lms-timer]').text(m + ':' + (s < 10 ? '0' : '') + s + ' left');
			$player.find('.tpd-lms-timer').toggleClass('is-low', left <= 60);
			if (left === 0) {
				stopTimer();
				flash('Time is up. Your saved answers are being submitted.', 'error');
				submitQuiz(true);
			}
		}
		tick();
		st.timer = setInterval(tick, 1000);
	}

	function collectAnswers() {
		var answers = {};
		st.attempt.questions.forEach(function (q) {
			var $inputs = $player.find('[data-q="' + q.id + '"]');
			if (q.type === 'short') answers[q.id] = $inputs.val() || '';
			else if (q.type === 'multiple') answers[q.id] = $inputs.filter(':checked').map(function () { return this.value; }).get();
			else answers[q.id] = $inputs.filter(':checked').val() || '';
		});
		return answers;
	}

	function answersParam(answers) {
		var data = {};
		Object.keys(answers).forEach(function (qid) {
			var v = answers[qid];
			if (Array.isArray(v)) {
				if (!v.length) data['answers[' + qid + ']'] = '';
				v.forEach(function (x, i) { data['answers[' + qid + '][' + i + ']'] = x; });
			} else data['answers[' + qid + ']'] = v;
		});
		return data;
	}

	function autosave() {
		clearTimeout(st.saveT);
		st.saveT = setTimeout(function () {
			if (!st.attempt) return;
			api('quiz_save', $.extend({ attempt_id: st.attempt.attempt_id }, answersParam(collectAnswers()))).then(function (r) {
				$player.find('[data-lms-saved]').text(r.saved ? 'Answers saved' : '');
			});
		}, 700);
	}

	function submitQuiz(auto) {
		if (!st.attempt) return;
		var answers = collectAnswers();
		if (!auto) {
			var blank = st.attempt.questions.filter(function (q) {
				var v = answers[q.id];
				return Array.isArray(v) ? !v.length : !$.trim(v);
			}).length;
			if (blank && !window.confirm(blank + (blank === 1 ? ' question is' : ' questions are') + ' unanswered. Submit anyway?')) return;
		}
		stopTimer();
		var $b = $player.find('[data-lms-attempt] [type=submit]').prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Grading…');
		api('quiz_submit', $.extend({ attempt_id: st.attempt.attempt_id }, answersParam(answers))).then(function (data) {
			st.attempt = null;
			st.dirty = true;
			st.outline = data.outline;
			st.lesson.quiz = data.quiz;
			st.lesson.done = data.result.passed || st.lesson.done;
			refreshPath();
			renderResult(data.result);
		}, function (msg) { $b.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Submit answers'); flash(msg, 'error'); });
	}

	function renderResult(r) {
		if (r.survey) {
			$player.find('.tpd-lms-p-main').html(
				'<div class="tpd-lms-result is-pass"><div class="tpd-lms-score"><i class="fa-solid fa-circle-check"></i></div>' +
				'<div><h3 class="tpd-lms-p-h3">Thanks for your answers</h3><p>Your feedback helps us improve the course.</p></div></div>' +
				'<div class="tpd-lms-p-actions">' + nextButton() + '</div>');
			return;
		}
		var review = r.review.length ? '<h4 class="tpd-lms-p-h4">Review your answers</h4><ol class="tpd-lms-review">' + r.review.map(function (it) {
			return '<li class="' + (it.ok ? 'is-ok' : 'is-wrong') + '"><div class="tpd-lms-q-prompt">' + it.prompt + '</div>' +
				'<p><strong>' + (it.ok ? '<i class="fa-solid fa-check"></i> Correct' : '<i class="fa-solid fa-xmark"></i> Not quite') + '</strong>' +
				(it.ok ? '' : ' · Your answer: ' + esc(it.given || 'none') + ' · Correct: ' + esc(it.correct)) + '</p>' +
				(it.explanation ? '<div class="tpd-lms-muted">' + it.explanation + '</div>' : '') + '</li>';
		}).join('') + '</ol>' : '';
		var q = st.lesson.quiz;
		var cert = st.outline.certificate && r.passed ? '<a class="tpd-btn tpd-btn-sm tpd-btn-outline" target="_blank" rel="noopener" href="' + esc(st.outline.certificate.url) + '"><i class="fa-solid fa-certificate text-gold"></i> View your certificate</a>' : '';
		$player.find('.tpd-lms-p-main').html(
			'<div class="tpd-lms-result ' + (r.passed ? 'is-pass' : 'is-fail') + '">' +
			'<div class="tpd-lms-score">' + r.score + '%</div>' +
			'<div><h3 class="tpd-lms-p-h3">' + (r.passed ? 'You passed. Well done!' : 'Not passed this time') + '</h3>' +
			'<p>' + r.points_earned + ' of ' + r.points_total + ' points. The pass mark is ' + r.passing_score + '%.</p></div></div>' +
			'<div class="tpd-lms-p-actions">' + cert + (r.passed ? nextButton() : (q && q.can_attempt ? '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-quiz-start style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-rotate-right"></i> Try again</button>' : '<span class="tpd-lms-muted">No attempts left.</span>')) + '</div>' +
			review
		);
	}

	/* ------------------------------------------------------------------ */
	/* Coupons in TPD's checkout modal (course purchases only)             */
	/* ------------------------------------------------------------------ */

	// TPD's checkout script stays untouched: the code is added to its own requests here.
	var coupon = { item: null, code: '' };

	$.ajaxPrefilter(function (options) {
		if (!coupon.code || typeof options.data !== 'string') return;
		if (options.data.indexOf('action=tpd_get_checkout_preview') === -1 && options.data.indexOf('action=tpd_initiate_checkout') === -1) return;
		if (options.data.indexOf('item_type=lms_course') === -1) return;
		options.data += '&lms_coupon=' + encodeURIComponent(coupon.code);
	});

	$(document).ajaxSuccess(function (e, xhr, settings) {
		if (typeof settings.data !== 'string' || settings.data.indexOf('action=tpd_get_checkout_preview') === -1) return;
		var res = xhr.responseJSON;
		var item = res && res.success && res.data && res.data.item;
		$('#tpd-lms-coupon').remove();
		if (!item || item.item_type !== 'lms_course') return;
		if (coupon.item !== item.item_id) coupon = { item: item.item_id, code: '' };
		var listed = item.extra && item.extra.list_price;
		if (!(listed > 0)) return; // Free or included with the plan: nothing to discount.

		var msg = item.coupon_ok ? '<span class="tpd-lms-coupon-ok"><i class="fa-solid fa-tag"></i> ' + esc(item.coupon_ok) + ' applied</span>' :
			item.coupon_error ? '<span class="tpd-lms-coupon-err" role="alert">' + esc(item.coupon_error) + '</span>' : '';
		var $row = $('<div id="tpd-lms-coupon" class="tpd-lms-coupon">' +
			'<label for="tpd-lms-coupon-code">Coupon code</label>' +
			'<div class="tpd-lms-coupon-row"><input type="text" id="tpd-lms-coupon-code" class="tpd-input" autocomplete="off" value="' + esc(coupon.code) + '" placeholder="Optional">' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-lms-coupon-apply>' + (item.coupon_ok ? 'Change' : 'Apply') + '</button>' +
			(coupon.code ? '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-lms-coupon-clear>Remove</button>' : '') + '</div>' + msg + '</div>');
		var $gateways = $('#tpd-co-gateways-wrap');
		if ($gateways.length) $row.insertBefore($gateways); else $row.insertBefore('#tpd-co-status-msg');
		if (item.coupon_ok && item.list_amount) {
			var sym = $('#tpd-co-item-price').data('symbol') || '$';
			$('#tpd-co-item-price').prepend('<s class="tpd-lms-was">' + esc(sym + Number(item.list_amount).toFixed(2)) + '</s> ');
		}
		if (item.coupon_error) coupon.code = ''; // Don't send a bad code with the payment.
	});

	function reloadCheckout() {
		if (typeof window.tpdOpenCheckoutModal === 'function') window.tpdOpenCheckoutModal('lms_course', coupon.item, 'once', $('input[name="tpd_co_gateway"]:checked').val() || '');
	}

	$(document)
		.on('click', '[data-lms-coupon-apply]', function () {
			coupon.code = $.trim($('#tpd-lms-coupon-code').val());
			reloadCheckout();
		})
		.on('keydown', '#tpd-lms-coupon-code', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); $('[data-lms-coupon-apply]').trigger('click'); }
		})
		.on('click', '[data-lms-coupon-clear]', function () {
			coupon.code = '';
			reloadCheckout();
		})
		.on('click', '.tpd-open-checkout-btn', function () {
			if (String($(this).data('item-id')) !== coupon.item) coupon = { item: null, code: '' };
		});

	/* ------------------------------------------------------------------ */
	/* Events                                                              */
	/* ------------------------------------------------------------------ */

	$(function () {
		$hub = $('#tpd-resources-hub-root');
		$player = $('#tpd-lms-player');
		if (!$hub.length || !$player.length) return;

		$(document)
			.on('click', '.tpd-lms-launch', function (e) {
				e.preventDefault();
				openCourse(String($(this).data('redirect')), Number($(this).data('lesson')) || null);
			})
			.on('submit', '[data-exam-unlock]', function (e) {
				e.preventDefault();
				var $f = $(this), $card = $f.closest('[data-exam-card]'), $msg = $f.find('.tpd-lms-unlock-msg');
				var $b = $f.find('[type=submit]').prop('disabled', true);
				api('exam_unlock', { course: $f.data('exam-unlock'), key: $f.find('[name=key]').val() }).then(function (r) {
					var x = r.exam_card;
					$card.removeClass('is-locked');
					$card.find('.tpd-lms-exam-icon i').attr('class', 'fa-solid fa-lock-open');
					$card.find('.tpd-lms-exam-status').attr('class', 'tpd-lms-exam-status is-open').html('<i class="fa-solid fa-lock-open"></i> Unlocked. Good luck!');
					$f.replaceWith('<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-btn-block tpd-lms-launch" data-redirect="' + esc(x.course_slug) + '" data-lesson="' + x.lesson_id + '" style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-play"></i> Start the exam</button>');
				}, function (msg) {
					$b.prop('disabled', false);
					$msg.text(msg).prop('hidden', false);
				});
			})
			.on('click', '[data-lms-close]', closePlayer)
			.on('click', '#tpd-lms-player [data-lms-lesson]', function () { openLesson(Number($(this).data('lms-lesson'))); })
			.on('click', '[data-lms-complete]', function () {
				var $b = $(this).prop('disabled', true);
				api('complete', { lesson_id: st.lesson.id }).then(function (outline) {
					st.outline = outline;
					st.dirty = true;
					st.lesson.done = true;
					refreshPath();
					var n = nextLesson();
					if (n) openLesson(n.id);
					else { renderLesson(); flash(outline.progress === 100 ? 'Course complete. Congratulations!' : 'Lesson completed.'); }
				}, function (msg) { $b.prop('disabled', false); flash(msg, 'error'); });
			})
			.on('click', '[data-deck-prev]', function () { if (deck) showSlide(deck.n - 1); })
			.on('click', '[data-deck-next]', function () { if (deck) showSlide(deck.n + 1); })
			.on('keydown', '[data-deck]', function (e) {
				if (!deck) return;
				if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); showSlide(deck.n + 1); }
				if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); showSlide(deck.n - 1); }
			})
			.on('click', '[data-deck-full]', function () {
				var el = $player.find('[data-deck]')[0];
				if (document.fullscreenElement) document.exitFullscreen();
				else if (el && el.requestFullscreen) el.requestFullscreen().then(function () { if (deck) showSlide(deck.n); });
			})
			.on('submit', '[data-lms-assign]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var fd = new FormData(this);
				fd.append('action', 'tpd_lms_s_assignment_submit');
				fd.append('nonce', tpdLmsStudent.nonce);
				fd.append('lesson_id', st.lesson.id);
				$b.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Sending…');
				$.ajax({ url: tpdLmsStudent.ajaxUrl, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' }).then(function (res) {
					if (!res || !res.success) return $.Deferred().reject(res && res.data && res.data.message ? res.data.message : 'Something went wrong. Try again.').promise();
					st.lesson = res.data.lesson;
					st.outline = res.data.outline;
					st.dirty = true;
					refreshPath();
					renderLesson();
					flash(st.lesson.done ? 'Handed in. The lesson is complete.' : 'Handed in. We’ll email you when it’s reviewed.');
				}).then(null, function (msg) {
					var m = typeof msg === 'string' ? msg : (msg && msg.responseJSON && msg.responseJSON.data && msg.responseJSON.data.message) || 'The upload failed. Check the file and try again.';
					$b.prop('disabled', false).html('<i class="fa-solid fa-paper-plane"></i> Hand in');
					flash(m, 'error');
				});
			})
			.on('click', '[data-lms-quiz-start]', startQuiz)
			.on('keydown', '#tpd-lms-access-code', function (e) { if (e.key === 'Enter') { e.preventDefault(); startQuiz(); } })
			.on('submit', '[data-lms-proctor-form]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]').prop('disabled', true);
				var data = { lesson_id: st.lesson.id };
				$f.find('[name]').each(function () { data[this.name] = $(this).val(); });
				api('proctor_request', data).then(function (r) {
					st.lesson.quiz = r.quiz;
					renderLesson();
					flash('Request sent. We’ll email you when it’s decided.');
				}, function (msg) { $b.prop('disabled', false); flash(msg, 'error'); });
			})
			.on('click', '[data-lms-proctor-cancel]', function () {
				if (!window.confirm('Cancel this proctor request?')) return;
				api('proctor_cancel', { request_id: $(this).data('lms-proctor-cancel') }).then(function (r) {
					st.lesson.quiz = r.quiz;
					renderLesson();
				}, function (msg) { flash(msg, 'error'); });
			})
			.on('change input', '[data-lms-attempt] [data-q]', autosave)
			.on('submit', '[data-lms-attempt]', function (e) { e.preventDefault(); submitQuiz(false); });

		// Open a course from a link like /advisor-dashboard/?lms_course=slug#tpd-view-resources
		var params = new URLSearchParams(window.location.search);
		if (params.get('lms_course')) {
			setTimeout(function () {
				$('.tpd-tab-link[data-view="resources"]').first().trigger('click');
				openCourse(params.get('lms_course'));
			}, 300);
		}
	});
})(jQuery);
