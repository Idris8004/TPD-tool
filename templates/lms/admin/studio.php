<?php
/**
 * Native LMS course builder, shown in Super Admin → LMS Studio while the native LMS is on.
 * The views (course list, course editor, lesson editor) are drawn by assets/js/tpd-lms-admin.js.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="tpd-lms-studio">
	<div class="tpd-sa-card tpd-lms-head">
		<div>
			<h2 class="tpd-lms-h2"><i class="fa-solid fa-chalkboard-user"></i> <?php esc_html_e( 'LMS Studio', 'tpd-tool' ); ?></h2>
			<p class="tpd-lms-muted"><?php esc_html_e( 'Build courses for travel advisors: modules, lessons, quizzes and the final exam.', 'tpd-tool' ); ?></p>
		</div>
		<nav class="tpd-lms-crumbs" id="tpd-lms-crumbs" aria-label="<?php esc_attr_e( 'Where you are in LMS Studio', 'tpd-tool' ); ?>"></nav>
	</div>

	<div id="tpd-lms-notice" class="tpd-lms-notice" role="status" aria-live="polite" hidden></div>

	<div id="tpd-lms-builder" class="tpd-lms-builder">
		<div class="tpd-sa-card tpd-lms-loading"><i class="fa-solid fa-spinner fa-spin"></i> <?php esc_html_e( 'Loading courses…', 'tpd-tool' ); ?></div>
	</div>

	<noscript>
		<div class="tpd-sa-card"><?php esc_html_e( 'LMS Studio needs JavaScript. Turn it on in your browser to build courses.', 'tpd-tool' ); ?></div>
	</noscript>
</div>
