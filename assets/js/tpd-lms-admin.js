/**
 * TPD native LMS: course builder (Super Admin → LMS Studio).
 * Views: course list → course (settings + curriculum) → lesson (content or quiz).
 * Talks to includes/lms/class-tpd-lms-admin.php. Every value shown is escaped.
 */
(function ($) {
	'use strict';

	if (typeof tpdLmsAdmin === 'undefined') return;

	var $root, $crumbs, $notice;
	var state = { view: 'list', courses: [], tree: null, lesson: null };
	var editors = [];

	var LESSON_TYPES = {
		text: { label: 'Text', icon: 'fa-file-lines' },
		video: { label: 'Video', icon: 'fa-circle-play' },
		download: { label: 'Download', icon: 'fa-file-arrow-down' },
		quiz: { label: 'Quiz', icon: 'fa-list-check' },
		exam: { label: 'Final exam', icon: 'fa-graduation-cap' }
	};
	var QUESTION_TYPES = {
		single: 'Single choice',
		multiple: 'Multiple select (all or nothing)',
		true_false: 'True or false',
		short: 'Short answer'
	};

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	function esc(v) {
		return String(v === null || v === undefined ? '' : v)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
	}

	function api(action, data) {
		data = $.extend({ action: 'tpd_lms_' + action, nonce: tpdLmsAdmin.nonce }, data || {});
		return $.ajax({ url: tpdLmsAdmin.ajaxUrl, type: 'POST', data: data, dataType: 'json' })
			.then(function (res) {
				if (res && res.success) return res.data;
				return $.Deferred().reject(res && res.data && res.data.message ? res.data.message : 'Something went wrong. Try again.').promise();
			}, function (xhr) {
				var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				return $.Deferred().reject(msg || 'The server could not be reached. Check your connection and try again.').promise();
			});
	}

	/** Same as api(), but sends multipart form data (for file uploads). */
	function apiForm(action, formData) {
		formData.append('action', 'tpd_lms_' + action);
		formData.append('nonce', tpdLmsAdmin.nonce);
		return $.ajax({ url: tpdLmsAdmin.ajaxUrl, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
			.then(function (res) {
				if (res && res.success) return res.data;
				return $.Deferred().reject(res && res.data && res.data.message ? res.data.message : 'Something went wrong. Try again.').promise();
			}, function (xhr) {
				var msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
				return $.Deferred().reject(msg || 'The server could not be reached. Check your connection and try again.').promise();
			});
	}

	function notify(message, kind) {
		$notice.removeClass('is-error is-ok').addClass(kind === 'error' ? 'is-error' : 'is-ok')
			.text(message).prop('hidden', false);
		clearTimeout(notify.t);
		notify.t = setTimeout(function () { $notice.prop('hidden', true); }, kind === 'error' ? 8000 : 3500);
	}

	function busy($btn, on) {
		if (!$btn || !$btn.length) return;
		if (on) {
			$btn.data('label', $btn.html()).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin"></i> Saving…');
		} else {
			$btn.prop('disabled', false).html($btn.data('label'));
		}
	}

	function money(n) {
		n = Number(n || 0);
		return n > 0 ? '$' + n.toFixed(2) : 'Free';
	}

	function numOrBlank(v) {
		return v === null || v === undefined ? '' : v;
	}

	function removeEditors() {
		editors.forEach(function (id) {
			if (window.wp && wp.editor && wp.editor.remove) wp.editor.remove(id);
		});
		editors = [];
	}

	function addEditor(id) {
		if (!(window.wp && wp.editor && wp.editor.initialize)) return;
		wp.editor.initialize(id, {
			tinymce: {
				wpautop: true,
				toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
				height: 260
			},
			quicktags: true,
			mediaButtons: false
		});
		editors.push(id);
	}

	function editorValue(id) {
		if (window.wp && wp.editor && wp.editor.getContent && editors.indexOf(id) !== -1) {
			return wp.editor.getContent(id);
		}
		return $('#' + id).val();
	}

	function pickMedia(title, type, done) {
		if (!(window.wp && wp.media)) {
			notify('The media library is not available on this page.', 'error');
			return;
		}
		var frame = wp.media({ title: title, multiple: false, library: type ? { type: type } : {} });
		frame.on('select', function () {
			done(frame.state().get('selection').first().toJSON());
		});
		frame.open();
	}

	function checkbox(name, label, checked, help) {
		return '<label class="tpd-lms-check"><input type="checkbox" name="' + esc(name) + '" value="1"' + (checked ? ' checked' : '') + '> ' +
			'<span>' + esc(label) + (help ? '<small>' + esc(help) + '</small>' : '') + '</span></label>';
	}

	function field(label, inner, help) {
		return '<div class="tpd-form-group tpd-lms-field"><label>' + esc(label) + '</label>' + inner +
			(help ? '<p class="tpd-lms-help">' + esc(help) + '</p>' : '') + '</div>';
	}

	function crumbs(items) {
		$crumbs.html(items.map(function (it, i) {
			if (i === items.length - 1) return '<span aria-current="page">' + esc(it.label) + '</span>';
			return '<button type="button" class="tpd-lms-crumb" data-go="' + esc(it.go) + '">' + esc(it.label) + '</button>';
		}).join('<i class="fa-solid fa-chevron-right" aria-hidden="true"></i>'));
	}

	/* ------------------------------------------------------------------ */
	/* View: course list                                                   */
	/* ------------------------------------------------------------------ */

	function showList() {
		removeEditors();
		state.view = 'list';
		crumbs([{ label: 'Courses' }]);
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading courses…</div>');
		api('course_list').then(function (data) {
			state.courses = data.courses;
			state.settings = data.settings;
			renderList();
		}, function (msg) {
			$root.html('<div class="tpd-sa-card">' + esc(msg) + '</div>');
		});
	}

	function renderList() {
		var rows = state.courses.map(function (c) {
			return '<tr>' +
				'<td><button type="button" class="tpd-lms-link" data-open-course="' + c.id + '">' + esc(c.title) + '</button>' +
				(c.subtitle ? '<div class="tpd-lms-muted">' + esc(c.subtitle) + '</div>' : '') + '</td>' +
				'<td><span class="tpd-lms-badge is-' + esc(c.status) + '">' + (c.status === 'published' ? 'Published' : 'Draft') + '</span></td>' +
				'<td>' + c.lesson_count + '</td>' +
				'<td>' + c.student_count + '</td>' +
				'<td>' + esc(money(c.price)) + '</td>' +
				'<td class="tpd-lms-actions">' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-open-course="' + c.id + '"><i class="fa-solid fa-pen"></i> Edit</button> ' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-lms-danger" data-delete-course="' + c.id + '" data-title="' + esc(c.title) + '"><i class="fa-solid fa-trash"></i> Delete</button>' +
				'</td></tr>';
		}).join('');

		$root.html(
			'<div class="tpd-sa-card">' +
			'<div class="tpd-lms-bar"><h3 class="tpd-sa-card-title">Courses</h3>' +
			'<span class="tpd-lms-bar-actions"><button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-go="coupons"><i class="fa-solid fa-tag"></i> Coupons</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-go="proctoring"><i class="fa-solid fa-user-shield"></i> Proctoring' + (state.settings && state.settings.pending_proctor ? ' <span class="tpd-lms-count">' + state.settings.pending_proctor + '</span>' : '') + '</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" id="tpd-lms-new-course"><i class="fa-solid fa-plus"></i> New course</button></span></div>' +
			(state.courses.length ?
				'<div class="tpd-lms-table-wrap"><table class="tpd-lms-table"><thead><tr><th>Course</th><th>Status</th><th>Lessons</th><th>Students</th><th>Price</th><th aria-label="Actions"></th></tr></thead><tbody>' + rows + '</tbody></table></div>' :
				'<div class="tpd-lms-empty"><i class="fa-solid fa-book-open"></i><p>No courses yet. Create the first one, then add modules and lessons.</p></div>') +
			'</div>' +
			'<div class="tpd-sa-card" id="tpd-lms-new-course-form" hidden>' +
			'<h3 class="tpd-sa-card-title">New course</h3>' +
			'<form class="tpd-lms-form" data-form="new-course">' +
			field('Title', '<input type="text" name="title" class="tpd-input" required maxlength="255" placeholder="e.g. Recognized Travel Advisor (RTA) Certification Program">') +
			field('Subtitle', '<input type="text" name="subtitle" class="tpd-input" maxlength="255" placeholder="One line on what advisors will learn">') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue">Create course</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-cancel-new>Cancel</button></div>' +
			'<p class="tpd-lms-help">New courses start as drafts. Advisors see a course only once you publish it.</p>' +
			'</form></div>' +
			'<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">Certificates and emails</h3>' +
			'<form class="tpd-lms-form tpd-lms-inline-settings" data-form="settings">' +
			field('Name printed on certificates', '<input type="text" name="issuer_name" class="tpd-input" required maxlength="120" value="' + esc(state.settings ? state.settings.issuer_name : '') + '">') +
			field('Support email', '<input type="email" name="support_email" class="tpd-input" maxlength="190" value="' + esc(state.settings ? state.settings.support_email : '') + '">', 'Gets new proctor requests and problem reports. Learner emails list it as the reply-to address.') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-outline"><i class="fa-solid fa-floppy-disk"></i> Save</button></div>' +
			(state.settings ? '<p class="tpd-lms-help">Anyone can check a certificate at <a href="' + esc(state.settings.verify_url) + '" target="_blank" rel="noopener">' + esc(state.settings.verify_url) + '</a>.</p>' : '') +
			'</form></div>'
		);
	}

	/* ------------------------------------------------------------------ */
	/* View: coupons                                                       */
	/* ------------------------------------------------------------------ */

	function showCoupons() {
		removeEditors();
		state.view = 'coupons';
		crumbs([{ label: 'Courses', go: 'list' }, { label: 'Coupons' }]);
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading coupons…</div>');
		api('coupon_list').then(renderCoupons, function (msg) { notify(msg, 'error'); showList(); });
	}

	function renderCoupons(data) {
		var rows = data.coupons.map(function (c) {
			return '<tr><td><strong>' + esc(c.code) + '</strong></td><td>' + esc(c.label) + '</td><td>' + esc(c.course) + '</td>' +
				'<td>' + c.uses + (c.max_uses !== null ? ' of ' + c.max_uses : '') + '</td><td>' + esc(c.expires_at || 'Never') + '</td>' +
				'<td><span class="tpd-lms-badge ' + (c.active ? 'is-published' : 'is-draft') + '">' + (c.active ? 'Active' : 'Off') + '</span></td>' +
				'<td class="tpd-lms-actions"><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-coupon-toggle="' + c.id + '">' + (c.active ? 'Turn off' : 'Turn on') + '</button> ' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-lms-danger" data-coupon-delete="' + c.id + '" data-code="' + esc(c.code) + '"><i class="fa-solid fa-trash"></i></button></td></tr>';
		}).join('');
		var courses = '<option value="">All courses</option>' + data.courses.map(function (c) { return '<option value="' + c.id + '">' + esc(c.title) + '</option>'; }).join('');
		$root.html(
			'<div class="tpd-lms-grid">' +
			'<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">Coupons</h3>' +
			(rows ? '<div class="tpd-lms-table-wrap"><table class="tpd-lms-table"><thead><tr><th>Code</th><th>Discount</th><th>Course</th><th>Used</th><th>Expires</th><th>Status</th><th aria-label="Actions"></th></tr></thead><tbody>' + rows + '</tbody></table></div>' :
				'<div class="tpd-lms-empty"><i class="fa-solid fa-tag"></i><p>No coupons yet. Advisors enter a code at checkout.</p></div>') +
			'</div>' +
			'<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">New coupon</h3><form class="tpd-lms-form" data-form="coupon">' +
			field('Code', '<input type="text" name="code" class="tpd-input" required maxlength="60" placeholder="e.g. WELCOME20" style="text-transform:uppercase">', 'Letters, numbers, dashes. Advisors type it at checkout.') +
			'<div class="tpd-form-grid-2">' +
			field('Discount type', '<select name="type" class="tpd-input"><option value="percent">Percent off</option><option value="fixed">Amount off (USD)</option></select>') +
			field('Discount', '<input type="number" name="value" class="tpd-input" required min="0.01" step="0.01" placeholder="20">') +
			'</div>' +
			field('Course', '<select name="course_id" class="tpd-input">' + courses + '</select>') +
			'<div class="tpd-form-grid-2">' +
			field('Use limit', '<input type="number" name="max_uses" class="tpd-input" min="1" placeholder="No limit">') +
			field('Last day it works', '<input type="date" name="expires_at" class="tpd-input">') +
			'</div>' +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-plus"></i> Create coupon</button></div>' +
			'</form></div></div>'
		);
	}

	/* ------------------------------------------------------------------ */
	/* View: course (settings + curriculum)                                */
	/* ------------------------------------------------------------------ */

	function openCourse(id) {
		removeEditors();
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading course…</div>');
		api('course_get', { course_id: id }).then(function (tree) {
			state.tree = tree;
			renderCourse();
		}, function (msg) {
			notify(msg, 'error');
			showList();
		});
	}

	function renderCourse() {
		removeEditors();
		state.view = 'course';
		var c = state.tree.course;
		crumbs([{ label: 'Courses', go: 'list' }, { label: c.title }]);

		$root.html(
			'<div class="tpd-lms-grid">' +
			renderCourseShellCurriculum() +
			'<div class="tpd-sa-card tpd-lms-settings">' + renderCourseSettings(c) + '</div>' +
			'</div>'
		);
		addEditor('tpd-lms-course-description');
	}

	function renderCurriculum() {
		var chapters = state.tree.chapters;
		if (!chapters.length) {
			return '<div class="tpd-lms-empty"><i class="fa-solid fa-layer-group"></i><p>Start with a module, then add lessons to it.</p></div>';
		}
		return chapters.map(function (ch, ci) {
			var lessons = ch.lessons.map(function (l, li) {
				var t = LESSON_TYPES[l.type] || LESSON_TYPES.text;
				var meta = [];
				if (l.question_count !== null) meta.push(l.question_count + (l.question_count === 1 ? ' question' : ' questions'));
				if (l.is_preview) meta.push('Free preview');
				if (l.drip_days) meta.push('Opens day ' + l.drip_days);
				return '<li class="tpd-lms-lesson">' +
					'<i class="fa-solid ' + t.icon + ' tpd-lms-lesson-icon" title="' + esc(t.label) + '" aria-hidden="true"></i>' +
					'<button type="button" class="tpd-lms-link" data-open-lesson="' + l.id + '">' + esc(l.title) + '</button>' +
					'<span class="tpd-lms-muted">' + esc(t.label) + (meta.length ? ' · ' + esc(meta.join(' · ')) : '') + '</span>' +
					'<span class="tpd-lms-move">' +
					'<button type="button" class="tpd-lms-icon-btn" data-move-lesson="' + l.id + '" data-dir="-1" title="Move up" aria-label="Move ' + esc(l.title) + ' up"' + (ci === 0 && li === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button>' +
					'<button type="button" class="tpd-lms-icon-btn" data-move-lesson="' + l.id + '" data-dir="1" title="Move down" aria-label="Move ' + esc(l.title) + ' down"' + (ci === chapters.length - 1 && li === ch.lessons.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button>' +
					'</span></li>';
			}).join('');

			var typeOptions = Object.keys(LESSON_TYPES).filter(function (k) {
				return k !== 'exam' || !state.tree.course.has_final_exam;
			}).map(function (k) {
				return '<option value="' + k + '">' + esc(LESSON_TYPES[k].label) + '</option>';
			}).join('');

			return '<section class="tpd-lms-chapter" data-chapter="' + ch.id + '">' +
				'<header class="tpd-lms-chapter-head">' +
				'<strong class="tpd-lms-chapter-title">' + esc(ch.title) + '</strong>' +
				'<span class="tpd-lms-move">' +
				'<button type="button" class="tpd-lms-icon-btn" data-move-chapter="' + ch.id + '" data-dir="-1" title="Move module up" aria-label="Move module ' + esc(ch.title) + ' up"' + (ci === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button>' +
				'<button type="button" class="tpd-lms-icon-btn" data-move-chapter="' + ch.id + '" data-dir="1" title="Move module down" aria-label="Move module ' + esc(ch.title) + ' down"' + (ci === chapters.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button>' +
				'<button type="button" class="tpd-lms-icon-btn" data-rename-chapter="' + ch.id + '" data-title="' + esc(ch.title) + '" title="Rename module" aria-label="Rename module ' + esc(ch.title) + '"><i class="fa-solid fa-pen"></i></button>' +
				'<button type="button" class="tpd-lms-icon-btn tpd-lms-danger" data-delete-chapter="' + ch.id + '" data-title="' + esc(ch.title) + '" data-count="' + ch.lessons.length + '" title="Delete module" aria-label="Delete module ' + esc(ch.title) + '"><i class="fa-solid fa-trash"></i></button>' +
				'</span></header>' +
				(lessons ? '<ol class="tpd-lms-lessons">' + lessons + '</ol>' : '<p class="tpd-lms-muted tpd-lms-pad">No lessons in this module yet.</p>') +
				'<form class="tpd-lms-inline-form" data-form="new-lesson" data-chapter="' + ch.id + '">' +
				'<select name="type" class="tpd-input" aria-label="Lesson type">' + typeOptions + '</select>' +
				'<input type="text" name="title" class="tpd-input" required maxlength="255" placeholder="Lesson title" aria-label="New lesson title">' +
				'<button type="submit" class="tpd-btn tpd-btn-xs tpd-btn-outline"><i class="fa-solid fa-plus"></i> Add lesson</button>' +
				'</form></section>';
		}).join('');
	}

	function renderCourseSettings(c) {
		var tiers = Object.keys(tpdLmsAdmin.tiers).map(function (k) {
			return '<option value="' + esc(k) + '"' + (c.tier_included === k ? ' selected' : '') + '>' + esc(tpdLmsAdmin.tiers[k]) + '</option>';
		}).join('');
		var levels = ['Beginner', 'Intermediate', 'Advanced', 'All levels'].map(function (l) {
			return '<option' + (c.level === l ? ' selected' : '') + '>' + esc(l) + '</option>';
		}).join('');

		return '<h3 class="tpd-sa-card-title">Course settings</h3>' +
			'<form class="tpd-lms-form" data-form="course">' +
			field('Title', '<input type="text" name="title" class="tpd-input" required maxlength="255" value="' + esc(c.title) + '">') +
			field('Subtitle', '<input type="text" name="subtitle" class="tpd-input" maxlength="255" value="' + esc(c.subtitle) + '">') +
			field('Description', '<textarea id="tpd-lms-course-description" name="description" rows="8" class="tpd-input">' + esc(c.description) + '</textarea>') +
			field('Cover image',
				'<div class="tpd-lms-media">' +
				(c.thumbnail_url ? '<img src="' + esc(c.thumbnail_url) + '" alt="" class="tpd-lms-thumb">' : '') +
				'<input type="hidden" name="thumbnail_url" value="' + esc(c.thumbnail_url) + '">' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-pick-thumb><i class="fa-solid fa-image"></i> ' + (c.thumbnail_url ? 'Change image' : 'Choose image') + '</button>' +
				(c.thumbnail_url ? ' <button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-clear-thumb>Remove</button>' : '') +
				'</div>') +
			'<div class="tpd-form-grid-2">' +
			field('Price (USD)', '<input type="number" name="price" class="tpd-input" min="0" step="0.01" value="' + esc(c.price) + '">', '0 makes the course free.') +
			field('Level', '<select name="level" class="tpd-input">' + levels + '</select>') +
			'</div>' +
			field('Membership access', '<select name="tier_included" class="tpd-input">' + tiers + '</select>', 'Members on an included plan get the course without paying.') +
			field('Access length (days)', '<input type="number" name="access_days" class="tpd-input" min="1" step="1" value="' + esc(numOrBlank(c.access_days)) + '" placeholder="Lifetime">', 'Leave blank for lifetime access.') +
			'<fieldset class="tpd-lms-fieldset"><legend>Learning rules</legend>' +
			checkbox('sequential', 'Lessons open in order', c.sequential, 'Advisors finish each lesson before the next one opens.') +
			checkbox('drip_enabled', 'Release lessons over time', c.drip_enabled, 'Set “opens on day” on each lesson.') +
			checkbox('certificate_enabled', 'Issue a certificate', c.certificate_enabled, 'Given at 100% progress, after the final exam is passed if there is one.') +
			'</fieldset>' +
			field('Status', '<select name="status" class="tpd-input"><option value="draft"' + (c.status === 'draft' ? ' selected' : '') + '>Draft (hidden from advisors)</option><option value="published"' + (c.status === 'published' ? ' selected' : '') + '>Published</option></select>') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-floppy-disk"></i> Save course</button></div>' +
			'</form>';
	}

	/** Flatten the curriculum, move a lesson one step, and rebuild the module structure. */
	function moveLesson(lessonId, dir) {
		var slots = [];
		state.tree.chapters.forEach(function (ch) {
			slots.push({ chapter: ch.id });
			ch.lessons.forEach(function (l) { slots.push({ lesson: l.id }); });
		});
		var i = slots.findIndex(function (s) { return s.lesson === lessonId; });
		var j = i + dir;
		if (i < 0 || j < 1 || j >= slots.length) return;
		var tmp = slots[i]; slots[i] = slots[j]; slots[j] = tmp;

		var structure = [];
		slots.forEach(function (s) {
			if (s.chapter) structure.push({ chapter_id: s.chapter, lesson_ids: [] });
			else structure[structure.length - 1].lesson_ids.push(s.lesson);
		});
		saveOrder(structure);
	}

	function moveChapter(chapterId, dir) {
		var structure = state.tree.chapters.map(function (ch) {
			return { chapter_id: ch.id, lesson_ids: ch.lessons.map(function (l) { return l.id; }) };
		});
		var i = structure.findIndex(function (s) { return s.chapter_id === chapterId; });
		var j = i + dir;
		if (i < 0 || j < 0 || j >= structure.length) return;
		var tmp = structure[i]; structure[i] = structure[j]; structure[j] = tmp;
		saveOrder(structure);
	}

	function saveOrder(structure) {
		api('reorder', { course_id: state.tree.course.id, structure: JSON.stringify(structure) }).then(function (tree) {
			state.tree = tree;
			$root.find('.tpd-lms-curriculum').replaceWith(
				$('<div>').html(renderCourseShellCurriculum()).children()
			);
		}, function (msg) { notify(msg, 'error'); });
	}

	function renderCourseShellCurriculum() {
		var c = state.tree.course;
		return '<div class="tpd-sa-card tpd-lms-curriculum">' +
			'<div class="tpd-lms-bar"><h3 class="tpd-sa-card-title">Curriculum</h3>' +
			'<span class="tpd-lms-bar-actions"><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-go="students"><i class="fa-solid fa-users"></i> Students (' + c.student_count + ')</button>' +
			'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-go="quiz_report"><i class="fa-solid fa-chart-column"></i> Quiz results</button>' +
			'<span class="tpd-lms-badge is-' + esc(c.status) + '">' + (c.status === 'published' ? 'Published' : 'Draft') + '</span></span></div>' +
			renderCurriculum() +
			'<form class="tpd-lms-inline-form" data-form="new-chapter">' +
			'<input type="text" name="title" class="tpd-input" required maxlength="255" placeholder="New module title, e.g. Module 1: Welcome to the travel industry" aria-label="New module title">' +
			'<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-outline"><i class="fa-solid fa-plus"></i> Add module</button>' +
			'</form></div>';
	}

	/* ------------------------------------------------------------------ */
	/* View: lesson                                                        */
	/* ------------------------------------------------------------------ */

	function openLesson(id) {
		removeEditors();
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading lesson…</div>');
		api('lesson_get', { lesson_id: id }).then(function (detail) {
			state.lesson = detail;
			renderLesson();
		}, function (msg) {
			notify(msg, 'error');
			renderCourse();
		});
	}

	function renderLesson() {
		removeEditors();
		state.view = 'lesson';
		var l = state.lesson.lesson;
		var t = LESSON_TYPES[l.type] || LESSON_TYPES.text;
		crumbs([{ label: 'Courses', go: 'list' }, { label: state.tree.course.title, go: 'course' }, { label: l.title }]);

		var body = '';
		if (l.type === 'text' || l.type === 'video' || l.type === 'download') {
			if (l.type === 'video') {
				body += field('Video link', '<input type="url" name="video_url" class="tpd-input" value="' + esc(l.video_url) + '" placeholder="https://www.youtube.com/watch?v=… or https://vimeo.com/…">',
					'Paste a YouTube or Vimeo link, or upload a video file below.');
			}
			if (l.type === 'video' || l.type === 'download') {
				body += field(l.type === 'video' ? 'Video file' : 'File to download',
					'<div class="tpd-lms-media"><input type="hidden" name="attachment_id" value="' + esc(numOrBlank(l.attachment_id)) + '">' +
					'<span class="tpd-lms-muted" data-attachment-name>' + esc(l.attachment_name || 'No file chosen') + '</span> ' +
					'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-pick-file="' + (l.type === 'video' ? 'video' : '') + '"><i class="fa-solid fa-upload"></i> Choose file</button>' +
					(l.attachment_id ? ' <button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-clear-file>Remove</button>' : '') +
					'</div>',
					l.type === 'download' ? 'Only enrolled advisors who can open this lesson can download it.' : '');
			}
			body += field(l.type === 'text' ? 'Lesson content' : 'Notes for advisors',
				'<textarea id="tpd-lms-lesson-content" name="content" rows="14" class="tpd-input">' + esc(l.content) + '</textarea>');
		} else {
			body += field('Instructions', '<textarea id="tpd-lms-lesson-content" name="content" rows="6" class="tpd-input">' + esc(l.content) + '</textarea>',
				'Shown to advisors before they start.');
		}

		$root.html(
			'<div class="tpd-lms-grid tpd-lms-grid-lesson">' +
			'<div class="tpd-sa-card">' +
			'<div class="tpd-lms-bar"><h3 class="tpd-sa-card-title"><i class="fa-solid ' + t.icon + '"></i> ' + esc(t.label) + ' lesson</h3>' +
			'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-lms-danger" data-delete-lesson="' + l.id + '" data-title="' + esc(l.title) + '"><i class="fa-solid fa-trash"></i> Delete lesson</button></div>' +
			'<form class="tpd-lms-form" data-form="lesson">' +
			field('Title', '<input type="text" name="title" class="tpd-input" required maxlength="255" value="' + esc(l.title) + '">') +
			body +
			'<div class="tpd-form-grid-2">' +
			field('Length (minutes)', '<input type="number" name="duration_minutes" class="tpd-input" min="1" value="' + esc(numOrBlank(l.duration_minutes)) + '" placeholder="Optional">') +
			field('Opens on day', '<input type="number" name="drip_days" class="tpd-input" min="1" value="' + esc(numOrBlank(l.drip_days)) + '" placeholder="Right away">', state.tree.course.drip_enabled ? 'Days after the advisor enrolls.' : 'Turn on “Release lessons over time” in course settings to use this.') +
			'</div>' +
			checkbox('is_preview', 'Free preview', l.is_preview, 'Anyone can open this lesson before enrolling.') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-floppy-disk"></i> Save lesson</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-go="course">Back to curriculum</button></div>' +
			'</form></div>' +
			(state.lesson.quiz ? '<div>' + renderQuizSettings(state.lesson.quiz) + renderQuestions() + '</div>' : '') +
			'</div>'
		);
		addEditor('tpd-lms-lesson-content');
	}

	function renderQuizSettings(q) {
		return '<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">' + (q.is_final ? 'Exam settings' : 'Quiz settings') + '</h3>' +
			'<form class="tpd-lms-form" data-form="quiz">' +
			'<div class="tpd-form-grid-2">' +
			field('Pass mark (%)', '<input type="number" name="passing_score" class="tpd-input" min="1" max="100" required value="' + esc(q.passing_score) + '">') +
			field('Attempts allowed', '<input type="number" name="max_attempts" class="tpd-input" min="1" value="' + esc(numOrBlank(q.max_attempts)) + '" placeholder="Unlimited">') +
			field('Time limit (minutes)', '<input type="number" name="time_limit_minutes" class="tpd-input" min="1" value="' + esc(numOrBlank(q.time_limit_minutes)) + '" placeholder="No limit">', 'Enforced on the server.') +
			field('Questions per attempt', '<input type="number" name="questions_per_attempt" class="tpd-input" min="1" value="' + esc(numOrBlank(q.questions_per_attempt)) + '" placeholder="All questions">', 'Draws a balanced random set from the bank, by topic. Retakes get unseen questions first.') +
			'</div>' +
			checkbox('shuffle_questions', 'Shuffle question order', q.shuffle_questions) +
			checkbox('shuffle_options', 'Shuffle answer order', q.shuffle_options) +
			checkbox('show_answers', 'Show correct answers after submitting', q.show_answers) +
			(q.is_final ? checkbox('require_all_lessons', 'Open only after every other lesson is done', q.require_all_lessons) : '') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-floppy-disk"></i> Save settings</button></div>' +
			'</form></div>' +
			'<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">Who can start it</h3>' +
			'<form class="tpd-lms-form" data-form="access">' +
			'<label class="tpd-lms-check"><input type="radio" name="access_mode" value="open"' + (q.access_mode === 'open' ? ' checked' : '') + '> <span>Anyone enrolled<small>No password or code.</small></span></label>' +
			'<label class="tpd-lms-check"><input type="radio" name="access_mode" value="password"' + (q.access_mode === 'password' ? ' checked' : '') + '> <span>Shared password<small>One password for everyone, given out by proctors. Case-sensitive.</small></span></label>' +
			'<div data-password-wrap' + (q.access_mode === 'password' ? '' : ' hidden') + '>' + field('Password', '<input type="text" name="access_password" class="tpd-input" maxlength="120" autocomplete="off" value="' + esc(q.access_password) + '">') + '</div>' +
			'<label class="tpd-lms-check"><input type="radio" name="access_mode" value="code"' + (q.access_mode === 'code' ? ' checked' : '') + '> <span>One-time codes with a proctor<small>Students request a proctor; you approve it in Proctoring and the proctor gets a code that works once.</small></span></label>' +
			'<p class="tpd-lms-help">The check applies when starting a new attempt, not when resuming one. Five wrong tries lock a student out for 15 minutes. You skip it when previewing.</p>' +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-floppy-disk"></i> Save access</button></div>' +
			'</form></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Views: students, one student, quiz results                          */
	/* ------------------------------------------------------------------ */

	function fmtDate(utc) {
		if (!utc) return '—';
		var d = new Date(utc.replace(' ', 'T') + 'Z');
		return isNaN(d) ? utc : d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
	}

	function showStudents() {
		removeEditors();
		var c = state.tree.course;
		crumbs([{ label: 'Courses', go: 'list' }, { label: c.title, go: 'course' }, { label: 'Students' }]);
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading students…</div>');
		api('students', { course_id: c.id }).then(renderStudents, function (msg) { notify(msg, 'error'); renderCourse(); });
	}

	function renderStudents(data) {
		state.view = 'students';
		var rows = data.students.map(function (s) {
			return '<tr><td><button type="button" class="tpd-lms-link" data-open-student="' + s.enrollment_id + '">' + esc(s.name) + '</button><div class="tpd-lms-muted">' + esc(s.email) + '</div></td>' +
				'<td><div class="tpd-lms-mini-bar"><span style="width:' + s.progress + '%"></span></div> ' + s.progress + '%</td>' +
				'<td>' + fmtDate(s.enrolled_at) + (s.paid ? '' : ' <span class="tpd-lms-muted">(free)</span>') + '</td>' +
				'<td>' + fmtDate(s.last_activity) + '</td>' +
				'<td>' + (s.certificate ? '<span class="tpd-lms-badge is-published">' + esc(s.certificate) + '</span>' : (s.expired ? '<span class="tpd-lms-badge is-error">Access ended</span>' : '—')) + '</td></tr>';
		}).join('');
		$root.html(
			'<div class="tpd-sa-card"><div class="tpd-lms-bar"><h3 class="tpd-sa-card-title">Students (' + data.students.length + ')</h3></div>' +
			(rows ? '<div class="tpd-lms-table-wrap"><table class="tpd-lms-table"><thead><tr><th>Student</th><th>Progress</th><th>Enrolled</th><th>Last active</th><th>Certificate</th></tr></thead><tbody>' + rows + '</tbody></table></div>' :
				'<div class="tpd-lms-empty"><i class="fa-solid fa-users"></i><p>No one is enrolled yet.</p></div>') +
			'<form class="tpd-lms-inline-form tpd-lms-pad0" data-form="enroll"><input type="email" name="email" class="tpd-input" required placeholder="Member’s email address" aria-label="Member’s email address">' +
			'<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-outline"><i class="fa-solid fa-user-plus"></i> Enroll a member</button></form>' +
			'<p class="tpd-lms-help">Enrolls an existing TPD member for free and emails them the course link.</p></div>'
		);
	}

	function openStudent(id) {
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading…</div>');
		api('student_detail', { enrollment_id: id }).then(renderStudent, function (msg) { notify(msg, 'error'); showStudents(); });
	}

	function renderStudent(d, newCode) {
		state.view = 'student';
		state.student = d;
		var c = state.tree.course;
		crumbs([{ label: 'Courses', go: 'list' }, { label: c.title, go: 'course' }, { label: 'Students', go: 'students' }, { label: d.student.name }]);
		var lessons = d.lessons.map(function (l) {
			var attempts = l.attempts.length ? '<div class="tpd-lms-muted">' + l.attempts.map(function (a, i) {
				return 'Attempt ' + (i + 1) + ': ' + (a.submitted_at ? a.score + '%' + (a.passed ? ' (passed)' : '') : 'in progress');
			}).join(' · ') + '</div>' : '';
			return '<li class="' + (l.done ? 'is-done' : '') + '"><i class="fa-solid ' + (l.done ? 'fa-circle-check' : 'fa-circle') + '" aria-hidden="true"></i> <span>' + esc(l.title) +
				(l.done ? ' <span class="tpd-lms-muted">· ' + fmtDate(l.completed_at) + '</span>' : '') + attempts + '</span></li>';
		}).join('');
		var hours = Object.keys(d.valid_for).map(function (h) { return '<option value="' + h + '"' + (h === '72' ? ' selected' : '') + '>' + esc(d.valid_for[h]) + '</option>'; }).join('');
		var exams = d.exams.map(function (x) {
			var codes = x.codes.length ? '<table class="tpd-lms-table"><thead><tr><th>Status</th><th>Proctor</th><th>Expires</th><th aria-label="Actions"></th></tr></thead><tbody>' + x.codes.map(function (k) {
				return '<tr><td><span class="tpd-lms-badge ' + (k.status === 'active' ? 'is-published' : 'is-draft') + '">' + esc(k.status) + '</span></td><td>' + esc(k.proctor || '—') + '</td><td>' + fmtDate(k.expires_at) + '</td>' +
					'<td>' + (k.status === 'active' ? '<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-lms-danger" data-revoke-code="' + k.id + '">Cancel code</button>' : '') + '</td></tr>';
			}).join('') + '</tbody></table>' : '<p class="tpd-lms-muted">No codes yet.</p>';
			return '<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">' + esc(x.title) + ' · ' + (x.access_mode === 'code' ? 'one-time codes' : 'shared password') + '</h3>' +
				(x.access_mode === 'code' ? codes +
					'<form class="tpd-lms-form" data-form="code" data-quiz="' + x.quiz_id + '"><h4>Make a code</h4><div class="tpd-form-grid-2">' +
					field('Valid for', '<select name="valid_hours" class="tpd-input">' + hours + '</select>') +
					field('Proctor’s email (optional)', '<input type="email" name="proctor_email" class="tpd-input" placeholder="We’ll email them the code and a private link">') +
					'</div>' + field('Proctor’s name (optional)', '<input type="text" name="proctor_name" class="tpd-input" maxlength="120">') +
					field('Note for the proctor (optional)', '<textarea name="notes" rows="2" class="tpd-input"></textarea>') +
					'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-key"></i> Make code</button></div></form>'
					: '<p class="tpd-lms-muted">Everyone uses the shared password set on the exam.</p>') + '</div>';
		}).join('');
		$root.html(
			(newCode ? '<div class="tpd-sa-card tpd-lms-newcode" role="status"><h3 class="tpd-sa-card-title">New code</h3><div class="tpd-lms-code">' + esc(newCode.code) + '</div>' +
				'<p class="tpd-lms-help">' + (newCode.emailed === true ? 'Emailed to the proctor with their private link. ' : newCode.emailed === false ? 'The email to the proctor could not be sent. Give them the code yourself. ' : '') +
				'This is the only time the code is shown here. It works once, for this student only. Don’t give it to the student.</p></div>' : '') +
			'<div class="tpd-lms-grid"><div class="tpd-sa-card"><h3 class="tpd-sa-card-title">' + esc(d.student.name) + '</h3>' +
			'<p class="tpd-lms-muted">' + esc(d.student.email) + ' · enrolled ' + fmtDate(d.student.enrolled_at) + (d.student.expires_at ? ' · access until ' + fmtDate(d.student.expires_at) : '') + '</p>' +
			'<p><strong>' + d.student.progress + '% complete</strong></p><ol class="tpd-lms-checklist">' + lessons + '</ol></div>' +
			'<div>' + (exams || '<div class="tpd-sa-card"><p class="tpd-lms-muted">This course has no exams that need a password or code.</p></div>') + '</div></div>'
		);
	}

	function showQuizReport() {
		removeEditors();
		var c = state.tree.course;
		crumbs([{ label: 'Courses', go: 'list' }, { label: c.title, go: 'course' }, { label: 'Quiz results' }]);
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading results…</div>');
		api('quiz_report', { course_id: c.id }).then(function (data) {
			state.view = 'quiz_report';
			$root.html(data.quizzes.length ? data.quizzes.map(function (q) {
				var rows = q.questions.map(function (x) {
					return '<tr><td>' + esc(x.prompt) + (x.topic ? '<div class="tpd-lms-muted">' + esc(x.topic) + '</div>' : '') + '</td><td>' + x.answers + '</td>' +
						'<td>' + (x.correct === null ? '—' : '<div class="tpd-lms-mini-bar ' + (x.correct < 50 ? 'is-low' : '') + '"><span style="width:' + x.correct + '%"></span></div> ' + x.correct + '%') + '</td></tr>';
				}).join('');
				return '<div class="tpd-sa-card"><h3 class="tpd-sa-card-title">' + esc(q.title) + (q.is_final ? ' <span class="tpd-lms-badge is-draft">Final exam</span>' : '') + '</h3>' +
					'<p class="tpd-lms-muted">' + q.attempts + ' submitted attempts' + (q.attempts ? ' · ' + q.pass_rate + '% passed · average ' + q.average + '%' : '') + '</p>' +
					(rows ? '<div class="tpd-lms-table-wrap"><table class="tpd-lms-table"><thead><tr><th>Question (hardest first)</th><th>Answered</th><th>Got it right</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '') + '</div>';
			}).join('') : '<div class="tpd-sa-card"><p class="tpd-lms-muted">This course has no quizzes yet.</p></div>');
		}, function (msg) { notify(msg, 'error'); renderCourse(); });
	}

	/* ------------------------------------------------------------------ */
	/* View: proctoring queue                                              */
	/* ------------------------------------------------------------------ */

	function showProctoring(status) {
		removeEditors();
		crumbs([{ label: 'Courses', go: 'list' }, { label: 'Proctoring' }]);
		$root.html('<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading requests…</div>');
		api('proctor_list', { status: status || 'pending' }).then(renderProctoring, function (msg) { notify(msg, 'error'); showList(); });
	}

	function renderProctoring(data) {
		state.view = 'proctoring';
		var tabs = ['pending', 'approved', 'declined', 'cancelled'].map(function (s) {
			var n = data.counts[s] || 0;
			return '<button type="button" class="tpd-btn tpd-btn-xs ' + (s === data.status ? 'tpd-btn-darkblue' : 'tpd-btn-outline') + '" data-proctor-tab="' + s + '">' + s.charAt(0).toUpperCase() + s.slice(1) + ' (' + n + ')</button>';
		}).join(' ');
		var hours = Object.keys(data.valid_for).map(function (h) { return '<option value="' + h + '"' + (h === '72' ? ' selected' : '') + '>' + esc(data.valid_for[h]) + '</option>'; }).join('');
		var cards = data.requests.map(function (r) {
			var info = '<dl class="tpd-lms-dl"><dt>Student</dt><dd>' + esc(r.student) + '</dd><dt>Exam</dt><dd>' + esc(r.exam) + '</dd><dt>Proctor</dt><dd>' + esc(r.proctor) + (r.phone ? ' · ' + esc(r.phone) : '') + '</dd>' +
				(r.relationship ? '<dt>Relationship</dt><dd>' + esc(r.relationship) + '</dd>' : '') + (r.preferred ? '<dt>Preferred</dt><dd>' + esc(r.preferred) + '</dd>' : '') +
				(r.notes ? '<dt>Student notes</dt><dd>' + esc(r.notes) + '</dd>' : '') + (r.decision ? '<dt>Decision note</dt><dd>' + esc(r.decision) + '</dd>' : '') +
				(r.code_status ? '<dt>Code</dt><dd>' + esc(r.code_status) + '</dd>' : '') +
				(r.confirmed ? '<dt>Proctor</dt><dd>' + (r.flagged ? '<span class="tpd-lms-badge is-error">Reported a problem</span> ' + esc(r.report) : '<span class="tpd-lms-badge is-published">Confirmed the session</span>') + '</dd>' : '') + '</dl>';
			var actions = '';
			if (data.status === 'pending') {
				actions = '<form class="tpd-lms-inline-form tpd-lms-pad0" data-form="approve" data-id="' + r.id + '"><select name="valid_hours" class="tpd-input" aria-label="Code valid for">' + hours + '</select>' +
					'<input type="text" name="note" class="tpd-input" placeholder="Note for proctor and student (optional)" aria-label="Note"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-check"></i> Approve and email the code</button></form>' +
					'<form class="tpd-lms-inline-form tpd-lms-pad0" data-form="decline" data-id="' + r.id + '"><input type="text" name="reason" class="tpd-input" required placeholder="Reason, shown to the student" aria-label="Reason for declining">' +
					'<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-outline tpd-lms-danger">Decline</button></form>';
			} else if (data.status === 'approved' && r.code_status === 'active') {
				actions = '<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-proctor-resend="' + r.id + '"><i class="fa-solid fa-paper-plane"></i> Send the proctor a new link</button>';
			}
			return '<div class="tpd-sa-card">' + info + actions + '</div>';
		}).join('');
		$root.html('<div class="tpd-sa-card"><div class="tpd-lms-bar"><h3 class="tpd-sa-card-title">Proctoring</h3><span class="tpd-lms-bar-actions">' + tabs + '</span></div>' +
			'<p class="tpd-lms-help">Approving creates a one-time code and emails it to the proctor with a private link. The student is told, but never sees the code.</p></div>' +
			(cards || '<div class="tpd-sa-card"><div class="tpd-lms-empty"><i class="fa-solid fa-user-shield"></i><p>No ' + esc(data.status) + ' requests.</p></div></div>'));
	}

	function renderQuestions() {
		var qs = state.lesson.questions;
		var list = qs.map(function (q, i) {
			var answers = q.type === 'short' ?
				'<div class="tpd-lms-muted">Accepted: ' + esc(q.accepted_answers.split(/\r?\n/).filter(Boolean).join(' · ')) + '</div>' :
				'<ul class="tpd-lms-options">' + q.options.map(function (o) {
					return '<li class="' + (o.is_correct ? 'is-correct' : '') + '">' + (o.is_correct ? '<i class="fa-solid fa-check" aria-label="Correct"></i> ' : '') + esc(o.text) + '</li>';
				}).join('') + '</ul>';
			return '<li class="tpd-lms-question">' +
				'<div class="tpd-lms-bar"><span class="tpd-lms-muted">' + (i + 1) + '. ' + esc(QUESTION_TYPES[q.type] || q.type) +
				(q.topic ? ' · ' + esc(q.topic) : '') + ' · ' + q.points + (q.points === 1 ? ' point' : ' points') + '</span>' +
				'<span><button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-edit-question="' + q.id + '"><i class="fa-solid fa-pen"></i> Edit</button> ' +
				'<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-lms-danger" data-delete-question="' + q.id + '"><i class="fa-solid fa-trash"></i></button></span></div>' +
				'<div class="tpd-lms-prompt">' + esc($('<div>').html(q.prompt).text()) + '</div>' + answers + '</li>';
		}).join('');

		return '<div class="tpd-sa-card"><div class="tpd-lms-bar"><h3 class="tpd-sa-card-title">Questions (' + qs.length + ')</h3>' +
			'<span class="tpd-lms-bar-actions"><button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-open-import><i class="fa-solid fa-file-import"></i> Import questions</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-new-question><i class="fa-solid fa-plus"></i> Add question</button></span></div>' +
			'<div id="tpd-lms-import"></div>' +
			(qs.length ? '<ol class="tpd-lms-questions">' + list + '</ol>' : '<div class="tpd-lms-empty"><i class="fa-solid fa-circle-question"></i><p>No questions yet. Advisors can’t start this until it has at least one.</p></div>') +
			'<div id="tpd-lms-question-editor"></div></div>';
	}

	/* Question import ---------------------------------------------------- */

	var importInput = null; // The file or text last previewed, sent again on import.

	function renderImportPanel() {
		importInput = null;
		$('#tpd-lms-import').html(
			'<form class="tpd-lms-form tpd-lms-import" data-form="import">' +
			'<h4>Import questions</h4>' +
			'<p class="tpd-lms-help">Upload an Excel (.xlsx), CSV, Word (.docx) or text file, or paste questions below. You’ll see a preview before anything is added. Questions already in this quiz are skipped.</p>' +
			field('File', '<input type="file" name="file" class="tpd-input" accept=".xlsx,.csv,.docx,.txt">') +
			field('Or paste questions', '<textarea name="text" rows="8" class="tpd-input" placeholder="Topic: Module 1&#10;1. Which body accredits travel agencies to sell airline tickets?&#10;A. IATA&#10;B. CLIA&#10;Answer: A&#10;&#10;2. A host agency supports independent advisors.&#10;Answer: True"></textarea>',
				'Number each question. Letter the choices A, B, C. Put "Answer:" after the choices: a letter, several letters ("A, C"), True or False, or the accepted text for short answers (alternatives separated by "|"). "Topic:" applies to the questions below it.') +
			field('Topic for questions without one', '<input type="text" name="default_topic" class="tpd-input" maxlength="120" placeholder="Optional, e.g. Module 3">') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-eye"></i> Preview</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-import-template><i class="fa-solid fa-download"></i> Download the spreadsheet template</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-close-import>Cancel</button></div>' +
			'</form><div id="tpd-lms-import-preview"></div>'
		);
	}

	function importFormData(commit) {
		var fd = new FormData();
		fd.append('quiz_id', state.lesson.quiz.id);
		if (importInput.file) fd.append('file', importInput.file);
		else fd.append('text', importInput.text);
		fd.append('default_topic', importInput.defaultTopic || '');
		if (commit) fd.append('commit', '1');
		return fd;
	}

	function renderImportPreview(items) {
		var ready = items.filter(function (it) { return !it.errors.length && !it.duplicate; }).length;
		var rows = items.map(function (it) {
			var status = it.errors.length ? '<span class="tpd-lms-badge is-error">Won’t import</span><ul class="tpd-lms-errors">' + it.errors.map(function (e) { return '<li>' + esc(e) + '</li>'; }).join('') + '</ul>' :
				it.duplicate ? '<span class="tpd-lms-badge is-draft">Already in quiz</span>' : '<span class="tpd-lms-badge is-published">Ready</span>';
			var answers = it.type === 'short' ? esc((it.accepted_answers || '').split('\n').join(' · ')) :
				it.options.map(function (o) { return (o.correct ? '<strong>✓ ' : '') + esc(o.text) + (o.correct ? '</strong>' : ''); }).join('<br>');
			return '<tr><td class="tpd-lms-muted">' + esc(it.source) + '</td><td>' + esc(it.prompt) + '<div class="tpd-lms-muted">' + esc(QUESTION_TYPES[it.type] || it.type) + (it.topic ? ' · ' + esc(it.topic) : '') + ' · ' + it.points + (it.points === 1 ? ' point' : ' points') + '</div></td><td>' + answers + '</td><td>' + status + '</td></tr>';
		}).join('');
		$('#tpd-lms-import-preview').html(
			'<div class="tpd-lms-bar"><strong>' + items.length + (items.length === 1 ? ' question found' : ' questions found') + ', ' + ready + ' ready to import</strong>' +
			(ready ? '<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue" data-commit-import><i class="fa-solid fa-check"></i> Import ' + ready + (ready === 1 ? ' question' : ' questions') + '</button>' : '') + '</div>' +
			'<div class="tpd-lms-table-wrap"><table class="tpd-lms-table"><thead><tr><th>Where</th><th>Question</th><th>Answers</th><th>Status</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
		);
	}

	function downloadTemplate() {
		var rows = [
			['Topic', 'Type', 'Question', 'Option A', 'Option B', 'Option C', 'Option D', 'Option E', 'Correct', 'Points', 'Explanation'],
			['Module 1', 'single', 'Which body accredits travel agencies to sell airline tickets worldwide?', 'IATA', 'CLIA', 'ASTA', 'UNWTO', '', 'A', '1', 'IATA accreditation lets agencies issue airline tickets.'],
			['Module 4', 'multiple', 'Which of these belong in a client discovery call? Select all that apply.', 'Budget', 'Travel dates', "The advisor's commission rate", 'Who is travelling', '', 'A, B, D', '1', ''],
			['Module 8', 'true_false', 'A host agency can provide booking tools and supplier relationships to independent advisors.', '', '', '', '', '', 'True', '1', ''],
			['Module 12', 'short', 'What does the abbreviation FIT stand for in travel?', '', '', '', '', '', 'Flexible independent travel | Free independent traveller', '1', 'Both forms are in common use.']
		];
		var csv = rows.map(function (r) {
			return r.map(function (c) { return '"' + String(c).replace(/"/g, '""') + '"'; }).join(',');
		}).join('\r\n');
		var blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = 'question-import-template.csv';
		document.body.appendChild(a);
		a.click();
		setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
	}

	function renderQuestionForm(q) {
		q = q || { id: 0, type: 'single', topic: '', prompt: '', explanation: '', accepted_answers: '', points: 1, options: [{ text: '', is_correct: true }, { text: '', is_correct: false }, { text: '', is_correct: false }, { text: '', is_correct: false }] };
		if (q.type === 'true_false' && !q.options.length) q.options = [{ text: 'True', is_correct: true }, { text: 'False', is_correct: false }];
		var types = Object.keys(QUESTION_TYPES).map(function (k) {
			return '<option value="' + k + '"' + (q.type === k ? ' selected' : '') + '>' + esc(QUESTION_TYPES[k]) + '</option>';
		}).join('');

		$('#tpd-lms-question-editor').html(
			'<form class="tpd-lms-form tpd-lms-question-form" data-form="question" data-id="' + q.id + '">' +
			'<h4>' + (q.id ? 'Edit question' : 'New question') + '</h4>' +
			'<div class="tpd-form-grid-2">' +
			field('Type', '<select name="type" class="tpd-input">' + types + '</select>') +
			field('Topic', '<input type="text" name="topic" class="tpd-input" maxlength="120" value="' + esc(q.topic) + '" placeholder="e.g. Module 4">', 'Used to balance random draws. “4” becomes “Module 4”.') +
			'</div>' +
			field('Question', '<textarea name="prompt" rows="3" class="tpd-input" required>' + esc($('<div>').html(q.prompt).text()) + '</textarea>') +
			'<div data-options-wrap>' + renderOptionRows(q) + '</div>' +
			'<div data-short-wrap' + (q.type === 'short' ? '' : ' hidden') + '>' +
			field('Accepted answers', '<textarea name="accepted_answers" rows="3" class="tpd-input" placeholder="One per line">' + esc(q.accepted_answers) + '</textarea>', 'Capital letters and extra spaces are ignored.') +
			'</div>' +
			'<div class="tpd-form-grid-2">' +
			field('Points', '<input type="number" name="points" class="tpd-input" min="1" max="100" value="' + esc(q.points) + '">') +
			'</div>' +
			field('Explanation', '<textarea name="explanation" rows="2" class="tpd-input" placeholder="Optional. Shown after submitting if answers are shown.">' + esc($('<div>').html(q.explanation).text()) + '</textarea>') +
			'<div class="tpd-lms-form-actions"><button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-darkblue"><i class="fa-solid fa-floppy-disk"></i> Save question</button> ' +
			'<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline" data-cancel-question>Cancel</button></div>' +
			'</form>'
		);
		var $form = $('#tpd-lms-question-editor form');
		$form[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		$form.find('[name=prompt]').trigger('focus');
	}

	function renderOptionRows(q) {
		if (q.type === 'short') return '';
		var inputType = q.type === 'multiple' ? 'checkbox' : 'radio';
		var rows = q.options.map(function (o) {
			return '<div class="tpd-lms-option-row">' +
				'<label class="tpd-lms-correct"><input type="' + inputType + '" name="correct" value="1"' + (o.is_correct ? ' checked' : '') + '> <span class="screen-reader-text">Correct answer</span></label>' +
				'<input type="text" class="tpd-input" data-option-text maxlength="500" value="' + esc(o.text) + '" placeholder="Answer"' + (q.type === 'true_false' ? ' readonly' : '') + '>' +
				(q.type === 'true_false' ? '' : '<button type="button" class="tpd-lms-icon-btn" data-remove-option title="Remove answer" aria-label="Remove answer"><i class="fa-solid fa-xmark"></i></button>') +
				'</div>';
		}).join('');
		return '<div class="tpd-form-group tpd-lms-field"><label>Answers <small>(' + (q.type === 'multiple' ? 'tick every correct answer' : 'select the correct answer') + ')</small></label>' + rows +
			(q.type === 'true_false' ? '' : '<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline" data-add-option><i class="fa-solid fa-plus"></i> Add answer</button>') + '</div>';
	}

	function readOptions($form) {
		var out = [];
		$form.find('.tpd-lms-option-row').each(function () {
			out.push({ text: $(this).find('[data-option-text]').val(), is_correct: $(this).find('[name=correct]').is(':checked') });
		});
		return out;
	}

	function formData($form) {
		var data = {};
		$form.find('input[name], select[name], textarea[name]').each(function () {
			var $f = $(this);
			if ($f.attr('type') === 'checkbox' && $f.attr('name') !== 'correct') data[$f.attr('name')] = $f.is(':checked') ? '1' : '0';
			else if ($f.attr('name') !== 'correct') data[$f.attr('name')] = $f.val();
		});
		return data;
	}

	/* ------------------------------------------------------------------ */
	/* Events                                                              */
	/* ------------------------------------------------------------------ */

	function bind() {
		$(document)
			.on('click', '#tpd-lms-crumbs [data-go], #tpd-lms-builder [data-go]', function () {
				var go = $(this).data('go');
				if (go === 'list') showList();
				if (go === 'course') openCourse(state.tree.course.id);
				if (go === 'coupons') showCoupons();
				if (go === 'proctoring') showProctoring();
				if (go === 'students') showStudents();
				if (go === 'quiz_report') showQuizReport();
			})
			.on('change', '[data-form=access] [name=access_mode]', function () {
				$(this).closest('form').find('[data-password-wrap]').prop('hidden', $(this).val() !== 'password');
			})
			.on('submit', '[data-form=access]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('quiz_access_save', { quiz_id: state.lesson.quiz.id, access_mode: $f.find('[name=access_mode]:checked').val(), access_password: $f.find('[name=access_password]').val() }).then(function (detail) {
					state.lesson = detail;
					notify('Access saved.');
					renderLesson();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-open-student]', function () { openStudent($(this).data('open-student')); })
			.on('submit', '[data-form=enroll]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('student_enroll', { course_id: state.tree.course.id, email: $f.find('[name=email]').val() }).then(function (data) {
					notify('Enrolled. They’ve been emailed the course link.');
					renderStudents(data);
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('submit', '[data-form=code]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var data = formData($f);
				data.quiz_id = $f.data('quiz');
				data.enrollment_id = state.student.enrollment_id;
				busy($b, true);
				api('code_create', data).then(function (d) { renderStudent(d, { code: d.code, emailed: d.emailed }); window.scrollTo({ top: $root.offset().top - 100, behavior: 'smooth' }); },
					function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-revoke-code]', function () {
				if (!window.confirm('Cancel this code? It will stop working right away.')) return;
				api('code_revoke', { code_id: $(this).data('revoke-code') }).then(function (d) { notify('Code cancelled.'); renderStudent(d); }, function (msg) { notify(msg, 'error'); });
			})
			.on('click', '[data-proctor-tab]', function () { showProctoring($(this).data('proctor-tab')); })
			.on('submit', '[data-form=approve]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('proctor_approve', $.extend({ request_id: $f.data('id') }, formData($f))).then(function (d) {
					notify(d.emails_sent ? 'Approved. The proctor and the student have been emailed.' : 'Approved, but an email could not be sent. Use “Send the proctor a new link” under Approved.', d.emails_sent ? 'ok' : 'error');
					renderProctoring(d);
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('submit', '[data-form=decline]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('proctor_decline', { request_id: $f.data('id'), reason: $f.find('[name=reason]').val() }).then(function (d) {
					notify('Declined. The student has been told why.');
					renderProctoring(d);
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-proctor-resend]', function () {
				if (!window.confirm('Send the proctor a new link? The old link stops working.')) return;
				api('proctor_resend', { request_id: $(this).data('proctor-resend') }).then(function (d) {
					notify(d.emails_sent ? 'New link sent to the proctor.' : 'The email could not be sent.', d.emails_sent ? 'ok' : 'error');
					renderProctoring(d);
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('submit', '[data-form=coupon]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('coupon_save', formData($f)).then(function (data) { notify('Coupon created.'); renderCoupons(data); }, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-coupon-toggle]', function () {
				api('coupon_toggle', { coupon_id: $(this).data('coupon-toggle') }).then(renderCoupons, function (msg) { notify(msg, 'error'); });
			})
			.on('click', '[data-coupon-delete]', function () {
				if (!window.confirm('Delete the coupon ' + $(this).data('code') + '? Past orders keep their discount.')) return;
				api('coupon_delete', { coupon_id: $(this).data('coupon-delete') }).then(renderCoupons, function (msg) { notify(msg, 'error'); });
			})
			.on('click', '#tpd-lms-new-course', function () {
				$('#tpd-lms-new-course-form').prop('hidden', false).find('[name=title]').trigger('focus');
			})
			.on('click', '[data-cancel-new]', function () {
				$('#tpd-lms-new-course-form').prop('hidden', true);
			})
			.on('click', '[data-open-course]', function () { openCourse($(this).data('open-course')); })
			.on('click', '[data-open-lesson]', function () { openLesson($(this).data('open-lesson')); })
			.on('click', '[data-delete-course]', function () {
				var $b = $(this);
				if (!window.confirm('Delete “' + $b.data('title') + '” and all its modules, lessons and questions? This can’t be undone.')) return;
				api('course_delete', { course_id: $b.data('delete-course') }).then(function () {
					notify('Course deleted.');
					showList();
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('submit', '[data-form=new-course]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('course_save', formData($f)).then(function (tree) {
					state.tree = tree;
					notify('Course created as a draft. Add a module to start the curriculum.');
					renderCourse();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('submit', '[data-form=settings]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				busy($b, true);
				api('settings_save', formData($f)).then(function (data) {
					state.courses = data.courses;
					state.settings = data.settings;
					notify('Saved. New and existing certificates show this name.');
					renderList();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('submit', '[data-form=course]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var data = formData($f);
				data.description = editorValue('tpd-lms-course-description');
				data.course_id = state.tree.course.id;
				busy($b, true);
				api('course_save', data).then(function (tree) {
					state.tree = tree;
					notify('Course saved.');
					renderCourse();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-pick-thumb]', function () {
				var $form = $(this).closest('form');
				pickMedia('Choose a cover image', 'image', function (att) {
					$form.find('[name=thumbnail_url]').val(att.url);
					$form.find('.tpd-lms-thumb').remove();
					$form.find('.tpd-lms-media').first().prepend($('<img class="tpd-lms-thumb" alt="">').attr('src', att.url));
				});
			})
			.on('click', '[data-clear-thumb]', function () {
				var $form = $(this).closest('form');
				$form.find('[name=thumbnail_url]').val('');
				$form.find('.tpd-lms-thumb').remove();
				$(this).remove();
			})
			.on('submit', '[data-form=new-chapter]', function (e) {
				e.preventDefault();
				var $f = $(this);
				api('chapter_save', { course_id: state.tree.course.id, title: $f.find('[name=title]').val() }).then(function (tree) {
					state.tree = tree;
					renderCourse();
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('click', '[data-rename-chapter]', function () {
				var $b = $(this);
				var title = window.prompt('Module title', $b.data('title'));
				if (title === null || $.trim(title) === '') return;
				api('chapter_save', { chapter_id: $b.data('rename-chapter'), title: title }).then(function (tree) {
					state.tree = tree;
					renderCourse();
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('click', '[data-delete-chapter]', function () {
				var $b = $(this), count = Number($b.data('count'));
				var msg = count ? 'Delete the module “' + $b.data('title') + '” and its ' + count + (count === 1 ? ' lesson' : ' lessons') + '? Advisors lose their progress on those lessons.' : 'Delete the module “' + $b.data('title') + '”?';
				if (!window.confirm(msg)) return;
				api('chapter_delete', { chapter_id: $b.data('delete-chapter') }).then(function (tree) {
					state.tree = tree;
					renderCourse();
				}, function (m) { notify(m, 'error'); });
			})
			.on('click', '[data-move-lesson]', function () { moveLesson(Number($(this).data('move-lesson')), Number($(this).data('dir'))); })
			.on('click', '[data-move-chapter]', function () { moveChapter(Number($(this).data('move-chapter')), Number($(this).data('dir'))); })
			.on('submit', '[data-form=new-lesson]', function (e) {
				e.preventDefault();
				var $f = $(this);
				api('lesson_save', { chapter_id: $f.data('chapter'), type: $f.find('[name=type]').val(), title: $f.find('[name=title]').val() }).then(function (detail) {
					state.lesson = detail;
					return api('course_get', { course_id: state.tree.course.id });
				}).then(function (tree) {
					state.tree = tree;
					renderLesson();
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('submit', '[data-form=lesson]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var data = formData($f);
				data.content = editorValue('tpd-lms-lesson-content');
				data.lesson_id = state.lesson.lesson.id;
				busy($b, true);
				api('lesson_save', data).then(function (detail) {
					state.lesson = detail;
					return api('course_get', { course_id: state.tree.course.id });
				}).then(function (tree) {
					state.tree = tree;
					notify('Lesson saved.');
					renderLesson();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-pick-file]', function () {
				var $form = $(this).closest('form');
				pickMedia('Choose a file', $(this).data('pick-file') || null, function (att) {
					$form.find('[name=attachment_id]').val(att.id);
					$form.find('[data-attachment-name]').text(att.filename || att.title);
				});
			})
			.on('click', '[data-clear-file]', function () {
				var $form = $(this).closest('form');
				$form.find('[name=attachment_id]').val('');
				$form.find('[data-attachment-name]').text('No file chosen');
				$(this).remove();
			})
			.on('click', '[data-delete-lesson]', function () {
				var $b = $(this);
				if (!window.confirm('Delete the lesson “' + $b.data('title') + '”? Advisors lose their progress on it.')) return;
				api('lesson_delete', { lesson_id: $b.data('delete-lesson') }).then(function (tree) {
					state.tree = tree;
					notify('Lesson deleted.');
					renderCourse();
				}, function (msg) { notify(msg, 'error'); });
			})
			.on('submit', '[data-form=quiz]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var data = formData($f);
				data.quiz_id = state.lesson.quiz.id;
				busy($b, true);
				api('quiz_save', data).then(function (detail) {
					state.lesson = detail;
					notify('Settings saved.');
					renderLesson();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-open-import]', function () { renderImportPanel(); $('#tpd-lms-import [name=file]').trigger('focus'); })
			.on('click', '[data-close-import]', function () { importInput = null; $('#tpd-lms-import').empty(); })
			.on('click', '[data-import-template]', downloadTemplate)
			.on('submit', '[data-form=import]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var file = $f.find('[name=file]')[0].files[0];
				var text = $f.find('[name=text]').val();
				if (!file && !$.trim(text)) { notify('Choose a file or paste your questions first.', 'error'); return; }
				importInput = file ? { file: file } : { text: text };
				importInput.defaultTopic = $f.find('[name=default_topic]').val();
				busy($b, true);
				apiForm('question_import', importFormData(false)).then(function (data) {
					busy($b, false);
					renderImportPreview(data.preview);
				}, function (msg) { busy($b, false); $('#tpd-lms-import-preview').empty(); notify(msg, 'error'); });
			})
			.on('click', '[data-commit-import]', function () {
				var $b = $(this);
				if (!importInput) return;
				busy($b, true);
				apiForm('question_import', importFormData(true)).then(function (detail) {
					state.lesson = detail;
					var r = detail.import;
					notify(r.added + (r.added === 1 ? ' question imported.' : ' questions imported.') + (r.skipped ? ' ' + r.skipped + ' skipped.' : ''));
					renderLesson();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-new-question]', function () { renderQuestionForm(null); })
			.on('click', '[data-edit-question]', function () {
				var id = Number($(this).data('edit-question'));
				renderQuestionForm($.extend(true, {}, state.lesson.questions.find(function (q) { return q.id === id; })));
			})
			.on('click', '[data-cancel-question]', function () { $('#tpd-lms-question-editor').empty(); })
			.on('change', '[data-form=question] [name=type]', function () {
				var $form = $(this).closest('form');
				var type = $(this).val();
				var current = readOptions($form);
				var q = { type: type, options: current };
				if (type === 'true_false') q.options = [{ text: 'True', is_correct: true }, { text: 'False', is_correct: false }];
				else if (current.length < 2 || current[0].text === 'True') q.options = [{ text: '', is_correct: true }, { text: '', is_correct: false }, { text: '', is_correct: false }];
				if (type !== 'multiple') {
					var seen = false;
					q.options.forEach(function (o) { if (o.is_correct && !seen) seen = true; else o.is_correct = false; });
				}
				$form.find('[data-options-wrap]').html(renderOptionRows(q));
				$form.find('[data-short-wrap]').prop('hidden', type !== 'short');
			})
			.on('click', '[data-add-option]', function () {
				var $form = $(this).closest('form');
				var q = { type: $form.find('[name=type]').val(), options: readOptions($form).concat([{ text: '', is_correct: false }]) };
				$form.find('[data-options-wrap]').html(renderOptionRows(q));
				$form.find('[data-option-text]').last().trigger('focus');
			})
			.on('click', '[data-remove-option]', function () { $(this).closest('.tpd-lms-option-row').remove(); })
			.on('submit', '[data-form=question]', function (e) {
				e.preventDefault();
				var $f = $(this), $b = $f.find('[type=submit]');
				var data = formData($f);
				data.options = JSON.stringify(readOptions($f));
				if (Number($f.data('id'))) data.question_id = $f.data('id');
				else data.quiz_id = state.lesson.quiz.id;
				busy($b, true);
				api('question_save', data).then(function (detail) {
					state.lesson = detail;
					notify('Question saved.');
					renderLesson();
				}, function (msg) { busy($b, false); notify(msg, 'error'); });
			})
			.on('click', '[data-delete-question]', function () {
				if (!window.confirm('Delete this question? Past attempts keep their scores.')) return;
				api('question_delete', { question_id: $(this).data('delete-question') }).then(function (detail) {
					state.lesson = detail;
					notify('Question deleted.');
					renderLesson();
				}, function (msg) { notify(msg, 'error'); });
			});
	}

	$(function () {
		$root = $('#tpd-lms-builder');
		if (!$root.length) return;
		$crumbs = $('#tpd-lms-crumbs');
		$notice = $('#tpd-lms-notice');
		bind();
		showList();
	});
})(jQuery);
