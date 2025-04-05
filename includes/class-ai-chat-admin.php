<?php

class WC_AI_Chat_Admin {
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'init_settings' ] );
	}

	public function add_admin_menu() {
		add_options_page(
			'WooCommerce AI Chatbot Settings',
			'WC AI Chatbot',
			'manage_options',
			'wc-ai-chatbot',
			[ $this, 'render_settings_page' ]
		);
	}

	public function init_settings() {
		register_setting( 'wc_ai_chat_group', 'wc_ai_chat_settings' );

		add_settings_section(
			'wc_ai_chat_main',
			'API Settings',
			null,
			'wc-ai-chatbot'
		);

		add_settings_field(
			'api_domain',
			'AI Service Domain (Optional)',
			[ $this, 'render_api_domain_field' ],
			'wc-ai-chatbot',
			'wc_ai_chat_main'
		);

		add_settings_field(
			'api_key',
			'OpenAI API Key',
			[ $this, 'render_api_key_field' ],
			'wc-ai-chatbot',
			'wc_ai_chat_main'
		);

		add_settings_field(
			'model',
			'Model',
			[ $this, 'render_model_field' ],
			'wc-ai-chatbot',
			'wc_ai_chat_main'
		);

		add_settings_field(
			'chat_title',
			'Chat Title',
			[ $this, 'render_chat_title_field' ],
			'wc-ai-chatbot',
			'wc_ai_chat_main'
		);
	}

	public function render_settings_page() {
		$view_path = WC_AI_CHATBOT_PLUGIN_DIR . 'includes/views/admin-settings.php';

		if ( file_exists( $view_path ) ) {
			require_once $view_path;
		} else {
			echo '<div class="error"><p>The settings view file is missing. Please reinstall the plugin.</p></div>';
		}
	}

	public function render_api_domain_field() {
		$options = get_option( 'wc_ai_chat_settings' );
		echo '<input type="url" name="wc_ai_chat_settings[api_domain]" value="' . esc_attr( $options['api_domain'] ?? '' )
		     . '" class="regular-text" placeholder="https://api.openai.com/v1">';
		echo '<p class="description">Leave empty to use default OpenAI endpoint</p>';
	}

	public function render_api_key_field() {
		$options = get_option( 'wc_ai_chat_settings' );
		echo '<input type="password" name="wc_ai_chat_settings[api_key]" value="' . esc_attr( $options['api_key'] ?? '' ) . '" class="regular-text">';
	}

	public function render_model_field() {
		$options = get_option( 'wc_ai_chat_settings' );
		$models  = [
			'google/gemini-2.0-flash-exp:free'    => 'Gemini 2.0 Flash Exp',
		];

		echo '<select name="wc_ai_chat_settings[model]">';
		foreach ( $models as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $options['model'] ?? '', $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function render_chat_title_field() {
		$options = get_option( 'wc_ai_chat_settings' );
		echo '<input type="text" name="wc_ai_chat_settings[chat_title]" value="' . esc_attr( $options['chat_title'] ?? 'How can I help you?' ) . '" class="regular-text">';
	}
}