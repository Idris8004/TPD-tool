<?php
/**
 * Shared Resources & Tools Hub Partial (My Resources & All Resources)
 * Used inside Advisor Dashboard (#tpd-view-resources) and Supplier Dashboard (#tpd-supp-resources).
 * Combines Laravel LMS Courses (Catalog, Enrollments, Certificates, Signed SSO) & TPD Sales Toolkits.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$uid             = get_current_user_id();
$lms_cfg         = class_exists( 'TPD_Tool_LMS' ) ? TPD_Tool_LMS::get_settings() : array();
$lms_configured  = class_exists( 'TPD_Tool_LMS' ) && TPD_Tool_LMS::is_configured();
$lms_catalog     = $lms_configured ? TPD_Tool_LMS::get_catalog( false, 'published' ) : array();
$lms_enrollments = $lms_configured ? TPD_Tool_LMS::get_user_enrollments( $uid, false ) : array();
$tpd_resources   = class_exists( 'TPD_Tool_LMS' ) ? array_values( TPD_Tool_LMS::get_custom_resources( false ) ) : array();
$pay_cfg         = class_exists( 'TPD_Tool_Settings' ) ? TPD_Tool_Settings::get_payment_settings() : array();
$sym             = ! empty( $pay_cfg['currency_symbol'] ) ? $pay_cfg['currency_symbol'] : '$';

$enrolled_by_slug = array();
$certs_count      = 0;
foreach ( (array) $lms_enrollments as $enr ) {
	$slug = (string) ( $enr['course_slug'] ?? '' );
	if ( '' !== $slug ) {
		$enrolled_by_slug[ $slug ] = $enr;
	}
	if ( ! empty( $enr['certificate'] ) ) {
		$certs_count++;
	}
}

$my_unlocked_resources = array();
foreach ( $tpd_resources as $res_item ) {
	if ( class_exists( 'TPD_Tool_LMS' ) && TPD_Tool_LMS::user_has_resource_access( $uid, $res_item ) ) {
		$my_unlocked_resources[] = $res_item;
	}
}

$my_total_count  = count( $lms_enrollments ) + count( $my_unlocked_resources );
$all_total_count = count( $lms_catalog ) + count( $tpd_resources );
?>

<div class="tpd-resources-hub" id="tpd-resources-hub-root">
	<!-- Hero Header Card -->
	<div class="tpd-card-box mb-4" style="background:linear-gradient(135deg, #0b1526 0%, #013243 55%, #00798c 100%); color:#ffffff; padding:26px 28px; border-radius:14px;">
		<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px;">
			<div>
				<span style="display:inline-block; background:rgba(245,158,11,0.2); border:1px solid rgba(245,158,11,0.45); color:#fcd34d; font-size:11px; font-weight:800; padding:4px 12px; border-radius:999px; letter-spacing:0.06em; margin-bottom:10px;">
					<i class="fa-solid fa-graduation-cap"></i> <?php esc_html_e( 'TARC ACADEMY & PARTNER RESOURCES', 'tpd-tool' ); ?>
				</span>
				<h2 style="margin:0 0 6px; font-size:22px; font-weight:800; color:#ffffff;"><?php esc_html_e( 'Resources, Courses & Sales Tools', 'tpd-tool' ); ?></h2>
				<p style="margin:0; font-size:13.5px; color:#cbd5e1; max-width:650px; line-height:1.5;">
					<?php esc_html_e( 'Browse certified training courses and partner toolkits, check out inside your dashboard, and launch your active courses directly with 1-click Single Sign-On.', 'tpd-tool' ); ?>
				</p>
			</div>

			<div style="display:flex; gap:12px; flex-wrap:wrap;">
				<div style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); border-radius:10px; padding:12px 16px; min-width:105px; text-align:center;">
					<strong style="display:block; font-size:22px; color:#ffffff; line-height:1.1;" id="tpd-res-stat-my"><?php echo esc_html( $my_total_count ); ?></strong>
					<span style="font-size:11px; color:#bae6fd; font-weight:600;"><?php esc_html_e( 'My Resources', 'tpd-tool' ); ?></span>
				</div>
				<div style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); border-radius:10px; padding:12px 16px; min-width:105px; text-align:center;">
					<strong style="display:block; font-size:22px; color:#fcd34d; line-height:1.1;"><?php echo esc_html( $certs_count ); ?></strong>
					<span style="font-size:11px; color:#bae6fd; font-weight:600;"><?php esc_html_e( 'Certificates', 'tpd-tool' ); ?></span>
				</div>
				<div style="background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.18); border-radius:10px; padding:12px 16px; min-width:105px; text-align:center;">
					<strong style="display:block; font-size:22px; color:#ffffff; line-height:1.1;"><?php echo esc_html( $all_total_count ); ?></strong>
					<span style="font-size:11px; color:#bae6fd; font-weight:600;"><?php esc_html_e( 'Catalog Items', 'tpd-tool' ); ?></span>
				</div>
			</div>
		</div>
	</div>

	<?php if ( ! $lms_configured && current_user_can( 'manage_options' ) ) : ?>
		<div style="background:#fffbeb; border:1px solid #fcd34d; color:#92400e; border-radius:10px; padding:14px 18px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; font-size:13px;">
			<div>
				<i class="fa-solid fa-plug-circle-exclamation"></i>
				<strong><?php esc_html_e( 'Admin Notice:', 'tpd-tool' ); ?></strong>
				<?php esc_html_e( 'LMS API Token / SSO Secret is not yet configured. Local TPD Toolkits are active; configure the LMS bridge in Super Admin -> LMS Studio to display live courses.', 'tpd-tool' ); ?>
			</div>
			<a href="<?php echo esc_url( home_url( '/tpd-admin/#sa-lms-studio' ) ); ?>" class="tpd-btn tpd-btn-xs tpd-btn-darkblue" style="text-decoration:none;">
				<?php esc_html_e( 'Configure LMS Studio', 'tpd-tool' ); ?>
			</a>
		</div>
	<?php endif; ?>

	<!-- Sub-Tabs Switcher + Filter Toolbar -->
	<div class="tpd-card-box mb-4" style="padding:16px 20px;">
		<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:14px;">
			<div style="display:flex; gap:8px; flex-wrap:wrap;">
				<button type="button" class="tpd-btn tpd-btn-sm tpd-res-subtab active" data-subtab="my" style="background:#00798c; color:#ffffff; border:1.5px solid #00798c; border-radius:8px; font-weight:700; padding:9px 18px;">
					<i class="fa-solid fa-bookmark"></i> <?php esc_html_e( 'My Resources', 'tpd-tool' ); ?>
					<span class="tpd-res-pill-count" style="background:rgba(255,255,255,0.22); padding:2px 8px; border-radius:999px; font-size:11px; margin-left:6px;"><?php echo esc_html( $my_total_count ); ?></span>
				</button>
				<button type="button" class="tpd-btn tpd-btn-sm tpd-res-subtab" data-subtab="all" style="background:#f8fafc; color:#334155; border:1.5px solid #cbd5e1; border-radius:8px; font-weight:700; padding:9px 18px;">
					<i class="fa-solid fa-layer-group"></i> <?php esc_html_e( 'All Resources', 'tpd-tool' ); ?>
					<span class="tpd-res-pill-count" style="background:#e2e8f0; color:#0f172a; padding:2px 8px; border-radius:999px; font-size:11px; margin-left:6px;"><?php echo esc_html( $all_total_count ); ?></span>
				</button>
			</div>

			<div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
				<div style="position:relative;">
					<i class="fa-solid fa-magnifying-glass" style="position:absolute; left:11px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:12.5px;"></i>
					<input type="text" id="tpd-res-search-input" placeholder="<?php esc_attr_e( 'Search courses or toolkits...', 'tpd-tool' ); ?>" class="tpd-input" style="padding:8px 12px 8px 32px; width:230px; font-size:13px;">
				</div>
				<select id="tpd-res-type-filter" class="tpd-select" style="width:165px; padding:8px 10px; font-size:13px;">
					<option value="all"><?php esc_html_e( 'All Types', 'tpd-tool' ); ?></option>
					<option value="lms_course"><?php esc_html_e( 'LMS Courses', 'tpd-tool' ); ?></option>
					<option value="resource"><?php esc_html_e( 'Toolkits & Guides', 'tpd-tool' ); ?></option>
					<option value="free"><?php esc_html_e( 'Free Only', 'tpd-tool' ); ?></option>
					<option value="paid"><?php esc_html_e( 'Paid Only', 'tpd-tool' ); ?></option>
				</select>
				<?php if ( $lms_configured ) : ?>
					<button type="button" id="tpd-btn-sync-my-resources" class="tpd-btn tpd-btn-xs tpd-btn-outline" title="<?php esc_attr_e( 'Refresh progress and courses from LMS', 'tpd-tool' ); ?>" style="padding:9px 12px; border-radius:8px;">
						<i class="fa-solid fa-arrows-rotate"></i> <?php esc_html_e( 'Refresh', 'tpd-tool' ); ?>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- =========================================================================
	     SUB-TAB 1: MY RESOURCES (Purchased/Enrolled Courses + Unlocked Toolkits)
	     ========================================================================= -->
	<div id="tpd-res-pane-my" class="tpd-res-pane active">
		<?php if ( ! empty( $lms_enrollments ) ) : ?>
			<div class="tpd-card-box mb-4">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
					<div>
						<h3 style="margin:0; font-size:17px;"><i class="fa-solid fa-play-circle text-blue"></i> <?php esc_html_e( 'My Active LMS Courses', 'tpd-tool' ); ?></h3>
						<span style="font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Launch any enrolled course seamlessly — no extra LMS sign-in needed.', 'tpd-tool' ); ?></span>
					</div>
					<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-outline tpd-btn-sso-launch" data-redirect="/courses">
						<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Open Learning Portal', 'tpd-tool' ); ?>
					</button>
				</div>

				<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:18px;">
					<?php foreach ( $lms_enrollments as $enr ) :
						$c_title    = $enr['course_title'] ?? 'Course';
						$c_sub      = $enr['course_subtitle'] ?? '';
						$c_thumb    = ! empty( $enr['thumbnail_url'] ) ? $enr['thumbnail_url'] : 'https://images.unsplash.com/photo-1488646953014-85cb44e25828?w=600&auto=format&fit=crop&q=80';
						$progress   = max( 0, min( 100, (int) ( $enr['progress_percent'] ?? 0 ) ) );
						$learn_path = ! empty( $enr['learn_path'] ) ? $enr['learn_path'] : '/courses';
						$cert       = ! empty( $enr['certificate'] ) && is_array( $enr['certificate'] ) ? $enr['certificate'] : null;
						$completed  = ! empty( $enr['completed_at'] ) || $progress >= 100;
					?>
						<div class="tpd-res-filter-card" data-type="lms_course" data-price-kind="owned" data-search="<?php echo esc_attr( strtolower( $c_title . ' ' . $c_sub ) ); ?>" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 6px rgba(15,23,42,0.04);">
							<div>
								<div style="height:140px; background-image:linear-gradient(180deg, rgba(11,21,38,0.12) 0%, rgba(11,21,38,0.55) 100%), url('<?php echo esc_url( $c_thumb ); ?>'); background-size:cover; background-position:center; padding:12px; display:flex; justify-content:space-between; align-items:flex-start;">
									<span style="background:<?php echo $completed ? '#16a34a' : '#00798c'; ?>; color:#fff; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:6px; text-transform:uppercase;">
										<?php echo $completed ? esc_html__( 'Completed', 'tpd-tool' ) : esc_html__( 'Enrolled', 'tpd-tool' ); ?>
									</span>
									<?php if ( $cert ) : ?>
										<span style="background:#fef3c7; color:#b45309; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:6px;">
											<i class="fa-solid fa-award"></i> <?php esc_html_e( 'Certificate Earned', 'tpd-tool' ); ?>
										</span>
									<?php endif; ?>
								</div>
								<div style="padding:16px;">
									<h4 style="margin:0 0 6px; font-size:15.5px; font-weight:800; color:#0f172a; line-height:1.35;"><?php echo esc_html( $c_title ); ?></h4>
									<?php if ( $c_sub ) : ?>
										<p style="margin:0 0 12px; font-size:12.5px; color:#64748b; line-height:1.45;"><?php echo esc_html( wp_trim_words( $c_sub, 16 ) ); ?></p>
									<?php endif; ?>

									<div style="margin-top:10px;">
										<div style="display:flex; justify-content:space-between; font-size:11.5px; font-weight:700; color:#334155; margin-bottom:5px;">
											<span><?php esc_html_e( 'Progress', 'tpd-tool' ); ?></span>
											<span><?php echo esc_html( $progress ); ?>%</span>
										</div>
										<div style="height:7px; background:#e2e8f0; border-radius:999px; overflow:hidden;">
											<div style="height:100%; width:<?php echo esc_attr( $progress ); ?>%; background:<?php echo $completed ? '#16a34a' : 'linear-gradient(90deg, #00798c, #00b4d8)'; ?>; border-radius:999px;"></div>
										</div>
									</div>
								</div>
							</div>

							<div style="padding:12px 16px 16px; display:flex; gap:8px; flex-wrap:wrap; border-top:1px solid #f1f5f9;">
								<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-btn-sso-launch" data-redirect="<?php echo esc_attr( $learn_path ); ?>" style="flex:1; background:#00798c; border-color:#00798c;">
									<i class="fa-solid fa-play"></i> <?php echo $progress > 0 ? esc_html__( 'Resume Course', 'tpd-tool' ) : esc_html__( 'Start Course', 'tpd-tool' ); ?>
								</button>
								<?php if ( $cert && ! empty( $cert['path'] ) ) : ?>
									<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-outline tpd-btn-sso-launch" data-redirect="<?php echo esc_attr( $cert['path'] ); ?>" title="<?php esc_attr_e( 'View Official Certificate', 'tpd-tool' ); ?>">
										<i class="fa-solid fa-certificate text-gold"></i>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $my_unlocked_resources ) ) : ?>
			<div class="tpd-card-box mb-4">
				<h3 style="margin:0 0 14px; font-size:17px;"><i class="fa-solid fa-folder-open text-gold"></i> <?php esc_html_e( 'My Sales Toolkits, Guides & Partner Resources', 'tpd-tool' ); ?></h3>
				<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(270px, 1fr)); gap:16px;">
					<?php foreach ( $my_unlocked_resources as $res ) :
						$r_url  = ! empty( $res['file_url'] ) ? $res['file_url'] : '#';
						$is_ext = ( 0 === strpos( $r_url, 'http' ) );
					?>
						<div class="tpd-res-filter-card" data-type="resource" data-price-kind="free" data-search="<?php echo esc_attr( strtolower( $res['title'] . ' ' . $res['category'] . ' ' . $res['description'] ) ); ?>" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:18px; display:flex; flex-direction:column; justify-content:space-between;">
							<div>
								<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
									<span style="background:#e0f2fe; color:#0369a1; font-size:10.5px; font-weight:800; padding:3px 9px; border-radius:6px;"><?php echo esc_html( $res['category'] ?: 'Toolkit' ); ?></span>
									<span style="color:#16a34a; font-size:11px; font-weight:700;"><i class="fa-solid fa-circle-check"></i> <?php esc_html_e( 'Unlocked', 'tpd-tool' ); ?></span>
								</div>
								<h4 style="margin:0 0 6px; font-size:15px; font-weight:800; color:#0f172a;"><?php echo esc_html( $res['title'] ); ?></h4>
								<p style="margin:0 0 14px; font-size:12.5px; color:#64748b; line-height:1.5;"><?php echo esc_html( $res['description'] ); ?></p>
							</div>
							<a href="<?php echo esc_url( $r_url ); ?>" <?php echo $is_ext ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="tpd-btn tpd-btn-sm tpd-btn-outline" style="text-decoration:none; text-align:center;">
								<i class="fa-solid fa-arrow-down-to-line"></i> <?php esc_html_e( 'Open / Download Resource', 'tpd-tool' ); ?>
							</a>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( empty( $lms_enrollments ) && empty( $my_unlocked_resources ) ) : ?>
			<div class="tpd-card-box text-center" style="padding:48px 24px;">
				<i class="fa-solid fa-graduation-cap" style="font-size:44px; color:#94a3b8; margin-bottom:12px;"></i>
				<h3 style="margin:0 0 6px;"><?php esc_html_e( 'No Enrolled Courses or Purchased Tools Yet', 'tpd-tool' ); ?></h3>
				<p style="color:#64748b; max-width:480px; margin:0 auto 18px; font-size:13.5px;">
					<?php esc_html_e( 'Explore All Resources to enroll in free or paid certification courses and unlock partner toolkits.', 'tpd-tool' ); ?>
				</p>
				<button type="button" class="tpd-btn tpd-btn-darkblue tpd-switch-res-subtab" data-subtab="all" style="background:#00798c; border-color:#00798c;">
					<i class="fa-solid fa-compass"></i> <?php esc_html_e( 'Browse All Resources', 'tpd-tool' ); ?>
				</button>
			</div>
		<?php endif; ?>
	</div>

	<!-- =========================================================================
	     SUB-TAB 2: ALL RESOURCES (Full LMS Catalog + All Toolkits & Resources)
	     ========================================================================= -->
	<div id="tpd-res-pane-all" class="tpd-res-pane" style="display:none;">
		<?php if ( ! empty( $lms_catalog ) ) : ?>
			<div class="tpd-card-box mb-4">
				<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:16px;">
					<div>
						<h3 style="margin:0; font-size:17px;"><i class="fa-solid fa-graduation-cap text-blue"></i> <?php esc_html_e( 'Training Courses & Certifications (LMS Catalog)', 'tpd-tool' ); ?></h3>
						<span style="font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Purchase or enroll directly here — courses unlock instantly in My Resources with Single Sign-On.', 'tpd-tool' ); ?></span>
					</div>
				</div>

				<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(295px, 1fr)); gap:18px;">
					<?php foreach ( $lms_catalog as $course ) :
						$slug      = (string) ( $course['slug'] ?? '' );
						$title     = (string) ( $course['title'] ?? 'Course' );
						$subtitle  = (string) ( $course['subtitle'] ?? '' );
						$price     = (float) ( $course['price'] ?? 0 );
						$is_free   = ! empty( $course['is_free'] ) || $price <= 0;
						$thumb     = ! empty( $course['thumbnail_url'] ) ? $course['thumbnail_url'] : 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?w=600&auto=format&fit=crop&q=80';
						$lessons   = (int) ( $course['lessons_count'] ?? 0 );
						$minutes   = (int) ( $course['total_minutes'] ?? 0 );
						$level     = (string) ( $course['level'] ?? 'All Levels' );
						$inst_name = $course['instructor']['name'] ?? 'TARC Instructor';
						$enrolled  = isset( $enrolled_by_slug[ $slug ] ) ? $enrolled_by_slug[ $slug ] : null;
						$price_txt = $is_free ? __( 'Free', 'tpd-tool' ) : $sym . number_format( $price, 2 );
					?>
						<div class="tpd-res-filter-card" data-type="lms_course" data-price-kind="<?php echo $is_free ? 'free' : 'paid'; ?>" data-search="<?php echo esc_attr( strtolower( $title . ' ' . $subtitle . ' ' . $inst_name ) ); ?>" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between; box-shadow:0 2px 6px rgba(15,23,42,0.04);">
							<div>
								<div style="height:145px; background-image:linear-gradient(180deg, rgba(11,21,38,0.12) 0%, rgba(11,21,38,0.6) 100%), url('<?php echo esc_url( $thumb ); ?>'); background-size:cover; background-position:center; padding:12px; display:flex; justify-content:space-between; align-items:flex-start;">
									<span style="background:rgba(11,21,38,0.82); color:#fff; font-size:10.5px; font-weight:700; padding:3px 9px; border-radius:6px; text-transform:capitalize;">
										<?php echo esc_html( $level ); ?>
									</span>
									<span style="background:<?php echo $is_free ? '#16a34a' : '#f59e0b'; ?>; color:<?php echo $is_free ? '#fff' : '#0b1526'; ?>; font-size:12px; font-weight:800; padding:4px 10px; border-radius:6px;">
										<?php echo esc_html( $price_txt ); ?>
									</span>
								</div>

								<div style="padding:16px;">
									<h4 style="margin:0 0 6px; font-size:15.5px; font-weight:800; color:#0f172a; line-height:1.35;"><?php echo esc_html( $title ); ?></h4>
									<?php if ( $subtitle ) : ?>
										<p style="margin:0 0 12px; font-size:12.5px; color:#64748b; line-height:1.45;"><?php echo esc_html( wp_trim_words( $subtitle, 16 ) ); ?></p>
									<?php endif; ?>
									<div style="display:flex; gap:12px; flex-wrap:wrap; font-size:11.5px; color:#475569; margin-top:8px;">
										<span><i class="fa-regular fa-circle-play text-blue"></i> <?php echo esc_html( $lessons ); ?> <?php esc_html_e( 'lessons', 'tpd-tool' ); ?></span>
										<?php if ( $minutes > 0 ) : ?>
											<span><i class="fa-regular fa-clock text-teal"></i> <?php echo esc_html( $minutes ); ?> <?php esc_html_e( 'min', 'tpd-tool' ); ?></span>
										<?php endif; ?>
										<?php if ( ! empty( $course['certificate_enabled'] ) ) : ?>
											<span><i class="fa-solid fa-certificate text-gold"></i> <?php esc_html_e( 'Certificate', 'tpd-tool' ); ?></span>
										<?php endif; ?>
									</div>
									<div style="font-size:11.5px; color:#64748b; margin-top:6px;">
										<i class="fa-regular fa-user"></i> <?php echo esc_html( $inst_name ); ?>
									</div>
								</div>
							</div>

							<div style="padding:12px 16px 16px; border-top:1px solid #f1f5f9;">
								<?php if ( $enrolled ) : ?>
									<div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
										<span style="font-size:11.5px; font-weight:700; color:#16a34a;">
											<i class="fa-solid fa-circle-check"></i> <?php esc_html_e( 'Enrolled', 'tpd-tool' ); ?> (<?php echo esc_html( (int) ( $enrolled['progress_percent'] ?? 0 ) ); ?>%)
										</span>
										<button type="button" class="tpd-btn tpd-btn-xs tpd-btn-darkblue tpd-btn-sso-launch" data-redirect="<?php echo esc_attr( $enrolled['learn_path'] ?? '/courses' ); ?>" style="background:#00798c; border-color:#00798c;">
											<i class="fa-solid fa-arrow-up-right-from-square"></i> <?php esc_html_e( 'Launch', 'tpd-tool' ); ?>
										</button>
									</div>
								<?php else : ?>
									<button type="button"
										class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-btn-block tpd-open-checkout-btn"
										data-item-type="lms_course"
										data-item-id="<?php echo esc_attr( $slug ); ?>"
										data-item-title="<?php echo esc_attr( $title ); ?>"
										data-item-price="<?php echo esc_attr( $price ); ?>"
										style="background:<?php echo $is_free ? '#00798c' : '#164e87'; ?>; border-color:transparent;">
										<?php if ( $is_free ) : ?>
											<i class="fa-solid fa-bolt"></i> <?php esc_html_e( 'Enroll Free & Launch', 'tpd-tool' ); ?>
										<?php else : ?>
											<i class="fa-solid fa-cart-shopping"></i> <?php printf( esc_html__( 'Buy & Checkout — %s', 'tpd-tool' ), esc_html( $price_txt ) ); ?>
										<?php endif; ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $tpd_resources ) ) : ?>
			<div class="tpd-card-box">
				<h3 style="margin:0 0 14px; font-size:17px;"><i class="fa-solid fa-toolbox text-gold"></i> <?php esc_html_e( 'Partner Sales Toolkits, Brochures & Guides', 'tpd-tool' ); ?></h3>
				<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px;">
					<?php foreach ( $tpd_resources as $res ) :
						$r_price    = (float) ( $res['price'] ?? 0 );
						$has_access = class_exists( 'TPD_Tool_LMS' ) && TPD_Tool_LMS::user_has_resource_access( $uid, $res );
						$r_url      = ! empty( $res['file_url'] ) ? $res['file_url'] : '#';
						$is_ext     = ( 0 === strpos( $r_url, 'http' ) );
						$r_thumb    = ! empty( $res['thumbnail_url'] ) ? $res['thumbnail_url'] : '';
					?>
						<div class="tpd-res-filter-card" data-type="resource" data-price-kind="<?php echo $r_price <= 0 ? 'free' : 'paid'; ?>" data-search="<?php echo esc_attr( strtolower( $res['title'] . ' ' . $res['category'] . ' ' . $res['description'] ) ); ?>" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; justify-content:space-between;">
							<div>
								<?php if ( $r_thumb ) : ?>
									<div style="height:115px; background-image:url('<?php echo esc_url( $r_thumb ); ?>'); background-size:cover; background-position:center; padding:10px; display:flex; justify-content:space-between; align-items:flex-start;">
										<span style="background:rgba(15,23,42,0.8); color:#fff; font-size:10px; font-weight:700; padding:3px 8px; border-radius:6px;"><?php echo esc_html( $res['category'] ); ?></span>
										<span style="background:<?php echo $r_price <= 0 ? '#16a34a' : '#f59e0b'; ?>; color:<?php echo $r_price <= 0 ? '#fff' : '#0b1526'; ?>; font-size:11px; font-weight:800; padding:3px 9px; border-radius:6px;">
											<?php echo $r_price <= 0 ? esc_html__( 'Free', 'tpd-tool' ) : esc_html( $sym . number_format( $r_price, 2 ) ); ?>
										</span>
									</div>
								<?php endif; ?>
								<div style="padding:16px;">
									<?php if ( ! $r_thumb ) : ?>
										<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
											<span style="background:#e0f2fe; color:#0369a1; font-size:10.5px; font-weight:800; padding:3px 8px; border-radius:6px;"><?php echo esc_html( $res['category'] ); ?></span>
											<strong style="font-size:13px; color:#0b1526;"><?php echo $r_price <= 0 ? esc_html__( 'Free', 'tpd-tool' ) : esc_html( $sym . number_format( $r_price, 2 ) ); ?></strong>
										</div>
									<?php endif; ?>
									<h4 style="margin:0 0 6px; font-size:15px; font-weight:800; color:#0f172a;"><?php echo esc_html( $res['title'] ); ?></h4>
									<p style="margin:0; font-size:12.5px; color:#64748b; line-height:1.5;"><?php echo esc_html( $res['description'] ); ?></p>
								</div>
							</div>

							<div style="padding:12px 16px 16px; border-top:1px solid #f1f5f9;">
								<?php if ( $has_access ) : ?>
									<a href="<?php echo esc_url( $r_url ); ?>" <?php echo $is_ext ? 'target="_blank" rel="noopener noreferrer"' : ''; ?> class="tpd-btn tpd-btn-sm tpd-btn-outline tpd-btn-block" style="text-decoration:none; text-align:center;">
										<i class="fa-solid fa-circle-check text-green"></i> <?php esc_html_e( 'Open / Download', 'tpd-tool' ); ?>
									</a>
								<?php else : ?>
									<button type="button"
										class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-btn-block tpd-open-checkout-btn"
										data-item-type="resource"
										data-item-id="<?php echo esc_attr( $res['id'] ); ?>"
										data-item-title="<?php echo esc_attr( $res['title'] ); ?>"
										data-item-price="<?php echo esc_attr( $r_price ); ?>">
										<i class="fa-solid fa-cart-shopping"></i> <?php printf( esc_html__( 'Unlock Resource — %s', 'tpd-tool' ), esc_html( $sym . number_format( $r_price, 2 ) ) ); ?>
									</button>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</div>
