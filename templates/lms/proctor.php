<?php
/**
 * Private proctor page: /proctor/{token}/. Needs no account; the 48-character
 * token in the link is the key, and only its hash is stored.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$request = get_query_var( 'tpd_lms_proctor_request' );
$notice  = (string) get_query_var( 'tpd_lms_proctor_notice' );
$c       = $request ? TPD_LMS_Exam::context( $request ) : null;
$code    = $c && $c['code'] ? $c['code'] : null;
$plain   = $code ? TPD_LMS_Exam::decrypt( (string) $code->code_enc ) : null;
$status  = $code ? TPD_LMS_Exam::code_status( $code ) : '';
$issuer  = TPD_LMS_Native::issuer_name();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<meta name="referrer" content="no-referrer">
	<title><?php esc_html_e( 'Proctor page', 'tpd-tool' ); ?></title>
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root { --navy: #0b1526; --teal: #00798c; --line: #e2e8f0; --muted: #64748b; }
		* { box-sizing: border-box; }
		body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f1f5f9; color: var(--navy); }
		.wrap { max-width: 640px; margin: 0 auto; padding: 32px 16px 48px; }
		.card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 24px; margin-bottom: 16px; }
		.org { font-size: 12px; font-weight: 800; letter-spacing: .08em; color: var(--teal); text-transform: uppercase; }
		h1 { font-size: 24px; margin: 6px 0 4px; }
		h2 { font-size: 17px; margin: 0 0 10px; }
		p, li { font-size: 15px; line-height: 1.6; }
		.muted { color: var(--muted); font-size: 14px; }
		dl { display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; margin: 12px 0 0; font-size: 15px; }
		dt { color: var(--muted); }
		dd { margin: 0; font-weight: 600; }
		.code { font-size: 34px; font-weight: 800; letter-spacing: .12em; text-align: center; background: #f8fafc; border: 2px dashed var(--teal); border-radius: 12px; padding: 18px; margin: 12px 0; font-variant-numeric: tabular-nums; }
		.badge { display: inline-block; font-size: 13px; font-weight: 700; padding: 4px 12px; border-radius: 999px; }
		.ok { background: #dcfce7; color: #166534; } .warn { background: #fef3c7; color: #92400e; } .err { background: #fee2e2; color: #b91c1c; }
		.notice { padding: 12px 16px; border-radius: 10px; font-weight: 600; margin-bottom: 16px; }
		button { font: inherit; font-weight: 700; font-size: 15px; padding: 11px 18px; border-radius: 8px; border: 1px solid var(--navy); background: var(--navy); color: #fff; cursor: pointer; }
		button.secondary { background: #fff; color: var(--navy); border-color: var(--line); }
		textarea { width: 100%; font: inherit; font-size: 15px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin: 8px 0 10px; }
		label { font-weight: 700; font-size: 14px; }
	</style>
</head>
<body>
<main class="wrap">
	<?php if ( ! $request || ! $c ) : ?>
		<div class="card">
			<div class="org"><?php echo esc_html( $issuer ); ?></div>
			<h1><?php esc_html_e( 'This proctor link doesn’t work', 'tpd-tool' ); ?></h1>
			<p><?php esc_html_e( 'It may have been replaced by a newer email, or mistyped. Use the most recent email you received, or contact the course team.', 'tpd-tool' ); ?></p>
			<p class="muted"><?php echo esc_html( TPD_LMS_Mail::support_email() ); ?></p>
		</div>
	<?php else : ?>
		<?php
		$messages = array(
			'confirmed'    => array( 'ok', __( 'Thank you. The session is confirmed.', 'tpd-tool' ) ),
			'reported'     => array( 'warn', __( 'Thank you. Your report was sent to the course team.', 'tpd-tool' ) ),
			'report_empty' => array( 'err', __( 'Describe the problem before sending the report.', 'tpd-tool' ) ),
			'expired'      => array( 'err', __( 'The page had expired. Try again.', 'tpd-tool' ) ),
		);
		if ( isset( $messages[ $notice ] ) ) :
			?>
			<div class="notice <?php echo esc_attr( $messages[ $notice ][0] ); ?>" role="status"><?php echo esc_html( $messages[ $notice ][1] ); ?></div>
		<?php endif; ?>

		<div class="card">
			<div class="org"><?php echo esc_html( $issuer ); ?></div>
			<h1><?php esc_html_e( 'Proctor page', 'tpd-tool' ); ?></h1>
			<p class="muted"><?php echo esc_html( sprintf( /* translators: %s: proctor name */ __( 'For %s. Keep this page private.', 'tpd-tool' ), $request->proctor_name ) ); ?></p>
			<dl>
				<dt><?php esc_html_e( 'Student', 'tpd-tool' ); ?></dt><dd><?php echo esc_html( $c['student'] ? $c['student']->display_name : '' ); ?></dd>
				<dt><?php esc_html_e( 'Exam', 'tpd-tool' ); ?></dt><dd><?php echo esc_html( $c['lesson'] ? $c['lesson']->title : '' ); ?></dd>
				<dt><?php esc_html_e( 'Course', 'tpd-tool' ); ?></dt><dd><?php echo esc_html( $c['course'] ? $c['course']->title : '' ); ?></dd>
				<?php if ( $request->preferred_at ) : ?>
					<dt><?php esc_html_e( 'Planned for', 'tpd-tool' ); ?></dt><dd><?php echo esc_html( TPD_LMS_Exam::format_time( $request->preferred_at, $request->timezone ) ); ?></dd>
				<?php endif; ?>
				<?php if ( $c['quiz'] && $c['quiz']->time_limit_minutes ) : ?>
					<dt><?php esc_html_e( 'Time limit', 'tpd-tool' ); ?></dt><dd><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d minutes', 'tpd-tool' ), (int) $c['quiz']->time_limit_minutes ) ); ?></dd>
				<?php endif; ?>
			</dl>
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'One-time exam code', 'tpd-tool' ); ?></h2>
			<?php if ( 'active' === $status && $plain ) : ?>
				<div class="code" aria-label="<?php esc_attr_e( 'Exam code', 'tpd-tool' ); ?>"><?php echo esc_html( $plain ); ?></div>
				<p class="muted"><?php echo esc_html( sprintf( /* translators: %s: expiry time */ __( 'Works once, for this student only, until %s.', 'tpd-tool' ), TPD_LMS_Exam::format_time( $code->expires_at, $request->timezone ) ) ); ?></p>
			<?php elseif ( 'used' === $status ) : ?>
				<p><span class="badge ok"><?php esc_html_e( 'Used', 'tpd-tool' ); ?></span> <?php esc_html_e( 'The student has started the exam with this code.', 'tpd-tool' ); ?></p>
			<?php elseif ( 'expired' === $status ) : ?>
				<p><span class="badge err"><?php esc_html_e( 'Expired', 'tpd-tool' ); ?></span> <?php esc_html_e( 'Ask the course team for a new code.', 'tpd-tool' ); ?></p>
			<?php else : ?>
				<p><span class="badge err"><?php esc_html_e( 'Cancelled', 'tpd-tool' ); ?></span> <?php esc_html_e( 'The course team cancelled this code.', 'tpd-tool' ); ?></p>
			<?php endif; ?>
			<h2 style="margin-top:18px;"><?php esc_html_e( 'How to proctor', 'tpd-tool' ); ?></h2>
			<ol>
				<li><?php esc_html_e( 'Meet the student (in person or on a video call with their screen shared).', 'tpd-tool' ); ?></li>
				<li><?php esc_html_e( 'When they open the exam, type the code in for them. Don’t share it in advance.', 'tpd-tool' ); ?></li>
				<li><?php esc_html_e( 'Stay until they submit, then confirm the session below.', 'tpd-tool' ); ?></li>
			</ol>
		</div>

		<div class="card">
			<h2><?php esc_html_e( 'After the exam', 'tpd-tool' ); ?></h2>
			<?php if ( $request->proctor_confirmed_at ) : ?>
				<p><span class="badge <?php echo $request->proctor_flagged ? 'warn' : 'ok'; ?>"><?php echo $request->proctor_flagged ? esc_html__( 'Problem reported', 'tpd-tool' ) : esc_html__( 'Session confirmed', 'tpd-tool' ); ?></span></p>
				<?php if ( $request->proctor_flagged && $request->proctor_report ) : ?>
					<p class="muted"><?php echo esc_html( $request->proctor_report ); ?></p>
				<?php endif; ?>
			<?php else : ?>
				<form method="post">
					<?php wp_nonce_field( 'tpd_lms_proctor_' . $request->id ); ?>
					<button type="submit" name="proctor_action" value="confirm"><?php esc_html_e( 'The session went well', 'tpd-tool' ); ?></button>
				</form>
				<form method="post" style="margin-top:18px;">
					<?php wp_nonce_field( 'tpd_lms_proctor_' . $request->id ); ?>
					<label for="proctor_report"><?php esc_html_e( 'Or report a problem', 'tpd-tool' ); ?></label>
					<textarea id="proctor_report" name="proctor_report" rows="3" placeholder="<?php esc_attr_e( 'What happened?', 'tpd-tool' ); ?>"></textarea>
					<button type="submit" class="secondary" name="proctor_action" value="report"><?php esc_html_e( 'Send report', 'tpd-tool' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</main>
</body>
</html>
