# Native LMS module

The TARC learning platform (courses, lessons, quizzes, exams, certificates) built into TPD.

The module is **off by default**. While it's off, it creates no tables and adds no hooks, and TPD keeps using the external LMS bridge in `includes/class-tpd-lms.php`.

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

When the module is off, Super Admin → LMS Studio shows the existing bridge pane unchanged.

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
