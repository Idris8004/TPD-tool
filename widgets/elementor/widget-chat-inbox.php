<?php
/**
 * Elementor Widget: Chat Inbox & Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Widget_Base;

class TPD_Elementor_Chat_Inbox extends Widget_Base {

	public function get_name() {
		return 'tpd_chat_inbox';
	}

	public function get_title() {
		return __( 'TPD Chat & Messages Inbox', 'tpd-tool' );
	}

	public function get_icon() {
		return 'eicon-comments';
	}

	public function get_categories() {
		return array( 'tpd-elements' );
	}

	protected function render() {
		echo do_shortcode( '[tpd_chat_inbox]' );
	}
}
