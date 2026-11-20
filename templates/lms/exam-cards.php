<?php
/**
 * Exam cards in Resources & Tools → My Resources (native LMS only).
 * One card per enrolled course whose final exam is shown as a separate card
 * (the RTA exam). Locked until the course is finished or an admin unlocks it.
 * Included from templates/shared/resources-hub.php with $lms_enrollments set.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tpd_exam_cards = array_values( array_filter( array_map( function ( $e ) {
	return ! empty( $e['exam_card'] ) ? $e['exam_card'] : null;
}, (array) $lms_enrollments ) ) );

if ( ! $tpd_exam_cards ) {
	return;
}
?>
		<div class="tpd-card-box mb-4 tpd-lms-exam-cards">
			<h3 style="margin:0 0 4px; font-size:17px;"><i class="fa-solid fa-graduation-cap text-blue"></i> <?php esc_html_e( 'My Exams', 'tpd-tool' ); ?></h3>
			<p style="margin:0 0 16px; font-size:12.5px; color:#64748b;"><?php esc_html_e( 'Each exam unlocks when you finish its course.', 'tpd-tool' ); ?></p>
			<div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(290px, 1fr)); gap:18px;">
				<?php foreach ( $tpd_exam_cards as $x ) :
					$attempts_left = null === $x['max_attempts'] ? null : max( 0, $x['max_attempts'] - $x['attempts_used'] );
					?>
					<div class="tpd-lms-exam-card<?php echo $x['locked'] ? ' is-locked' : ''; ?><?php echo $x['passed'] ? ' is-passed' : ''; ?>" data-exam-card="<?php echo esc_attr( $x['course_slug'] ); ?>">
						<div class="tpd-lms-exam-card-top">
							<span class="tpd-lms-exam-icon" aria-hidden="true"><i class="fa-solid <?php echo $x['passed'] ? 'fa-award' : ( $x['locked'] ? 'fa-lock' : 'fa-lock-open' ); ?>"></i></span>
							<div>
								<h4><?php echo esc_html( $x['title'] ); ?></h4>
								<p><?php echo esc_html( $x['course_title'] ); ?></p>
							</div>
						</div>
						<ul class="tpd-lms-exam-facts">
							<li><?php echo esc_html( sprintf( /* translators: %d: questions */ _n( '%d question', '%d questions', $x['questions'], 'tpd-tool' ), $x['questions'] ) ); ?></li>
							<li><?php echo esc_html( sprintf( /* translators: %d: pass mark */ __( 'Pass mark %d%%', 'tpd-tool' ), $x['passing_score'] ) ); ?></li>
							<?php if ( $x['time_limit'] ) : ?><li><?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d minutes', 'tpd-tool' ), $x['time_limit'] ) ); ?></li><?php endif; ?>
							<?php if ( null !== $attempts_left ) : ?><li><?php echo esc_html( sprintf( /* translators: 1: left, 2: total */ __( '%1$d of %2$d attempts left', 'tpd-tool' ), $attempts_left, $x['max_attempts'] ) ); ?></li><?php endif; ?>
						</ul>

						<?php if ( $x['passed'] ) : ?>
							<p class="tpd-lms-exam-status is-ok"><i class="fa-solid fa-circle-check"></i> <?php echo esc_html( sprintf( /* translators: %d: score */ __( 'Passed with %d%%', 'tpd-tool' ), (int) $x['best_score'] ) ); ?></p>
						<?php elseif ( $x['locked'] ) : ?>
							<p class="tpd-lms-exam-status">
								<i class="fa-solid fa-lock"></i>
								<?php
								echo esc_html( $x['lessons_left'] > 0
									? sprintf( /* translators: %d: lessons left */ _n( 'Finish the course to unlock it: %d lesson left.', 'Finish the course to unlock it: %d lessons left.', $x['lessons_left'], 'tpd-tool' ), $x['lessons_left'] )
									: (string) $x['lock'] );
								?>
							</p>
							<form class="tpd-lms-unlock-form" data-exam-unlock="<?php echo esc_attr( $x['course_slug'] ); ?>">
								<label for="tpd-unlock-<?php echo esc_attr( $x['lesson_id'] ); ?>"><?php esc_html_e( 'Have an unlock key from TARC?', 'tpd-tool' ); ?></label>
								<div class="tpd-lms-unlock-row">
									<input type="text" id="tpd-unlock-<?php echo esc_attr( $x['lesson_id'] ); ?>" name="key" class="tpd-input" placeholder="ABCD-EFGH" autocomplete="off" required>
									<button type="submit" class="tpd-btn tpd-btn-sm tpd-btn-outline"><?php esc_html_e( 'Unlock', 'tpd-tool' ); ?></button>
								</div>
								<p class="tpd-lms-unlock-msg" role="alert" hidden></p>
							</form>
						<?php else : ?>
							<p class="tpd-lms-exam-status is-open"><i class="fa-solid fa-lock-open"></i> <?php echo 'admin' === $x['unlocked_how'] ? esc_html__( 'Unlocked by the course team.', 'tpd-tool' ) : esc_html__( 'Unlocked. You finished the course.', 'tpd-tool' ); ?></p>
						<?php endif; ?>

						<?php if ( ! $x['locked'] && ( ! $x['passed'] || null === $attempts_left || $attempts_left > 0 ) ) : ?>
							<button type="button" class="tpd-btn tpd-btn-sm tpd-btn-darkblue tpd-btn-block tpd-lms-launch" data-redirect="<?php echo esc_attr( $x['course_slug'] ); ?>" data-lesson="<?php echo esc_attr( $x['lesson_id'] ); ?>" style="background:#00798c; border-color:#00798c;">
								<i class="fa-solid fa-play"></i> <?php echo $x['passed'] ? esc_html__( 'View the exam', 'tpd-tool' ) : ( $x['attempts_used'] ? esc_html__( 'Try the exam again', 'tpd-tool' ) : esc_html__( 'Start the exam', 'tpd-tool' ) ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
