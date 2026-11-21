# Native LMS module

The TARC learning platform (courses, lessons, quizzes, exams, certificates) built into TPD.

The module is **off by default** on a fresh install. While it's off, it creates no tables and adds no hooks, and no courses are shown. The external Laravel LMS bridge (`includes/class-tpd-lms.php`) was removed in 2.13.0; toolkits moved to `includes/class-tpd-resources.php`.

## Switching it on

```bash
wp tpd-lms status    # on/off and table check
wp tpd-lms enable    # create the tables and switch on
wp tpd-lms disable   # switch off; data is kept
```

## Where things are

| File | What it does |
|---|---|
| `class-tpd-lms-native.php` | Loader, on/off switch, WP-CLI commands |
| `class-tpd-lms-schema.php` | `{prefix}tpd_lms_*` tables (dbDelta, version in `tpd_lms_db_ver`) |
| `class-tpd-lms-store.php` | Courses, modules (chapters), lessons, quizzes, questions |
| `class-tpd-lms-progress.php` | Enrollment, lesson locks, completion, certificates |
| `class-tpd-lms-quiz.php` | Attempts, question-bank draws, grading, deadlines |
| `class-tpd-lms-admin.php` | Course builder AJAX actions (`tpd_lms_*`, super admins only) |
| `templates/lms/admin/studio.php` | Builder shell, shown in Super Admin → LMS Studio when the module is on |
| `assets/js/tpd-lms-admin.js`, `assets/css/tpd-lms.css` | Builder views and styles, loaded only on the super admin page when the module is on |

| `class-tpd-lms-importer.php` | Question import: .xlsx, .csv, .docx, .txt, pasted text |
| `class-tpd-lms-student.php` | Advisor side: hub data, lesson player actions (`tpd_lms_s_*`), checkout hooks, downloads, certificate page |
| `assets/js/tpd-lms-student.js` | Lesson player inside Resources & Tools (advisor and supplier dashboards) |
| `templates/lms/certificate.php` | Public certificate page at `/certificate-verify/{CODE}/` |
| `class-tpd-lms-coupons.php` | Coupons: percent or fixed, per course or all, expiry, use limit |
| `class-tpd-lms-exam.php` | Exam password or one-time codes, lockout, proctor requests, proctor page `/proctor/{token}/` |
| `templates/lms/proctor.php` | The proctor's private page (no account needed) |
| `class-tpd-lms-reports.php` | Students per course, one student's progress and codes, per-question quiz results |
| `class-tpd-lms-assign.php` | Assignment hand-ins: private file storage (uploads/tpd-lms-private, web access denied), review, emails |
| `class-tpd-lms-mail.php` | Plain-text emails: enrolled (with receipt), certificate, proctoring; failures are logged, never fatal |

When the module is off:
- Super Admin → LMS Studio shows a short "LMS is off" note above the toolkits manager.
- Resources & Tools shows toolkits only, and a notice for super admins.
- Course checkout is refused.

When it's on:
- Resources & Tools reads courses from the native tables.
- "Start course" opens the player in the dashboard.
- `TPD_Tool_Financial` prices and fulfils `lms_course` items natively. Course access included in a membership plan costs 0, and paying again after access ends renews it.

The name printed on certificates is set in LMS Studio (default "Travel Advisor Resource Center"). So is the support email that gets proctor requests (default admin@thetarc.com).

Coupons are typed in TPD's checkout modal. The LMS script adds the field and sends the code with TPD's own checkout requests (`lms_coupon`), so `tpd-dashboard.js` is unchanged. A use is counted once, on a confirmed payment.

Exam passwords and one-time codes are stored encrypted (AES-256-GCM, keyed from the site's WordPress salts). Codes are also hashed for matching.

If the WordPress salts change, saved passwords and unused codes can no longer be read. Set the password again and issue new codes.

Known limitation: lesson files live in the WordPress media library. The download button streams them only to advisors who can open the lesson, but the media file URL itself is public if someone knows it.

Students and instructors are WordPress users. Payments stay in `{prefix}tpd_transactions`; `enrollments.tx_id` links the transaction that paid for a seat.

## Rules

The rules are ported from the TARC learning platform's `briefing.md`:
- **Lesson locks**, checked in order: not enrolled (previews only), access expired, drip date, sequential order, final exam waiting for every other lesson. Course managers bypass locks.
- **Completion:** quizzes and exams complete only when passed.
- **Certificates:** issued at 100% progress when certificates are on and the final exam is passed.
- **Grading:** multiple select is all or nothing. Short answers ignore case and spacing.
- **Question banks:** proportional draws by topic, with unseen questions first.
- **Time limits:** enforced on the server.
- **One final exam per course.**

Hooks for the rest of TPD:
- `tpd_lms_enrolled`
- `tpd_lms_course_completed`
- `tpd_lms_certificate_issued`
- `tpd_lms_attempt_submitted`

## Tests

Run them against a **local** WordPress with TPD active. Tests create and delete their own users and courses.

```bash
TPD_WP_LOAD=/path/to/wordpress/wp-load.php php phpunit.phar -c tests/lms/phpunit.xml
```

## Lesson types

The types match Thinkific's: video, text, PDF, quiz, audio, download, multimedia, survey, presentation, assignment, exam and live (Zoom). Type-specific options live in `lessons.settings` (JSON, cleaned per type by `TPD_LMS_Store::sanitize_settings()`).

| Type | How it works |
|---|---|
| PDF | Pages drawn in the lesson with PDF.js (loaded from cdnjs only when needed). The file is streamed through a lock-checked endpoint (`tpd_lms_file`). The download button is optional. |
| Presentation | Thinkific's model: upload a PDF (PowerPoint and Keynote export to PDF), one slide per page. Each slide can have narration (uploaded, or recorded in the browser) and text under it. Options: view every slide before completing, auto-advance when a slide's narration ends, download. |
| Audio | A media-library file or an https link, with a transcript. |
| Download | Several files per lesson. Only files that belong to the lesson can be downloaded. |
| Multimedia | An https embed (Typeform, Canva, H5P, Google Slides…), with a set height. |
| Live (Zoom) | Join link, a start time stored in UTC and shown in each advisor's time zone, a join button from 15 minutes before the start, and a recording link afterwards. It doesn't create Zoom meetings automatically. |
| Survey | A quiz with a pass mark of 0 and no right answers. One response, which completes the lesson. Results show per question. |
| Assignment | A file and/or written answer. Completes on staff approval (or on hand-in if approval is off). Returned work can be sent again. |

## Exam card

With "Show the final exam as a separate card" on, a course's final exam leaves the curriculum and appears as its own card in Resources & Tools → My Exams (`templates/lms/exam-cards.php`, `class-tpd-lms-examcard.php`).
- **Unlocking:** the card is locked until every other lesson is done, or until an admin unlocks it early, either with the "Unlock exam now" button on the student's page or with a one-time unlock key the advisor types on the card.
- **Keys:** stored as a hash, they expire, and five wrong tries lock the advisor out for 15 minutes.
- **What still applies:** access expiry, exam passwords and proctor codes, and the certificate rule.
