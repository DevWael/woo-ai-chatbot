<?php

declare(strict_types=1);

namespace WoocommerceAIChatbot;

use WoocommerceAIChatbot\Admin\PluginSettings;

defined( '\ABSPATH' ) || exit;

class Core {

	// singleton instance
	private static $instance = null;

	// get the singleton instance
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	// initialize the plugin
	public function init() {
		$this->pluginSettings = new PluginSettings();
		$this->ajaxHandler    = new Ajax_Chat_Handler();
		$this->chatFrontend   = new ChatFrontend();
	}

	// load hooks
	public function load_hooks() {
		if ( is_admin() ) {
			$this->pluginSettings->load_hooks();
		}
		$this->ajaxHandler->load_hooks();
		$this->chatFrontend->load_hooks();
	}
}