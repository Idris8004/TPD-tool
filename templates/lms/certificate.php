<?php
/**
 * Public certificate page: /certificate-verify/{CODE}/ (and a lookup form at /certificate-verify/).
 * Anyone with the code can check a certificate is genuine. Prints cleanly on one page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$code   = (string) get_query_var( 'tpd_lms_cert_code' );
$cert   = '' !== $code ? TPD_LMS_Progress::find_certificate( $code ) : null;
$course = $cert ? TPD_LMS_Store::get_course( (int) $cert->course_id ) : null;
$holder = $cert ? get_userdata( (int) $cert->user_id ) : null;
if ( '' !== $code && ! $cert ) {
	status_header( 404 );
}
$site = TPD_LMS_Native::issuer_name();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title><?php echo esc_html( $cert ? sprintf( /* translators: %s: course title */ __( 'Certificate: %s', 'tpd-tool' ), $course ? $course->title : '' ) : __( 'Verify a certificate', 'tpd-tool' ) ); ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
	<style>
		:root { --navy: #0b1526; --blue: #164e87; --teal: #00798c; --gold: #b45309; --line: #e2e8f0; --muted: #64748b; }
		* { box-sizing: border-box; }
		body { margin: 0; font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f1f5f9; color: var(--navy); }
		.wrap { max-width: 960px; margin: 0 auto; padding: 32px 16px 48px; }
		.verified { display: flex; gap: 10px; align-items: center; background: #dcfce7; color: #166534; border-radius: 10px; padding: 12px 16px; font-weight: 700; font-size: 14px; margin-bottom: 18px; }
		.missing { background: #fee2e2; color: #b91c1c; }
		.cert { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 56px 48px; text-align: center; position: relative; box-shadow: 0 10px 30px rgba(11,21,38,.08); }
		.cert::before { content: ""; position: absolute; inset: 14px; border: 2px solid var(--teal); border-radius: 8px; pointer-events: none; }
		.org { font-size: 13px; font-weight: 800; letter-spacing: .08em; color: var(--teal); text-transform: uppercase; }
		h1 { font-family: 'Playfair Display', Georgia, serif; font-size: 40px; margin: 14px 0 6px; color: var(--navy); }
		.lead { color: var(--muted); font-size: 15px; margin: 0; }
		.name { font-family: 'Playfair Display', Georgia, serif; font-size: 34px; margin: 22px 0 6px; color: var(--blue); }
		.course { font-size: 20px; font-weight: 800; margin: 18px 0 0; }
		.meta { display: flex; justify-content: center; gap: 36px; flex-wrap: wrap; margin-top: 36px; font-size: 13px; color: var(--muted); }
		.meta strong { display: block; color: var(--navy); font-size: 15px; margin-top: 2px; }
		.actions { display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap; }
		.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: var(--navy); font: inherit; font-weight: 700; font-size: 14px; cursor: pointer; text-decoration: none; }
		.btn-primary { background: var(--navy); border-color: var(--navy); color: #fff; }
		form.lookup { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 28px; max-width: 520px; margin: 0 auto; }
		form.lookup h1 { font-size: 28px; margin-top: 0; }
		label { display: block; font-weight: 700; font-size: 14px; margin: 16px 0 6px; }
		input[type=text] { width: 100%; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; font-size: 16px; letter-spacing: .08em; text-transform: uppercase; }
		@media (max-width: 640px) { .cert { padding: 40px 22px; } h1 { font-size: 30px; } .name { font-size: 26px; } }
		@media print {
			body { background: #fff; }
			.wrap { padding: 0; max-width: none; }
			.verified, .actions, .no-print { display: none !important; }
			.cert { box-shadow: none; border: 0; padding: 64px 48px; }
			@page { size: landscape; margin: 12mm; }
		}
	</style>
</head>
<body>
<main class="wrap">
	<?php if ( $cert && $course ) : ?>
		<div class="verified" role="status">
			<span aria-hidden="true">✓</span>
			<?php
			/* translators: %s: site name */
			echo esc_html( sprintf( __( 'Verified: this certificate was issued by %s.', 'tpd-tool' ), $site ) );
			?>
		</div>
		<article class="cert">
			<div class="org"><?php echo esc_html( $site ); ?></div>
			<h1><?php esc_html_e( 'Certificate of completion', 'tpd-tool' ); ?></h1>
			<p class="lead"><?php esc_html_e( 'This certifies that', 'tpd-tool' ); ?></p>
			<div class="name"><?php echo esc_html( $holder ? $holder->display_name : __( 'A former member', 'tpd-tool' ) ); ?></div>
			<p class="lead"><?php esc_html_e( 'has successfully completed', 'tpd-tool' ); ?></p>
			<div class="course"><?php echo esc_html( $course->title ); ?></div>
			<div class="meta">
				<div><?php esc_html_e( 'Issued', 'tpd-tool' ); ?><strong><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $cert->issued_at . ' UTC' ) ) ); ?></strong></div>
				<?php if ( null !== $cert->final_score ) : ?>
					<div><?php esc_html_e( 'Final exam score', 'tpd-tool' ); ?><strong><?php echo esc_html( (int) $cert->final_score ); ?>%</strong></div>
				<?php endif; ?>
				<div><?php esc_html_e( 'Verification code', 'tpd-tool' ); ?><strong><?php echo esc_html( $cert->code ); ?></strong></div>
			</div>
		</article>
		<div class="actions">
			<button type="button" class="btn btn-primary" onclick="window.print()"><?php esc_html_e( 'Print or save as PDF', 'tpd-tool' ); ?></button>
			<a class="btn" href="<?php echo esc_url( home_url( '/' . TPD_LMS_Student::CERT_PATH . '/' ) ); ?>"><?php esc_html_e( 'Verify another certificate', 'tpd-tool' ); ?></a>
		</div>
	<?php else : ?>
		<?php if ( '' !== $code ) : ?>
			<div class="verified missing" role="alert">
				<?php
				/* translators: %s: verification code entered */
				echo esc_html( sprintf( __( 'No certificate matches the code %s. Check the code and try again.', 'tpd-tool' ), $code ) );
				?>
			</div>
		<?php endif; ?>
		<form class="lookup" method="get" action="<?php echo esc_url( home_url( '/' . TPD_LMS_Student::CERT_PATH . '/' ) ); ?>">
			<h1><?php esc_html_e( 'Verify a certificate', 'tpd-tool' ); ?></h1>
			<p class="lead"><?php echo esc_html( sprintf( /* translators: %s: site name */ __( 'Enter the code printed on a %s certificate to confirm it is genuine.', 'tpd-tool' ), $site ) ); ?></p>
			<label for="code"><?php esc_html_e( 'Verification code', 'tpd-tool' ); ?></label>
			<input type="text" id="code" name="code" required placeholder="ABCD-EFGH-JKLM" value="<?php echo esc_attr( $code ); ?>" autocomplete="off">
			<div class="actions" style="justify-content:flex-start;"><button type="submit" class="btn btn-primary"><?php esc_html_e( 'Check code', 'tpd-tool' ); ?></button></div>
		</form>
	<?php endif; ?>
</main>
</body>
</html>
