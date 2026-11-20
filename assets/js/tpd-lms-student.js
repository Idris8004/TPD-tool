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
	var TYPE_ICON = { text: 'fa-file-lines', video: 'fa-circle-play', download: 'fa-file-arrow-down', quiz: 'fa-list-check', exam: 'fa-graduation-cap' };

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
		return { text: 'Reading', video: 'Video', download: 'Download', quiz: 'Quiz', exam: 'Final exam' }[t] || 'Lesson';
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
		if (l.file) {
			body += '<div class="tpd-lms-file"><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i><span>' + esc(l.file.name) + '</span>' +
				'<a class="tpd-btn tpd-btn-sm tpd-btn-darkblue" href="' + esc(l.file.url) + '"><i class="fa-solid fa-download"></i> Download</a></div>';
		}
		if (l.content) body += '<div class="tpd-lms-content">' + l.content + '</div>';

		var footer = '';
		if (l.quiz) {
			body += renderQuizIntro(l.quiz);
		} else if (l.can_mark) {
			footer = l.done ?
				'<span class="tpd-lms-done"><i class="fa-solid fa-circle-check"></i> Completed</span>' + nextButton() :
				'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-complete style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-check"></i> Mark complete and continue</button>';
		} else {
			footer = nextButton();
		}

		$player.find('.tpd-lms-p-main').html(
			'<div class="tpd-lms-p-lesson-head"><span class="tpd-lms-muted"><i class="fa-solid ' + (TYPE_ICON[l.type] || 'fa-file-lines') + '"></i> ' + esc(typeLabel(l.type)) + (l.minutes ? ' · ' + l.minutes + ' min' : '') + '</span>' +
			'<h3 class="tpd-lms-p-h3">' + esc(l.title) + '</h3></div>' +
			body +
			(footer ? '<div class="tpd-lms-p-actions">' + footer + '</div>' : '')
		);
	}

	function nextLesson() {
		var list = flatLessons();
		var i = list.findIndex(function (x) { return st.lesson && x.id === st.lesson.id; });
		for (var j = i + 1; j < list.length; j++) if (!list[j].locked) return list[j];
		return null;
	}

	function nextButton() {
		var n = nextLesson();
		return n ? '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-lms-lesson="' + n.id + '">Next: ' + esc(n.title) + ' <i class="fa-solid fa-arrow-right"></i></button>' : '';
	}

	/* ------------------------------------------------------------------ */
	/* Quizzes                                                             */
	/* ------------------------------------------------------------------ */

	function renderQuizIntro(q) {
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
		else action = '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-lms-quiz-start style="background:#00798c;border-color:#00798c;"><i class="fa-solid fa-play"></i> ' + (q.has_open ? 'Continue your attempt' : (q.attempts_used ? 'Try again' : (q.is_final ? 'Start the exam' : 'Start the quiz'))) + '</button>';
		return '<div class="tpd-lms-quiz-intro"><ul class="tpd-lms-facts">' + facts.map(function (f) { return '<li>' + esc(f) + '</li>'; }).join('') + '</ul>' + best +
			(q.time_limit ? '<p class="tpd-lms-muted">The timer keeps running if you leave the page. Answers are saved as you go.</p>' : '') +
			'<div class="tpd-lms-p-actions">' + action + '</div></div>';
	}

	function startQuiz() {
		var $b = $player.find('[data-lms-quiz-start]').prop('disabled', true);
		api('quiz_start', { lesson_id: st.lesson.id }).then(function (attempt) {
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
	/* Events                                                              */
	/* ------------------------------------------------------------------ */

	$(function () {
		$hub = $('#tpd-resources-hub-root');
		$player = $('#tpd-lms-player');
		if (!$hub.length || !$player.length) return;

		$(document)
			.on('click', '.tpd-lms-launch', function (e) {
				e.preventDefault();
				openCourse(String($(this).data('redirect')));
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
			.on('click', '[data-lms-quiz-start]', startQuiz)
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
