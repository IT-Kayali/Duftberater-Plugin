<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SY_Duftberater_Admin {

	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	public function init() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_filter( 'option_page_capability_sy_duftberater_settings_group', array( $this, 'filter_settings_capability' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_sydb_save_question', array( $this, 'handle_save_question' ) );
		add_action( 'admin_post_sydb_delete_question', array( $this, 'handle_delete_question' ) );
		add_action( 'admin_post_sydb_import_examples', array( $this, 'handle_import_examples' ) );
		add_action( 'admin_post_sydb_export_parfums_xlsx', array( $this, 'handle_export_parfums_xlsx' ) );
		add_action( 'admin_post_sydb_export_parfums_matrix_xlsx', array( $this, 'handle_export_parfums_matrix_xlsx' ) );
		add_action( 'admin_post_sydb_import_parfums_file', array( $this, 'handle_import_parfums_file' ) );
		add_action( 'admin_post_sydb_quick_update_parfum', array( $this, 'handle_quick_update_parfum' ) );
		add_action( 'admin_post_sydb_duplicate_parfum', array( $this, 'handle_duplicate_parfum' ) );
		add_action( 'admin_post_sydb_export_setup', array( $this, 'handle_export_setup' ) );
		add_action( 'admin_post_sydb_import_setup', array( $this, 'handle_import_setup' ) );
		add_action( 'admin_post_sydb_export_leads_csv', array( $this, 'handle_export_leads_csv' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_parfum_meta_boxes' ) );
		add_action( 'save_post_' . SY_Duftberater::POST_TYPE_PARFUM, array( $this, 'save_parfum_meta' ) );
		add_filter( 'manage_' . SY_Duftberater::POST_TYPE_PARFUM . '_posts_columns', array( $this, 'filter_parfum_columns' ) );
		add_action( 'manage_' . SY_Duftberater::POST_TYPE_PARFUM . '_posts_custom_column', array( $this, 'render_parfum_columns' ), 10, 2 );
	}


	public function filter_settings_capability( $capability ) {
		return SY_Duftberater::CAP_MANAGE;
	}

	public function register_admin_menu() {
		add_menu_page(
			'SY Duftberater',
			'SY Duftberater',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater',
			array( $this, 'render_dashboard_page' ),
			'dashicons-admin-customizer',
			58
		);

		add_submenu_page(
			'sy-duftberater',
			'Dashboard',
			'Dashboard',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater',
			array( $this, 'render_dashboard_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Fragen',
			'Fragen',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-questions',
			array( $this, 'render_questions_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Parfums',
			'Parfums klassisch',
			'edit_posts',
			'edit.php?post_type=' . SY_Duftberater::POST_TYPE_PARFUM
		);

		add_submenu_page(
			'sy-duftberater',
			'Parfum Manager',
			'Parfum Manager',
			'edit_posts',
			'sy-duftberater-parfum-manager',
			array( $this, 'render_parfum_manager_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Pflege-Status',
			'Pflege-Status',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-health',
			array( $this, 'render_health_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Matching Simulator',
			'Matching Simulator',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-matching-simulator',
			array( $this, 'render_matching_simulator_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Einträge / PDFs',
			'Einträge / PDFs',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-leads',
			array( $this, 'render_leads_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Import / Export',
			'Import / Export',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-import-export',
			array( $this, 'render_import_export_page' )
		);

		add_submenu_page(
			'sy-duftberater',
			'Einstellungen',
			'Einstellungen',
			SY_Duftberater::CAP_MANAGE,
			'sy-duftberater-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting(
			'sy_duftberater_settings_group',
			SY_Duftberater::OPTION_SETTINGS,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'sy_duftberater_main_section',
			'Grundkonfiguration',
			function() {
				echo '<p>Steuere die Ausgabe des Beraters, den Startbutton, die Akzentfarbe und die Beispiel-Daten.</p>';
			},
			'sy-duftberater-settings'
		);

		add_settings_field( 'result_count', 'Anzahl Ergebnisse', array( $this, 'render_result_count_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'start_button_text', 'Button-Text Start', array( $this, 'render_start_button_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'accent_color', 'Primärfarbe / Akzentfarbe', array( $this, 'render_accent_color_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'frontend_logo_link', 'Frontend-Logo-Link', array( $this, 'render_frontend_logo_link_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'frontend_theme', 'Frontend-Farbmodus', array( $this, 'render_frontend_theme_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'default_language', 'Standardsprache der Startseite', array( $this, 'render_default_language_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'language_images', 'Sprachbilder / Flaggen', array( $this, 'render_language_images_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'audio_disabled', 'Ton global deaktivieren', array( $this, 'render_audio_disabled_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'click_sound_url', 'Klickton MP3', array( $this, 'render_click_sound_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'welcome_audio_i18n', 'Begrüßung MP3 je Sprache', array( $this, 'render_welcome_audio_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'result_audio_i18n', 'Ergebnisseite MP3 je Sprache', array( $this, 'render_result_audio_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'completion_audio_i18n', 'Abschluss-Popup MP3 je Sprache', array( $this, 'render_completion_audio_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'auto_download_enabled', 'Automatischer PDF-Download am Ende', array( $this, 'render_auto_download_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'layout_options', 'Layout / Vollbreite', array( $this, 'render_layout_options_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'ui_i18n', 'Mehrsprachige Frontend-Texte', array( $this, 'render_ui_i18n_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
		add_settings_field( 'enable_example_data', 'Beispiel-Daten', array( $this, 'render_example_data_field' ), 'sy-duftberater-settings', 'sy_duftberater_main_section' );
	}

	public function sanitize_settings( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = $this->plugin->get_default_settings();
		$current  = $this->get_settings();

		$default_ui   = $this->plugin->get_default_i18n_texts();
		$default_html = $this->plugin->get_default_html_i18n_texts();
		$current_ui   = array_replace_recursive( $default_ui, isset( $current['ui_i18n'] ) && is_array( $current['ui_i18n'] ) ? $current['ui_i18n'] : array() );
		$current_html = array_replace_recursive( $default_html, isset( $current['html_i18n'] ) && is_array( $current['html_i18n'] ) ? $current['html_i18n'] : array() );
		$current_pdf_templates = isset( $current['pdf_templates'] ) && is_array( $current['pdf_templates'] ) ? $current['pdf_templates'] : ( $defaults['pdf_templates'] ?? array() );

		$output = array(
			'result_count'        => 3,
			'start_button_text'   => isset( $input['start_button_text'] ) ? sanitize_text_field( $input['start_button_text'] ) : ( $current['start_button_text'] ?? $defaults['start_button_text'] ),
			'accent_color'        => isset( $input['accent_color'] ) ? sanitize_hex_color( $input['accent_color'] ) : ( $current['accent_color'] ?? $defaults['accent_color'] ),
			'frontend_theme'      => ( isset( $input['frontend_theme'] ) && in_array( $input['frontend_theme'], array( 'dark', 'light' ), true ) ) ? $input['frontend_theme'] : ( $current['frontend_theme'] ?? $defaults['frontend_theme'] ),
			'default_language'    => ( isset( $input['default_language'] ) && array_key_exists( sanitize_key( $input['default_language'] ), $this->plugin->get_supported_languages() ) ) ? sanitize_key( $input['default_language'] ) : ( $current['default_language'] ?? ( $defaults['default_language'] ?? 'de' ) ),
			'enable_example_data' => isset( $input['enable_example_data'] ) ? 1 : 0,
			'language_images'     => $this->sanitize_language_images( $input['language_images'] ?? ( $current['language_images'] ?? array() ), $defaults['language_images'] ?? $this->plugin->get_default_language_images() ),
			'pdf_logo_url'        => isset( $input['pdf_logo_url'] ) ? esc_url_raw( $input['pdf_logo_url'] ) : ( $current['pdf_logo_url'] ?? ( $defaults['pdf_logo_url'] ?? '' ) ),
			'frontend_logo_link' => isset( $input['frontend_logo_link'] ) ? esc_url_raw( $input['frontend_logo_link'] ) : ( $current['frontend_logo_link'] ?? ( $defaults['frontend_logo_link'] ?? '' ) ),
			'brand_name'          => isset( $input['brand_name'] ) ? sanitize_text_field( $input['brand_name'] ) : ( $current['brand_name'] ?? ( $defaults['brand_name'] ?? '' ) ),
			'brand_address'       => isset( $input['brand_address'] ) ? sanitize_textarea_field( $input['brand_address'] ) : ( $current['brand_address'] ?? ( $defaults['brand_address'] ?? '' ) ),
			'brand_phone'         => isset( $input['brand_phone'] ) ? sanitize_text_field( $input['brand_phone'] ) : ( $current['brand_phone'] ?? ( $defaults['brand_phone'] ?? '' ) ),
			'brand_email'         => isset( $input['brand_email'] ) ? sanitize_email( $input['brand_email'] ) : ( $current['brand_email'] ?? ( $defaults['brand_email'] ?? '' ) ),
			'brand_website'       => isset( $input['brand_website'] ) ? esc_url_raw( $input['brand_website'] ) : ( $current['brand_website'] ?? ( $defaults['brand_website'] ?? '' ) ),
			'coupon_percent'      => isset( $input['coupon_percent'] ) ? max( 0, min( 100, absint( $input['coupon_percent'] ) ) ) : ( $current['coupon_percent'] ?? ( $defaults['coupon_percent'] ?? 15 ) ),
			'audio_disabled'     => isset( $input['audio_disabled'] ) ? 1 : 0,
			'click_sound_enabled' => isset( $input['click_sound_enabled'] ) ? 1 : 0,
			'click_sound_url'     => isset( $input['click_sound_url'] ) ? esc_url_raw( $input['click_sound_url'] ) : ( $current['click_sound_url'] ?? ( $defaults['click_sound_url'] ?? '' ) ),
			'welcome_audio_i18n'  => $this->sanitize_audio_i18n( $input['welcome_audio_i18n'] ?? ( $current['welcome_audio_i18n'] ?? array() ), $defaults['welcome_audio_i18n'] ?? array() ),
			'result_audio_i18n'   => $this->sanitize_audio_i18n( $input['result_audio_i18n'] ?? ( $current['result_audio_i18n'] ?? array() ), $defaults['result_audio_i18n'] ?? array() ),
			'completion_audio_i18n' => $this->sanitize_audio_i18n( $input['completion_audio_i18n'] ?? ( $current['completion_audio_i18n'] ?? array() ), $defaults['completion_audio_i18n'] ?? array() ),
			'auto_download_enabled' => isset( $input['auto_download_enabled'] ) ? 1 : 0,
			'frontend_fullwidth' => isset( $input['frontend_fullwidth'] ) ? 1 : 0,
			'hide_plugin_header' => isset( $input['hide_plugin_header'] ) ? 1 : 0,
			'hide_plugin_footer' => isset( $input['hide_plugin_footer'] ) ? 1 : 0,
			'ui_i18n'             => $this->sanitize_ui_i18n( $input['ui_i18n'] ?? array(), $current_ui ),
			'html_i18n'           => $this->sanitize_html_i18n( $input['html_i18n'] ?? array(), $current_html ),
			'pdf_templates'       => $this->sanitize_pdf_templates( $input['pdf_templates'] ?? array(), $current_pdf_templates ),
		);

		if ( empty( $output['accent_color'] ) ) {
			$output['accent_color'] = $defaults['accent_color'];
		}

		return $output;
	}

	protected function get_settings() {
		return $this->plugin->get_settings();
	}

	public function render_result_count_field() {
		$settings = $this->get_settings();
		?>
		<input type="number" min="3" max="3" step="1" readonly name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[result_count]" value="3" class="small-text" />
		<p class="description">Diese Version zeigt fest die Top 3 Ergebnisse an.</p>
		<?php
	}

	public function render_start_button_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[start_button_text]" value="<?php echo esc_attr( $settings['start_button_text'] ); ?>" class="regular-text" />
		<?php
	}

	public function render_accent_color_field() {
		$settings = $this->get_settings();
		?>
		<input type="text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[accent_color]" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" class="regular-text" />
		<p class="description">Beispiel: #d4af37</p>
		<?php
	}



	public function render_frontend_logo_link_field() {
		$settings = $this->get_settings();
		$url = isset( $settings['frontend_logo_link'] ) ? esc_url( $settings['frontend_logo_link'] ) : '';
		?>
		<div style="display:grid;gap:8px;max-width:820px;">
			<input type="url" id="sydb_frontend_logo_link" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[frontend_logo_link]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://alowidat.de/" />
			<p class="description">Wenn hier ein Link eingetragen ist, wird das Logo oben im Duftberater klickbar. Leer lassen, wenn das Logo nicht verlinkt werden soll.</p>
		</div>
		<?php
	}


	public function render_frontend_theme_field() {
		$settings = $this->get_settings();
		$current  = isset( $settings['frontend_theme'] ) ? $settings['frontend_theme'] : 'dark';
		?>
		<select name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[frontend_theme]">
			<option value="dark" <?php selected( $current, 'dark' ); ?>>Dunkelmodus</option>
			<option value="light" <?php selected( $current, 'light' ); ?>>Hellmodus</option>
		</select>
		<p class="description">Nur im Backend umstellbar. Das Frontend übernimmt automatisch die hier gewählte Ansicht.</p>
		<?php
	}


	public function render_default_language_field() {
		$settings = $this->get_settings();
		$current  = isset( $settings['default_language'] ) ? $this->plugin->normalize_lang( $settings['default_language'] ) : 'de';
		?>
		<select name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[default_language]">
			<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
				<option value="<?php echo esc_attr( $lang ); ?>" <?php selected( $current, $lang ); ?>><?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description">Steuert die Sprache der ersten Sprachauswahl-Seite und der Begrüßung, bevor der Besucher selbst eine Sprache anklickt.</p>
		<?php
	}



	public function render_audio_disabled_field() {
		$settings = $this->get_settings();
		$disabled = ! empty( $settings['audio_disabled'] );
		?>
		<div style="display:grid;gap:8px;max-width:820px;padding:12px 14px;border:1px solid #d8c4ab;border-radius:10px;background:#fffaf2;">
			<label style="font-weight:700;font-size:15px;">
				<input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[audio_disabled]" value="1" <?php checked( $disabled ); ?>>
				Alle Töne im Duftberater deaktivieren
			</label>
			<p class="description" style="margin:0;">Wenn aktiv, werden Klickton, Begrüßung, Fragen-Audios, Unterfragen-Audios, Ergebnisseite-Audio und Abschluss-Popup-Audio komplett deaktiviert. Die hochgeladenen MP3-Dateien bleiben gespeichert.</p>
		</div>
		<?php
	}


	public function render_click_sound_field() {
		$settings = $this->get_settings();
		$url = isset( $settings['click_sound_url'] ) ? esc_url( $settings['click_sound_url'] ) : '';
		$enabled = ! empty( $settings['click_sound_enabled'] );
		?>
		<div style="display:grid;gap:8px;max-width:820px;">
			<label style="font-weight:600;"><input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[click_sound_enabled]" value="1" <?php checked( $enabled ); ?>> Klickton aktivieren</label>
			<input type="url" id="sydb_click_sound_url" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[click_sound_url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://.../click.mp3" />
			<p class="description">Der Klickton ist jetzt separat deaktivierbar. Du kannst eine MP3 aus der Mediathek wählen oder eine URL eintragen.</p>
			<p><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#sydb_click_sound_url">MP3 aus Mediathek wählen</button></p>
			<?php if ( $url ) : ?><audio controls preload="none" src="<?php echo esc_url( $url ); ?>" style="width:100%;max-width:520px;"></audio><?php endif; ?>
		</div>
		<?php
	}


	protected function sanitize_audio_i18n( $input, $defaults = array() ) {
		$output = array();
		$input  = is_array( $input ) ? $input : array();
		foreach ( array_keys( $this->plugin->get_supported_languages() ) as $lang ) {
			$output[ $lang ] = isset( $input[ $lang ] ) ? esc_url_raw( $input[ $lang ] ) : ( $defaults[ $lang ] ?? '' );
		}
		return $output;
	}

	public function render_welcome_audio_field() {
		$settings = $this->get_settings();
		$values   = isset( $settings['welcome_audio_i18n'] ) && is_array( $settings['welcome_audio_i18n'] ) ? $settings['welcome_audio_i18n'] : array();
		?>
		<div style="display:grid;gap:12px;max-width:900px;">
			<p class="description">Diese MP3 wird automatisch abgespielt, wenn der Nutzer nach der Sprachauswahl auf die Begrüßungsseite kommt. Wegen Browser-Regeln startet Audio erst nach dem ersten Klick des Nutzers.</p>
			<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
				<?php $field_id = 'sydb-welcome-audio-' . $lang; $audio_val = esc_url( $values[ $lang ] ?? '' ); ?>
				<label style="display:block;font-weight:600;">
					<?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?>
					<input id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[welcome_audio_i18n][<?php echo esc_attr( $lang ); ?>]" type="url" class="large-text" value="<?php echo esc_attr( $audio_val ); ?>" placeholder="https://.../begruessung-<?php echo esc_attr( $lang ); ?>.mp3">
				</label>
				<p><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#<?php echo esc_attr( $field_id ); ?>">MP3 aus Mediathek wählen</button></p>
				<?php if ( $audio_val ) : ?><audio controls preload="none" src="<?php echo esc_url( $audio_val ); ?>" style="width:100%;max-width:520px;"></audio><?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}


	public function render_result_audio_field() {
		$settings = $this->get_settings();
		$values   = isset( $settings['result_audio_i18n'] ) && is_array( $settings['result_audio_i18n'] ) ? $settings['result_audio_i18n'] : array();
		?>
		<div style="display:grid;gap:12px;max-width:900px;">
			<p class="description">Diese MP3 wird automatisch abgespielt, sobald die Ergebnisseite angezeigt wird – also nach der letzten Frage und vor dem Abschluss-Popup.</p>
			<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
				<?php $field_id = 'sydb-result-audio-' . $lang; $audio_val = esc_url( $values[ $lang ] ?? '' ); ?>
				<label style="display:block;font-weight:600;">
					<?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?>
					<input id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[result_audio_i18n][<?php echo esc_attr( $lang ); ?>]" type="url" class="large-text" value="<?php echo esc_attr( $audio_val ); ?>" placeholder="https://.../ergebnisseite-<?php echo esc_attr( $lang ); ?>.mp3">
				</label>
				<p><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#<?php echo esc_attr( $field_id ); ?>">MP3 aus Mediathek wählen</button></p>
				<?php if ( $audio_val ) : ?><audio controls preload="none" src="<?php echo esc_url( $audio_val ); ?>" style="width:100%;max-width:520px;"></audio><?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}



	public function render_auto_download_field() {
		$settings = $this->get_settings();
		$enabled  = isset( $settings['auto_download_enabled'] ) ? ! empty( $settings['auto_download_enabled'] ) : true;
		?>
		<div style="display:grid;gap:8px;max-width:820px;padding:12px 14px;border:1px solid #d8c4ab;border-radius:10px;background:#fffaf2;">
			<label style="font-weight:700;font-size:15px;">
				<input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[auto_download_enabled]" value="1" <?php checked( $enabled ); ?>>
				Automatischer PDF-Download am Ende aktivieren
			</label>
			<p class="description" style="margin:0;">Wenn aktiv, startet der PDF-Download automatisch nach „PDF per E-Mail senden“. Wenn deaktiviert, wird nur die Erfolgsmeldung mit dem manuellen Link „PDF herunterladen“ angezeigt.</p>
		</div>
		<?php
	}


	public function render_completion_audio_field() {
		$settings = $this->get_settings();
		$values   = isset( $settings['completion_audio_i18n'] ) && is_array( $settings['completion_audio_i18n'] ) ? $settings['completion_audio_i18n'] : array();
		?>
		<div style="display:grid;gap:12px;max-width:900px;">
			<p class="description">Diese MP3 wird im Abschluss-Popup nach erfolgreichem Klick auf „PDF erhalten“ abgespielt. Pro Sprache kann eine eigene Audiodatei gewählt werden.</p>
			<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
				<?php $field_id = 'sydb-completion-audio-' . $lang; $audio_val = esc_url( $values[ $lang ] ?? '' ); ?>
				<label style="display:block;font-weight:600;">
					<?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?>
					<input id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[completion_audio_i18n][<?php echo esc_attr( $lang ); ?>]" type="url" class="large-text" value="<?php echo esc_attr( $audio_val ); ?>" placeholder="https://.../abschluss-<?php echo esc_attr( $lang ); ?>.mp3">
				</label>
				<p><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#<?php echo esc_attr( $field_id ); ?>">MP3 aus Mediathek wählen</button></p>
				<?php if ( $audio_val ) : ?><audio controls preload="none" src="<?php echo esc_url( $audio_val ); ?>" style="width:100%;max-width:520px;"></audio><?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function render_layout_options_field() {
		$settings = $this->get_settings();
		?>
		<div style="display:grid;gap:10px;max-width:820px;">
			<label style="font-weight:600;"><input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[frontend_fullwidth]" value="1" <?php checked( ! empty( $settings['frontend_fullwidth'] ) ); ?>> Fullwidth-Modus aktivieren</label>
			<label style="font-weight:600;"><input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[hide_plugin_header]" value="1" <?php checked( ! empty( $settings['hide_plugin_header'] ) ); ?>> Plugin-Header im Frontend ausblenden</label>
			<label style="font-weight:600;"><input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[hide_plugin_footer]" value="1" <?php checked( ! empty( $settings['hide_plugin_footer'] ) ); ?>> Plugin-Footer / unteren Markenbereich ausblenden</label>
			<p class="description">Für Seiten im Hochformat empfohlen: Fullwidth sorgt dafür, dass die Ergebnis-Karten immer genug Breite haben und nebeneinander bleiben.</p>
		</div>
		<?php
	}

	public function render_pdf_logo_field() {
		$settings = $this->get_settings();
		$url = isset( $settings['pdf_logo_url'] ) ? esc_url( $settings['pdf_logo_url'] ) : '';
		?>
		<div style="display:grid;gap:8px;max-width:820px;">
			<input type="url" id="sydb_pdf_logo_url" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[pdf_logo_url]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://..." />
			<p class="description">Dieses Logo wird am Anfang der PDF-Empfehlung genutzt. Du kannst eine URL eintragen oder ein Bild aus der Mediathek auswählen.</p>
			<p><button type="button" class="button sydb-media-select" data-target="#sydb_pdf_logo_url">Aus Mediathek wählen</button></p>
			<?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" style="max-width:180px;height:auto;border:1px solid #ddd;border-radius:8px;background:#fff;padding:8px;" /><?php endif; ?>
		</div>
		<?php
	}

	protected function sanitize_language_images( $input, $defaults ) {
		$output = array();
		$input  = is_array( $input ) ? $input : array();
		foreach ( array_keys( $this->plugin->get_supported_languages() ) as $lang ) {
			$output[ $lang ] = isset( $input[ $lang ] ) ? esc_url_raw( $input[ $lang ] ) : ( $defaults[ $lang ] ?? '' );
		}
		return $output;
	}

	public function render_language_images_field() {
		$settings = $this->get_settings();
		$defaults = $this->plugin->get_default_language_images();
		$images   = isset( $settings['language_images'] ) && is_array( $settings['language_images'] ) ? $settings['language_images'] : array();
		?>
		<div style="display:grid;gap:10px;max-width:820px;">
			<p class="description">Diese Bilder erscheinen auf der ersten Sprachauswahl. Du kannst die URLs jederzeit ersetzen.</p>
			<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
				<label style="display:grid;grid-template-columns:120px 1fr;gap:10px;align-items:center;">
					<strong><?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?></strong>
					<input type="url" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[language_images][<?php echo esc_attr( $lang ); ?>]" value="<?php echo esc_attr( $images[ $lang ] ?? ( $defaults[ $lang ] ?? '' ) ); ?>" placeholder="https://..." />
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	protected function sanitize_ui_i18n( $input, $defaults ) {
		$output    = array();
		$input     = is_array( $input ) ? $input : array();
		$defaults  = array_replace_recursive( $this->plugin->get_default_i18n_texts(), is_array( $defaults ) ? $defaults : array() );
		$html_keys = array( 'mail_body_text' );

		foreach ( (array) $defaults as $key => $langs ) {
			$output[ $key ] = array();
			foreach ( array_keys( $this->plugin->get_supported_languages() ) as $lang ) {
				if ( isset( $input[ $key ][ $lang ] ) ) {
					$value = (string) $input[ $key ][ $lang ];
					if ( in_array( $key, $html_keys, true ) ) {
						$output[ $key ][ $lang ] = wp_kses_post( $value );
					} else {
						$output[ $key ][ $lang ] = sanitize_textarea_field( $value );
					}
				} else {
					$output[ $key ][ $lang ] = $langs[ $lang ] ?? '';
				}
			}
		}
		return $output;
	}

	protected function sanitize_html_i18n( $input, $defaults ) {
		$output   = array();
		$input    = is_array( $input ) ? $input : array();
		$defaults = array_replace_recursive( $this->plugin->get_default_html_i18n_texts(), is_array( $defaults ) ? $defaults : array() );

		foreach ( (array) $defaults as $key => $langs ) {
			$output[ $key ] = array();
			foreach ( array_keys( $this->plugin->get_supported_languages() ) as $lang ) {
				$value = isset( $input[ $key ][ $lang ] ) ? (string) $input[ $key ][ $lang ] : ( $langs[ $lang ] ?? '' );
				$output[ $key ][ $lang ] = wp_kses_post( $value );
			}
		}

		return $output;
	}


	protected function sanitize_pdf_templates( $input, $defaults = array() ) {
		$output   = array();
		$input    = is_array( $input ) ? $input : array();
		$fallback = $this->plugin->get_default_pdf_templates();

		$image_fields = array(
			'frame_image',
			'header_image',
			'coupon_image',
			'instruction_image',
			'welcome_band_image',
			'footer_image',
			'branch_1_image',
			'branch_2_image',
			'branch_3_image',
			'branch_4_image',
			'branch_5_image',
		);
		$text_fields = array( 'intro_text', 'result_title', 'website', 'email', 'phone' );

		foreach ( array_keys( $this->plugin->get_supported_languages() ) as $lang ) {
			$base = isset( $fallback[ $lang ] ) && is_array( $fallback[ $lang ] ) ? $fallback[ $lang ] : ( $fallback['de'] ?? array() );
			$raw  = isset( $input[ $lang ] ) && is_array( $input[ $lang ] ) ? $input[ $lang ] : array();
			$output[ $lang ] = $base;

			foreach ( $image_fields as $key ) {
				$id_key  = $key . '_id';
				$url_key = $key . '_url';
				$url_value = isset( $raw[ $url_key ] ) ? esc_url_raw( $raw[ $url_key ] ) : '';
				// Wenn die URL im Backend leer ist, muss auch die Attachment-ID geleert werden.
				// Sonst lädt die PDF-Ausgabe über die versteckte ID weiterhin das alte Bild.
				$output[ $lang ][ $url_key ] = $url_value;
				$output[ $lang ][ $id_key ]  = '' !== $url_value && isset( $raw[ $id_key ] ) ? absint( $raw[ $id_key ] ) : 0;
			}

			foreach ( $text_fields as $key ) {
				$value = isset( $raw[ $key ] ) ? (string) $raw[ $key ] : ( $base[ $key ] ?? '' );
				$output[ $lang ][ $key ] = sanitize_textarea_field( $value );
			}
		}

		return $output;
	}


	public function render_ui_i18n_field() {
		$settings = $this->get_settings();
		$defaults = $this->plugin->get_default_i18n_texts();
		$ui       = isset( $settings['ui_i18n'] ) && is_array( $settings['ui_i18n'] ) ? $settings['ui_i18n'] : array();
		$labels   = array(
			'header_title'    => 'Header – Titel',
			'header_subtitle' => 'Header – Untertitel',
			'language_title'  => 'Sprachauswahl – Titel',
			'language_text'   => 'Sprachauswahl – Text',
			'welcome_title'   => 'Begrüßungsseite – Titel',
			'welcome_text'    => 'Begrüßungsseite – Text',
			'welcome_badge_1' => 'Begrüßung Badge 1',
			'welcome_badge_2' => 'Begrüßung Badge 2',
			'welcome_badge_3' => 'Begrüßung Badge 3',
			'start_button'    => 'Button Start',
			'back_button'     => 'Button Zurück',
			'next_button'     => 'Button Weiter',
			'show_results_button' => 'Button letzte Frage – Ergebnisse anzeigen',
			'restart_button'  => 'Button Neu starten',
			'completion_popup_text' => 'Abschluss-Popup – Text',
			'completion_popup_restart' => 'Abschluss-Popup – Button',
			'result_title'    => 'Ergebnis – Titel',
			'result_text'     => 'Ergebnis – Text',
			'product_button'  => 'Button Produkt ansehen',
			'empty_text'      => 'Keine Ergebnisse',
			'lead_form_title' => 'Lead-Formular – Titel',
			'lead_form_text'  => 'Lead-Formular – Text',
			'lead_name_label' => 'Lead-Formular – Name',
			'lead_email_label'=> 'Lead-Formular – E-Mail',
			'lead_submit'     => 'Lead-Formular – Button',
			'lead_success'    => 'Lead-Formular – Erfolg',
			'lead_error'      => 'Lead-Formular – Fehler',
			'lead_pdf_link'   => 'Lead-Formular – PDF-Link',
			'lead_sending_text' => 'Lead-Formular – Text während Versand',
			'mail_subject'    => 'Mail – Betreff',
			'mail_body_text'  => 'Mail – Inhalt (Normaltext)',
		);
		?>
		<div class="sydb-i18n-settings" style="display:grid;gap:14px;max-width:820px;">
			<p class="description">Hier pflegst du die sichtbaren Frontend- und E-Mail-Texte in Deutsch, Englisch und Arabisch. Die PDF-Endvorlage hat jetzt eine eigene saubere Maske im Tab PDF & E-Mail.</p>
			<?php foreach ( $labels as $key => $label ) : ?>
				<?php $rows = in_array( $key, array( 'mail_body_text' ), true ) ? 12 : 2; ?>
				<div style="border:1px solid #ddd;border-radius:10px;padding:12px;background:#fafafa;">
					<strong><?php echo esc_html( $label ); ?></strong>
					<?php if ( in_array( $key, array( 'mail_body_text' ), true ) ) : ?>
						<p class="description" style="margin:6px 0 0;">Du kannst hier normalen Text oder einfaches HTML wie &lt;br&gt;, &lt;p&gt;, &lt;strong&gt; und &lt;em&gt; verwenden.</p>
					<?php endif; ?>
					<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:8px;">
						<?php foreach ( $this->plugin->get_supported_languages() as $lang => $language ) : ?>
							<label style="display:block;font-weight:600;">
								<?php echo esc_html( strtoupper( $lang ) ); ?>
								<textarea name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[ui_i18n][<?php echo esc_attr( $key ); ?>][<?php echo esc_attr( $lang ); ?>]" rows="<?php echo esc_attr( $rows ); ?>" class="large-text" <?php echo 'ar' === $lang ? 'dir="rtl"' : ''; ?>><?php echo esc_textarea( $ui[ $key ][ $lang ] ?? ( $defaults[ $key ][ $lang ] ?? '' ) ); ?></textarea>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public function render_html_i18n_field() {
		$settings = $this->get_settings();
		$defaults = $this->plugin->get_default_html_i18n_texts();
		$html     = isset( $settings['html_i18n'] ) && is_array( $settings['html_i18n'] ) ? $settings['html_i18n'] : array();
		$languages = $this->plugin->get_supported_languages();
		?>
		<div class="sydb-pdf-html-manager" style="max-width:1180px;display:grid;gap:16px;">
			<p class="description" style="font-size:14px;">
				Hier bearbeitest du nur den <strong>PDF-Body</strong> und den <strong>PDF-Footer</strong> für Deutsch, Englisch und Arabisch. Die Vorschau aktualisiert sich direkt beim Schreiben. Platzhalter: <code>{name}</code>, <code>{email}</code>.
			</p>

			<style>
				.sydb-pdf-html-manager .sydb-lang-tabs{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px;}
				.sydb-pdf-html-manager .sydb-lang-tab{border:1px solid #d9c58d;background:#fffaf0;border-radius:999px;padding:8px 14px;font-weight:700;cursor:pointer;color:#3b2a16;}
				.sydb-pdf-html-manager .sydb-lang-tab.is-active{background:#2b3d24;color:#f6d98f;border-color:#2b3d24;}
				.sydb-pdf-html-manager .sydb-lang-panel{display:none;border:1px solid #dccda6;border-radius:14px;background:#fffdf8;padding:16px;box-shadow:0 6px 20px rgba(70,50,15,.06);}
				.sydb-pdf-html-manager .sydb-lang-panel.is-active{display:block;}
				.sydb-pdf-html-manager .sydb-editor-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(360px,.9fr);gap:16px;align-items:start;}
				.sydb-pdf-html-manager textarea{font-family:Consolas,Monaco,monospace;min-height:150px;border-radius:10px;border-color:#d6c28e;background:#fff;}
				.sydb-pdf-html-manager .sydb-field-card{display:grid;gap:8px;margin-bottom:14px;}
				.sydb-pdf-html-manager .sydb-field-card strong{font-size:14px;color:#2b2416;}
				.sydb-pdf-html-manager .sydb-preview-card{border:1px solid #e5d6ad;border-radius:18px;background:linear-gradient(135deg,#fffdf8,#f7efe0);padding:18px;min-height:350px;position:sticky;top:42px;overflow:hidden;}
				.sydb-pdf-html-manager .sydb-preview-logo{text-align:center;color:#c7a14a;font-size:38px;line-height:1;font-family:Georgia,serif;margin:4px 0 22px;}
				.sydb-pdf-html-manager .sydb-preview-body{border:1px solid #e6d8b5;background:rgba(255,255,255,.72);border-radius:14px;padding:18px;margin-bottom:18px;color:#342414;font-size:16px;line-height:1.75;}
				.sydb-pdf-html-manager .sydb-preview-footer{background:#21180f;color:#e8d393;border-radius:14px;padding:18px;font-size:16px;line-height:1.65;text-align:center;}
				.sydb-pdf-html-manager .sydb-preview-body[dir="rtl"], .sydb-pdf-html-manager .sydb-preview-footer[dir="rtl"]{text-align:right;font-family:Tahoma,Arial,sans-serif;}
				.sydb-pdf-html-manager .sydb-help{background:#f7f1df;border-right:4px solid #c7a14a;padding:10px 12px;border-radius:10px;color:#5d4b2e;margin-top:8px;}
				@media (max-width: 980px){.sydb-pdf-html-manager .sydb-editor-grid{grid-template-columns:1fr}.sydb-pdf-html-manager .sydb-preview-card{position:static}}
			</style>

			<div class="sydb-lang-tabs" role="tablist">
				<?php $first = true; foreach ( $languages as $lang => $language ) : ?>
					<button type="button" class="sydb-lang-tab <?php echo $first ? 'is-active' : ''; ?>" data-sydb-tab="<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?></button>
				<?php $first = false; endforeach; ?>
			</div>

			<?php $first = true; foreach ( $languages as $lang => $language ) : ?>
				<?php
					$is_rtl = ( 'ar' === $lang );
					$body_value   = $html['pdf_body_html'][ $lang ] ?? ( $defaults['pdf_body_html'][ $lang ] ?? '' );
					$footer_value = $html['pdf_footer_html'][ $lang ] ?? ( $defaults['pdf_footer_html'][ $lang ] ?? '' );
				?>
				<div class="sydb-lang-panel <?php echo $first ? 'is-active' : ''; ?>" data-sydb-panel="<?php echo esc_attr( $lang ); ?>">
					<div class="sydb-editor-grid">
						<div>
							<div class="sydb-field-card">
								<strong>PDF-Body HTML – <?php echo esc_html( $language['native'] ); ?></strong>
								<textarea class="large-text code sydb-html-source" data-preview-target="sydb-preview-body-<?php echo esc_attr( $lang ); ?>" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[html_i18n][pdf_body_html][<?php echo esc_attr( $lang ); ?>]" rows="8" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>><?php echo esc_textarea( $body_value ); ?></textarea>
							</div>
							<div class="sydb-field-card">
								<strong>PDF-Footer HTML – <?php echo esc_html( $language['native'] ); ?></strong>
								<textarea class="large-text code sydb-html-source" data-preview-target="sydb-preview-footer-<?php echo esc_attr( $lang ); ?>" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[html_i18n][pdf_footer_html][<?php echo esc_attr( $lang ); ?>]" rows="5" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>><?php echo esc_textarea( $footer_value ); ?></textarea>
							</div>
							<div class="sydb-help">
								Erlaubt sind einfache HTML-Tags wie <code>&lt;p&gt;</code>, <code>&lt;br&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;ul&gt;</code>, <code>&lt;li&gt;</code>. Für die PDF-Ausgabe wird der Inhalt sauber in PDF-Text umgewandelt.
							</div>
						</div>
						<div class="sydb-preview-card">
							<div class="sydb-preview-logo">Alowidat</div>
							<div id="sydb-preview-body-<?php echo esc_attr( $lang ); ?>" class="sydb-preview-body" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>></div>
							<div id="sydb-preview-footer-<?php echo esc_attr( $lang ); ?>" class="sydb-preview-footer" <?php echo $is_rtl ? 'dir="rtl"' : ''; ?>></div>
						</div>
					</div>
				</div>
			<?php $first = false; endforeach; ?>

			<script>
			(function(){
				function replacePlaceholders(value){
					return String(value || '')
						.replaceAll('{name}', 'Ghaith Kayali')
						.replaceAll('{email}', 'kunde@example.com')
						.replaceAll('{pdf_url}', 'https://example.com/duftberater.pdf');
				}
				function updatePreview(textarea){
					var target = document.getElementById(textarea.getAttribute('data-preview-target'));
					if (!target) return;
					target.innerHTML = replacePlaceholders(textarea.value);
				}
				document.querySelectorAll('.sydb-pdf-html-manager .sydb-html-source').forEach(function(textarea){
					updatePreview(textarea);
					textarea.addEventListener('input', function(){ updatePreview(textarea); });
				});
				document.querySelectorAll('.sydb-pdf-html-manager .sydb-lang-tab').forEach(function(btn){
					btn.addEventListener('click', function(){
						var lang = btn.getAttribute('data-sydb-tab');
						document.querySelectorAll('.sydb-pdf-html-manager .sydb-lang-tab').forEach(function(b){ b.classList.remove('is-active'); });
						document.querySelectorAll('.sydb-pdf-html-manager .sydb-lang-panel').forEach(function(p){ p.classList.remove('is-active'); });
						btn.classList.add('is-active');
						var panel = document.querySelector('.sydb-pdf-html-manager [data-sydb-panel="' + lang + '"]');
						if (panel) panel.classList.add('is-active');
					});
				});
			})();
			</script>
		</div>
		<?php
	}


	protected function render_pdf_template_image_control( $lang, $tpl, $key, $label, $description = '' ) {
		$url_key = $key . '_url';
		$id_key  = $key . '_id';
		$field_id = 'sydb_pdf_tpl_' . $lang . '_' . $key;
		$url = isset( $tpl[ $url_key ] ) ? esc_url( $tpl[ $url_key ] ) : '';
		$attachment_id = isset( $tpl[ $id_key ] ) ? absint( $tpl[ $id_key ] ) : 0;
		?>
		<div class="sydb-pdf-image-field">
			<strong><?php echo esc_html( $label ); ?></strong>
			<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
			<div class="sydb-pdf-image-preview" id="<?php echo esc_attr( $field_id ); ?>_preview">
				<?php if ( $url ) : ?>
					<img src="<?php echo esc_url( $url ); ?>" alt="" />
				<?php else : ?>
					<span>Keine Grafik gewählt</span>
				<?php endif; ?>
			</div>
			<input id="<?php echo esc_attr( $field_id ); ?>_id" type="hidden" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[pdf_templates][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $id_key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" />
			<input id="<?php echo esc_attr( $field_id ); ?>_url" type="text" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[pdf_templates][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $url_key ); ?>]" value="<?php echo esc_attr( $url ); ?>" placeholder="https://..." />
			<p style="margin:6px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
				<button type="button" class="button sydb-media-select" data-target="#<?php echo esc_attr( $field_id ); ?>_url" data-id-target="#<?php echo esc_attr( $field_id ); ?>_id" data-preview="#<?php echo esc_attr( $field_id ); ?>_preview">Bild wählen</button>
				<button type="button" class="button sydb-media-remove" data-target="#<?php echo esc_attr( $field_id ); ?>_url" data-id-target="#<?php echo esc_attr( $field_id ); ?>_id" data-preview="#<?php echo esc_attr( $field_id ); ?>_preview">Bild entfernen</button>
				<small>ID: <span><?php echo $attachment_id ? esc_html( (string) $attachment_id ) : 'wird nach Auswahl gespeichert'; ?></span></small>
			</p>
		</div>
		<?php
	}

	public function render_pdf_templates_field() {
		$settings  = $this->get_settings();
		$defaults  = $this->plugin->get_default_pdf_templates();
		$templates = isset( $settings['pdf_templates'] ) && is_array( $settings['pdf_templates'] ) ? $settings['pdf_templates'] : array();
		$languages = $this->plugin->get_supported_languages();
		$image_fields = array(
			'frame_image'       => array( 'Großer Rahmen / Hauptseitenrahmen', 'Vollflächige A4-Basisgrafik. Aus diesem Upload kommen Hintergrund, Wasserzeichen, Muster, Rahmen und statische Flächen.' ),
			'header_image'      => array( 'Logo-/Kopfbereich', 'Fertige Grafik mit Logo und festem Untertitel, ohne zusätzliche PDF-Texte darüber.' ),
			'coupon_image'      => array( 'Gutschein-/Rabattbox links', 'Komplette fertige Grafik, z. B. Exklusiv für dich, 15% OFF, Code-Bereich und Flasche.' ),
			'instruction_image' => array( 'Anleitung-/So-funktioniert-es-Box rechts', 'Komplette fertige Grafik mit den 3 Schritten.' ),
			'welcome_band_image' => array( 'Willkommensband über Filialkarten', 'Komplette fertige Grafik für das Band „WIR FREUEN UNS DARAUF …“ über den Filialkarten.' ),
			'footer_image'      => array( 'Footerbild unten Mitte', 'Optionales Bild für den unteren mittigen Footerbereich innerhalb der PDF unterhalb des Rahmens.' ),
			'branch_1_image'    => array( 'Filiale 1', 'Komplette Filialkarte als Grafik inklusive Land, Adresse und Flagge.' ),
			'branch_2_image'    => array( 'Filiale 2', 'Komplette Filialkarte als Grafik inklusive Land, Adresse und Flagge.' ),
			'branch_3_image'    => array( 'Filiale 3', 'Komplette Filialkarte als Grafik inklusive Land, Adresse und Flagge.' ),
			'branch_4_image'    => array( 'Filiale 4', 'Komplette Filialkarte als Grafik inklusive Land, Adresse und Flagge.' ),
			'branch_5_image'    => array( 'Filiale 5', 'Komplette Filialkarte als Grafik inklusive Land, Adresse und Flagge.' ),
		);
		$text_fields = array(
			'intro_text'   => array( 'Einleitung / Begrüßungstext', 'textarea', 'Platzhalter {name} wird automatisch durch den Kundennamen ersetzt.' ),
			'result_title' => array( 'Überschrift über Duftkarten', 'text', '' ),
			'website'      => array( 'Website unten', 'text', '' ),
			'email'        => array( 'E-Mail unten', 'text', '' ),
			'phone'        => array( 'Telefonnummer unten', 'text', '' ),
		);
		?>
		<div class="sydb-pdf-template-editor">
			<p class="description">PDF-Endvorlage: pro Sprache 11 Bildbereiche und 5 Textfelder. Es gibt keine eingebauten Placeholder-/Hintergrundbilder mehr; alle statischen Designteile kommen ausschließlich aus deinen hochgeladenen Bildern. Leere Bildfelder bleiben leer.</p>
			<style>
				.sydb-pdf-template-editor{display:grid;gap:14px;max-width:1180px}.sydb-pdf-template-tabs{display:flex;gap:8px;flex-wrap:wrap}.sydb-pdf-template-tab{border:1px solid #d8c4ab;background:#fffaf5;border-radius:999px;padding:8px 14px;font-weight:700;cursor:pointer;color:#705a42}.sydb-pdf-template-tab.is-active{background:#705a42;color:#fff;border-color:#705a42}.sydb-pdf-template-panel{display:none;border:1px solid #e5ddd0;border-radius:16px;padding:16px;background:#fffdf8}.sydb-pdf-template-panel.is-active{display:block}.sydb-pdf-template-section{margin-top:18px;padding-top:14px;border-top:1px solid #e5ddd0}.sydb-pdf-image-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(255px,1fr));gap:14px}.sydb-pdf-image-field{border:1px solid #e5ddd0;background:#fffaf4;border-radius:14px;padding:12px;display:grid;gap:8px}.sydb-pdf-image-field strong,.sydb-pdf-text-grid strong{display:block;margin-bottom:5px;color:#705a42}.sydb-pdf-image-preview{height:118px;border:1px dashed #d8c4ab;border-radius:10px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;color:#8a7a65}.sydb-pdf-image-preview img{max-width:100%;max-height:100%;display:block}.sydb-pdf-text-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}.sydb-pdf-text-grid label{display:block;border:1px solid #e5ddd0;background:#fffaf4;border-radius:14px;padding:12px}.sydb-pdf-text-grid textarea{min-height:92px}.sydb-pdf-note{background:#f6f0e8;border-left:4px solid #c9af7f;border-radius:10px;padding:10px 12px;color:#705a42}
			</style>
			<div class="sydb-pdf-template-tabs">
				<?php $first = true; foreach ( $languages as $lang => $language ) : ?>
					<button type="button" class="sydb-pdf-template-tab <?php echo $first ? 'is-active' : ''; ?>" data-pdf-template-tab="<?php echo esc_attr( $lang ); ?>"><?php echo esc_html( strtoupper( $lang ) . ' – ' . $language['native'] ); ?></button>
				<?php $first = false; endforeach; ?>
			</div>
			<?php $first = true; foreach ( $languages as $lang => $language ) : ?>
				<?php $tpl = wp_parse_args( $templates[ $lang ] ?? array(), $defaults[ $lang ] ?? array() ); ?>
				<div class="sydb-pdf-template-panel <?php echo $first ? 'is-active' : ''; ?>" data-pdf-template-panel="<?php echo esc_attr( $lang ); ?>">
					<h3 style="margin-top:0;">PDF-Endvorlage – <?php echo esc_html( strtoupper( $lang ) . ' / ' . $language['native'] ); ?></h3>
					<div class="sydb-pdf-note">Hinweis: Die roten Bereiche aus deiner Vorlage sind hier Bild-Uploads. Zusätzlich gibt es jetzt das Willkommensband über den Filialkarten als eigenes Bildfeld. Die Grafik wird aus der Mediathek möglichst über die Attachment-ID und den lokalen Dateipfad geladen.</div>
					<div class="sydb-pdf-template-section" style="border-top:0;padding-top:0;">
						<h4>11 Bild-Upload-Felder</h4>
						<div class="sydb-pdf-image-grid">
							<?php foreach ( $image_fields as $key => $meta ) : ?>
								<?php $this->render_pdf_template_image_control( $lang, $tpl, $key, $meta[0], $meta[1] ); ?>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="sydb-pdf-template-section">
						<h4>5 Textfelder</h4>
						<div class="sydb-pdf-text-grid">
							<?php foreach ( $text_fields as $key => $meta ) : ?>
								<label>
									<strong><?php echo esc_html( $meta[0] ); ?></strong>
									<?php if ( $meta[2] ) : ?><p class="description"><?php echo esc_html( $meta[2] ); ?></p><?php endif; ?>
									<?php if ( 'textarea' === $meta[1] ) : ?>
										<textarea class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[pdf_templates][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" <?php echo 'ar' === $lang ? 'dir="rtl" style="unicode-bidi:plaintext;direction:rtl;text-align:right;"' : ''; ?>><?php echo esc_textarea( $tpl[ $key ] ?? '' ); ?></textarea>
										<?php if ( 'ar' === $lang && 'intro_text' === $key ) : ?>
											<p class="description" dir="rtl" style="text-align:right;unicode-bidi:plaintext;">مثال قابل للتعديل: <code>مرحباً {name}،</code></p>
										<?php endif; ?>
									<?php else : ?>
										<input type="text" class="large-text" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[pdf_templates][<?php echo esc_attr( $lang ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $tpl[ $key ] ?? '' ); ?>" <?php echo 'ar' === $lang ? 'dir="rtl"' : ''; ?> />
									<?php endif; ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php $first = false; endforeach; ?>
			<script>
			(function(){
				document.querySelectorAll('.sydb-pdf-template-tab').forEach(function(btn){
					btn.addEventListener('click', function(){
						var lang = btn.getAttribute('data-pdf-template-tab');
						document.querySelectorAll('.sydb-pdf-template-tab').forEach(function(b){ b.classList.remove('is-active'); });
						document.querySelectorAll('.sydb-pdf-template-panel').forEach(function(p){ p.classList.remove('is-active'); });
						btn.classList.add('is-active');
						var panel = document.querySelector('[data-pdf-template-panel="' + lang + '"]');
						if(panel){ panel.classList.add('is-active'); }
					});
				});
			})();
			</script>
		</div>
		<?php
	}


	public function render_example_data_field() {
		$settings = $this->get_settings();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( SY_Duftberater::OPTION_SETTINGS ); ?>[enable_example_data]" value="1" <?php checked( 1, (int) $settings['enable_example_data'] ); ?> />
			Beispiel-Parfumdaten erlauben und Import-Funktion aktivieren
		</label>
		<?php
	}

	protected function admin_wrap_start( $title ) {
		echo '<div class="wrap"><h1>' . esc_html( $title ) . '</h1>';
	}

	protected function admin_wrap_end() {
		echo '</div>';
	}

	public function render_dashboard_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}

		$stats    = $this->plugin->get_stats();
		$settings = $this->get_settings();

		$this->admin_wrap_start( 'SY Duftberater – Dashboard' );
		?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:1000px;margin-top:20px;">
			<div style="background:#fff;padding:18px;border:1px solid #ddd;border-radius:12px;">
				<h2 style="margin-top:0;">Fragen</h2>
				<p style="font-size:28px;font-weight:700;margin:0;"><?php echo esc_html( $stats['question_count'] ); ?></p>
			</div>
			<div style="background:#fff;padding:18px;border:1px solid #ddd;border-radius:12px;">
				<h2 style="margin-top:0;">Parfums</h2>
				<p style="font-size:28px;font-weight:700;margin:0;"><?php echo esc_html( $stats['parfum_count'] ); ?></p>
			</div>
			<div style="background:#fff;padding:18px;border:1px solid #ddd;border-radius:12px;">
				<h2 style="margin-top:0;">Shortcode</h2>
				<p style="font-size:18px;font-weight:600;margin:0;"><code>[sy_duftberater]</code></p>
			</div>
			<div style="background:#fff;padding:18px;border:1px solid #ddd;border-radius:12px;">
				<h2 style="margin-top:0;">Akzentfarbe</h2>
				<p style="font-size:18px;font-weight:600;margin:0;"><?php echo esc_html( $settings['accent_color'] ); ?></p>
			</div>
			<div style="background:#fff;padding:18px;border:1px solid #ddd;border-radius:12px;">
				<h2 style="margin-top:0;">Frontend-Modus</h2>
				<p style="font-size:18px;font-weight:600;margin:0;"><?php echo 'light' === ( $settings['frontend_theme'] ?? 'dark' ) ? 'Hellmodus' : 'Dunkelmodus'; ?></p>
			</div>
		</div>

		<div style="margin-top:24px;background:#fff;padding:22px;border:1px solid #ddd;border-radius:12px;max-width:1000px;">
			<h2>Status & Hinweise</h2>
			<div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 14px;">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-parfum-manager' ) ); ?>">Parfum Manager öffnen</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-health' ) ); ?>">Pflege-Status prüfen</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-matching-simulator' ) ); ?>">Matching testen</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-import-export' ) ); ?>">Backup / Import</a>
			</div>
			<ul style="list-style:disc;padding-left:18px;">
				<li>Der neue <strong>Parfum Manager</strong> ist für schnelle Pflege, Filter und Duplikate gedacht.</li>
				<li><strong>Pflege-Status</strong> zeigt fehlende Bilder, Links, Übersetzungen und technische Server-Checks.</li>
				<li><strong>Matching Simulator</strong> testet Backend-Antworten gegen die aktuelle Parfum-Datenbank.</li>
				<li>Import / Export enthält jetzt Vorschau und Komplett-Backup für Einstellungen, Fragen und Parfums.</li>
			</ul>

			<?php if ( (int) $settings['enable_example_data'] === 1 ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
					<?php wp_nonce_field( 'sydb_import_examples' ); ?>
					<input type="hidden" name="action" value="sydb_import_examples" />
					<button type="submit" class="button button-primary">Beispiel-Parfums importieren</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
		$this->admin_wrap_end();
	}

	public function render_questions_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}

		wp_enqueue_media();

		$questions   = $this->plugin->get_questions();
		$edit_key    = sanitize_key( $_GET['edit_question'] ?? '' );
		$question    = array(
			'key'                    => '',
			'label'                  => '',
			'description'            => '',
			'label_i18n'             => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'description_i18n'       => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'multiple'               => 0,
			'required'               => 1,
			'order'                  => count( $questions ) + 1,
			'options'                => array(),
			'options_i18n'           => array(),
			'option_icons'           => array(),
			'usage_group_icons'      => array( 'alltag' => '', 'formell' => '' ),
			'usage_stage_audio_i18n' => array(
				'alltag'  => array( 'de' => '', 'en' => '', 'ar' => '' ),
				'arbeit'  => array( 'de' => '', 'en' => '', 'ar' => '' ),
				'formell' => array( 'de' => '', 'en' => '', 'ar' => '' ),
			),
			'answer_display'         => 'image_only',
			'audio_url'              => '',
			'audio_i18n'             => array( 'de' => '', 'en' => '', 'ar' => '' ),
		);

		foreach ( $questions as $item ) {
			if ( $edit_key && $edit_key === sanitize_key( $item['key'] ?? '' ) ) {
				$question = $item;
				break;
			}
		}

		$options_rows = array();
		foreach ( (array) ( $question['options'] ?? array() ) as $value => $label ) {
			$value = sanitize_key( $value );
			$i18n  = isset( $question['options_i18n'][ $value ] ) && is_array( $question['options_i18n'][ $value ] ) ? $question['options_i18n'][ $value ] : array( 'de' => $label, 'en' => '', 'ar' => '' );
			$options_rows[] = array(
				'key'  => $value,
				'de'   => $i18n['de'] ?? $label,
				'en'   => $i18n['en'] ?? '',
				'ar'   => $i18n['ar'] ?? '',
				'icon' => isset( $question['option_icons'][ $value ] ) ? (string) $question['option_icons'][ $value ] : '',
			);
		}
		if ( empty( $options_rows ) ) {
			$options_rows[] = array( 'key' => '', 'de' => '', 'en' => '', 'ar' => '', 'icon' => '' );
		}

		$answer_display = isset( $question['answer_display'] ) ? sanitize_key( $question['answer_display'] ) : 'image_only';
		$audio_i18n     = isset( $question['audio_i18n'] ) && is_array( $question['audio_i18n'] ) ? $question['audio_i18n'] : array();
		if ( empty( $audio_i18n['de'] ) && ! empty( $question['audio_url'] ) ) {
			$audio_i18n['de'] = $question['audio_url'];
		}
		$usage_group_icons = isset( $question['usage_group_icons'] ) && is_array( $question['usage_group_icons'] ) ? $question['usage_group_icons'] : array();
		$usage_stage_audio_i18n = isset( $question['usage_stage_audio_i18n'] ) && is_array( $question['usage_stage_audio_i18n'] ) ? $question['usage_stage_audio_i18n'] : array();

		$this->admin_wrap_start( 'SY Duftberater – Fragen' );
		?>
		<style>
			.sydb-question-admin-grid{display:grid;grid-template-columns:minmax(320px,.86fr) minmax(620px,1.14fr);gap:22px;max-width:1500px;margin-top:20px;align-items:start}.sydb-panel{background:#fff;border:1px solid #dcdcde;border-radius:16px;box-shadow:0 8px 28px rgba(0,0,0,.035);overflow:hidden}.sydb-panel-head{padding:16px 18px;border-bottom:1px solid #eef0f2;background:linear-gradient(180deg,#fff,#fbfbfb)}.sydb-panel-head h2{margin:0;font-size:18px}.sydb-panel-body{padding:18px}.sydb-question-list{display:grid;gap:10px}.sydb-question-item{display:grid;grid-template-columns:34px 1fr auto;gap:12px;align-items:center;border:1px solid #e5e7eb;border-radius:14px;background:#fff;padding:12px;text-decoration:none;color:#1d2327}.sydb-question-item:hover{border-color:#8c8f94;box-shadow:0 5px 18px rgba(0,0,0,.05)}.sydb-question-item.is-active{border-color:#2271b1;background:#f0f6fc}.sydb-question-order{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:#f0f0f1;font-weight:800}.sydb-question-item.is-active .sydb-question-order{background:#2271b1;color:#fff}.sydb-question-title{font-weight:800}.sydb-question-meta{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}.sydb-chip{display:inline-flex;border-radius:999px;background:#f0f0f1;padding:2px 8px;font-size:11px;font-weight:700;color:#50575e}.sydb-chip.warn{background:#fff4d8;color:#7a5300}.sydb-chip.ok{background:#dff4ea;color:#0f5132}.sydb-form-section{border:1px solid #e5e7eb;border-radius:16px;background:#fff;margin-bottom:16px;overflow:hidden}.sydb-form-section h3{margin:0;padding:13px 16px;background:#f6f7f7;border-bottom:1px solid #e5e7eb;font-size:14px}.sydb-form-section-body{padding:16px}.sydb-field-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sydb-field-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.sydb-admin-label{display:block;font-weight:700;color:#2c3338}.sydb-admin-label span{display:block;margin-bottom:6px}.sydb-admin-label input,.sydb-admin-label textarea,.sydb-admin-label select{width:100%;max-width:100%}.sydb-option-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px;flex-wrap:wrap}.sydb-option-list{display:grid;gap:12px}.sydb-option-card{border:1px solid #dcdcde;border-radius:16px;background:#fbfbfb;padding:14px}.sydb-option-card-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}.sydb-option-card-title{font-weight:800}.sydb-option-grid{display:grid;grid-template-columns:140px repeat(3,minmax(0,1fr));gap:10px;align-items:end}.sydb-option-image-row{display:grid;grid-template-columns:1fr auto 72px;gap:8px;align-items:center;margin-top:10px}.sydb-option-preview{width:64px;height:64px;object-fit:cover;border-radius:12px;border:1px solid #ddd;background:#fff}.sydb-input-button-row{display:flex;gap:8px;align-items:center}.sydb-input-button-row input{min-width:0;flex:1;width:100%}.sydb-audio-language-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.sydb-audio-field{display:block;border:1px solid #eadfca;border-radius:14px;background:#fff;padding:12px;min-width:0}.sydb-audio-field span{display:block;font-weight:700;margin-bottom:8px;color:#4b3925}.sydb-audio-preview{width:100%;max-width:100%;margin-top:8px;height:34px}.sydb-follow-audio-wrap{display:grid;gap:12px}.sydb-follow-audio-card{border:1px solid #e3d4b2;border-radius:16px;background:linear-gradient(180deg,#fffdf8,#fff8ee);padding:14px}.sydb-follow-audio-card-title{margin:0 0 12px;font-weight:800;color:#382613}.sydb-savebar{position:sticky;bottom:0;z-index:5;background:#fff;border-top:1px solid #dcdcde;padding:14px 16px;margin:18px -18px -18px;display:flex;justify-content:space-between;align-items:center;gap:12px}.sydb-muted{color:#646970}.sydb-raw-import{margin-top:12px;border-top:1px dashed #ccd0d4;padding-top:12px}.sydb-raw-import textarea{font-family:Consolas,monospace}.sydb-danger-link{color:#b32d2e;border-color:#f0b6b8!important}.sydb-option-card.is-hidden{display:none}@media(max-width:1160px){.sydb-question-admin-grid{grid-template-columns:1fr}.sydb-field-grid,.sydb-field-grid.two,.sydb-audio-language-grid,.sydb-option-grid{grid-template-columns:1fr}.sydb-option-image-row{grid-template-columns:1fr}.sydb-option-preview{width:100%;max-width:120px;height:90px}.sydb-savebar{position:static}}
		</style>
		<p class="sydb-muted">Die Werte bleiben erhalten: alle bestehenden Keys, Texte, Bilder und MP3s werden aus der aktuellen Datenbank geladen. Du bearbeitest sie jetzt nur über Karten statt über die alte Textarea.</p>
		<div class="sydb-question-admin-grid">
			<div class="sydb-panel">
				<div class="sydb-panel-head"><h2>Fragen</h2></div>
				<div class="sydb-panel-body">
					<div class="sydb-question-list">
						<?php foreach ( $questions as $item ) :
							$item_key = sanitize_key( $item['key'] ?? '' );
							$item_url = admin_url( 'admin.php?page=sy-duftberater-questions&edit_question=' . $item_key );
							$is_active = ( $edit_key && $edit_key === $item_key );
						?>
							<a class="sydb-question-item <?php echo $is_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $item_url ); ?>">
								<span class="sydb-question-order"><?php echo esc_html( (string) ( $item['order'] ?? '' ) ); ?></span>
								<span>
									<span class="sydb-question-title"><?php echo esc_html( $item['label_i18n']['de'] ?? $item['label'] ?? '' ); ?></span><br>
									<code><?php echo esc_html( $item_key ); ?></code>
									<span class="sydb-question-meta">
										<span class="sydb-chip"><?php echo ! empty( $item['multiple'] ) ? 'Mehrfachauswahl' : 'Einzelauswahl'; ?></span>
										<span class="sydb-chip <?php echo ! empty( $item['required'] ) ? 'ok' : 'warn'; ?>"><?php echo ! empty( $item['required'] ) ? 'Pflicht' : 'Optional'; ?></span>
										<span class="sydb-chip"><?php echo esc_html( count( (array) ( $item['options'] ?? array() ) ) ); ?> Optionen</span>
									</span>
								</span>
								<span class="dashicons dashicons-edit"></span>
							</a>
						<?php endforeach; ?>
					</div>
					<p style="margin-top:16px;"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-questions' ) ); ?>">Neue Frage</a></p>
				</div>
			</div>

			<div class="sydb-panel">
				<div class="sydb-panel-head"><h2><?php echo $edit_key ? 'Frage modern bearbeiten' : 'Neue Frage hinzufügen'; ?></h2></div>
				<div class="sydb-panel-body">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="sydb-question-form">
						<?php wp_nonce_field( 'sydb_save_question' ); ?>
						<input type="hidden" name="action" value="sydb_save_question">
						<input type="hidden" name="original_key" value="<?php echo esc_attr( $edit_key ); ?>">

						<div class="sydb-form-section">
							<h3>Grunddaten</h3>
							<div class="sydb-form-section-body">
								<div class="sydb-field-grid two">
									<label class="sydb-admin-label"><span>Key</span><input id="sydb-question-key" name="question[key]" type="text" class="regular-text" value="<?php echo esc_attr( $question['key'] ); ?>" required></label>
									<label class="sydb-admin-label"><span>Reihenfolge</span><input id="sydb-question-order" name="question[order]" type="number" class="small-text" value="<?php echo esc_attr( (string) $question['order'] ); ?>" min="1"></label>
								</div>
								<div class="sydb-field-grid" style="margin-top:12px;">
									<label class="sydb-admin-label"><span>Fragetext Deutsch</span><input name="question[label_i18n][de]" type="text" value="<?php echo esc_attr( $question['label_i18n']['de'] ?? $question['label'] ); ?>" required></label>
									<label class="sydb-admin-label"><span>Fragetext English</span><input name="question[label_i18n][en]" type="text" value="<?php echo esc_attr( $question['label_i18n']['en'] ?? '' ); ?>"></label>
									<label class="sydb-admin-label"><span>Fragetext العربية</span><input name="question[label_i18n][ar]" type="text" dir="rtl" value="<?php echo esc_attr( $question['label_i18n']['ar'] ?? '' ); ?>"></label>
								</div>
								<div class="sydb-field-grid" style="margin-top:12px;">
									<label class="sydb-admin-label"><span>Beschreibung Deutsch</span><textarea name="question[description_i18n][de]" rows="3"><?php echo esc_textarea( $question['description_i18n']['de'] ?? $question['description'] ); ?></textarea></label>
									<label class="sydb-admin-label"><span>Beschreibung English</span><textarea name="question[description_i18n][en]" rows="3"><?php echo esc_textarea( $question['description_i18n']['en'] ?? '' ); ?></textarea></label>
									<label class="sydb-admin-label"><span>Beschreibung العربية</span><textarea name="question[description_i18n][ar]" rows="3" dir="rtl"><?php echo esc_textarea( $question['description_i18n']['ar'] ?? '' ); ?></textarea></label>
								</div>
							</div>
						</div>

						<div class="sydb-form-section">
							<h3>Verhalten</h3>
							<div class="sydb-form-section-body">
								<div class="sydb-field-grid">
									<label class="sydb-admin-label"><span>Antworttyp</span><label><input type="checkbox" name="question[multiple]" value="1" <?php checked( ! empty( $question['multiple'] ) ); ?>> Mehrfachauswahl erlauben</label><br><label><input type="checkbox" name="question[required]" value="1" <?php checked( ! empty( $question['required'] ) ); ?>> Pflichtfrage</label></label>
									<div class="sydb-admin-label"><span>Antwort-Anzeige</span><label><input type="radio" name="question[answer_display]" value="image_only" <?php checked( $answer_display, 'image_only' ); ?>> Nur Bild</label><br><label><input type="radio" name="question[answer_display]" value="image_text" <?php checked( $answer_display, 'image_text' ); ?>> Bild + Text</label><br><label><input type="radio" name="question[answer_display]" value="text" <?php checked( $answer_display, 'text' ); ?>> Nur Text</label></div>
									<p class="sydb-muted">Die vorhandenen Frontend-Werte bleiben unverändert. Wichtig ist vor allem, dass die Antwort-Keys nicht geändert werden, wenn schon Parfum-Zuordnungen damit arbeiten.</p>
								</div>
							</div>
						</div>

						<div class="sydb-form-section">
							<h3>Frage-Audio MP3 je Sprache</h3>
							<div class="sydb-form-section-body">
								<div class="sydb-audio-language-grid">
									<?php foreach ( $this->plugin->get_supported_languages() as $audio_lang => $language ) : ?>
										<?php $field_id = 'sydb-question-audio-' . $audio_lang; $audio_val = esc_url( $audio_i18n[ $audio_lang ] ?? '' ); ?>
										<label class="sydb-audio-field">
											<span><?php echo esc_html( $language['label'] ); ?> / <?php echo esc_html( strtoupper( $audio_lang ) ); ?></span>
											<div class="sydb-input-button-row"><input id="<?php echo esc_attr( $field_id ); ?>" name="question[audio_i18n][<?php echo esc_attr( $audio_lang ); ?>]" type="url" value="<?php echo esc_attr( $audio_val ); ?>" placeholder="https://.../frage-<?php echo esc_attr( $audio_lang ); ?>.mp3"><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#<?php echo esc_attr( $field_id ); ?>">MP3 wählen</button></div>
											<?php if ( $audio_val ) : ?><audio class="sydb-audio-preview" controls preload="none" src="<?php echo esc_url( $audio_val ); ?>"></audio><?php endif; ?>
										</label>
									<?php endforeach; ?>
								</div>
								<input type="hidden" name="question[audio_url]" value="">
							</div>
						</div>

						<?php if ( 'verwendungsbereich' === sanitize_key( $question['key'] ?? '' ) ) : ?>
						<div class="sydb-form-section">
							<h3>Verwendungsbereich – Hauptauswahl & Folgefragen</h3>
							<div class="sydb-form-section-body">
								<div class="sydb-field-grid two">
									<?php foreach ( array( 'alltag' => 'Alltag', 'formell' => 'Formell / Wichtige Anlässe' ) as $usage_key => $usage_label ) : ?>
										<?php $usage_field_id = 'sydb-usage-group-icon-' . $usage_key; $usage_icon_val = esc_url( $usage_group_icons[ $usage_key ] ?? '' ); ?>
										<label class="sydb-admin-label"><span><?php echo esc_html( $usage_label ); ?> Bild</span><div class="sydb-input-button-row"><input id="<?php echo esc_attr( $usage_field_id ); ?>" name="question[usage_group_icons][<?php echo esc_attr( $usage_key ); ?>]" type="url" value="<?php echo esc_attr( $usage_icon_val ); ?>"><button type="button" class="button sydb-media-select" data-target="#<?php echo esc_attr( $usage_field_id ); ?>">Bild wählen</button></div></label>
									<?php endforeach; ?>
								</div>
								<div class="sydb-follow-audio-wrap" style="margin-top:14px;">
									<?php foreach ( array( 'alltag' => 'Unterfrage Alltag', 'arbeit' => 'Unterfrage Arbeit', 'formell' => 'Unterfrage Formell' ) as $stage_key => $stage_label ) : ?>
										<div class="sydb-follow-audio-card">
											<div class="sydb-follow-audio-card-title"><?php echo esc_html( $stage_label ); ?> – Audio</div>
											<div class="sydb-audio-language-grid">
												<?php foreach ( $this->plugin->get_supported_languages() as $audio_lang => $language ) : ?>
													<?php $field_id = 'sydb-usage-stage-audio-' . $stage_key . '-' . $audio_lang; $audio_val = esc_url( $usage_stage_audio_i18n[ $stage_key ][ $audio_lang ] ?? '' ); ?>
													<label class="sydb-audio-field"><span><?php echo esc_html( $language['label'] ); ?> / <?php echo esc_html( strtoupper( $audio_lang ) ); ?></span><div class="sydb-input-button-row"><input id="<?php echo esc_attr( $field_id ); ?>" name="question[usage_stage_audio_i18n][<?php echo esc_attr( $stage_key ); ?>][<?php echo esc_attr( $audio_lang ); ?>]" type="url" value="<?php echo esc_attr( $audio_val ); ?>"><button type="button" class="button sydb-media-select" data-media-type="audio" data-target="#<?php echo esc_attr( $field_id ); ?>">MP3 wählen</button></div><?php if ( $audio_val ) : ?><audio class="sydb-audio-preview" controls preload="none" src="<?php echo esc_url( $audio_val ); ?>"></audio><?php endif; ?></label>
												<?php endforeach; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<?php endif; ?>

						<div class="sydb-form-section">
							<h3>Antwortoptionen</h3>
							<div class="sydb-form-section-body">
								<div class="sydb-option-toolbar">
									<p class="sydb-muted" style="margin:0;">Jede Antwort ist jetzt eine eigene Karte. Key, Texte und Bild-URL bleiben beim Speichern sauber getrennt.</p>
									<button type="button" class="button button-primary" id="sydb-add-option">Antwort hinzufügen</button>
								</div>
								<div class="sydb-option-list" id="sydb-option-list">
									<?php foreach ( $options_rows as $idx => $row ) : ?>
										<div class="sydb-option-card" data-index="<?php echo esc_attr( (string) $idx ); ?>">
											<div class="sydb-option-card-head"><span class="sydb-option-card-title">Antwort <span class="sydb-option-number"><?php echo esc_html( (string) ( $idx + 1 ) ); ?></span></span><button type="button" class="button sydb-danger-link sydb-remove-option">Entfernen</button></div>
											<div class="sydb-option-grid">
												<label class="sydb-admin-label"><span>Key</span><input name="question_option_rows[<?php echo esc_attr( (string) $idx ); ?>][key]" type="text" value="<?php echo esc_attr( $row['key'] ); ?>" placeholder="z.B. sommer"></label>
												<label class="sydb-admin-label"><span>Deutsch</span><input name="question_option_rows[<?php echo esc_attr( (string) $idx ); ?>][de]" type="text" value="<?php echo esc_attr( $row['de'] ); ?>" placeholder="Deutsch"></label>
												<label class="sydb-admin-label"><span>English</span><input name="question_option_rows[<?php echo esc_attr( (string) $idx ); ?>][en]" type="text" value="<?php echo esc_attr( $row['en'] ); ?>" placeholder="English"></label>
												<label class="sydb-admin-label"><span>العربية</span><input name="question_option_rows[<?php echo esc_attr( (string) $idx ); ?>][ar]" type="text" dir="rtl" value="<?php echo esc_attr( $row['ar'] ); ?>" placeholder="العربية"></label>
											</div>
											<div class="sydb-option-image-row">
												<input class="sydb-option-icon-input" name="question_option_rows[<?php echo esc_attr( (string) $idx ); ?>][icon]" type="url" value="<?php echo esc_attr( $row['icon'] ); ?>" placeholder="Bild-URL">
												<button type="button" class="button sydb-media-select" data-target=".sydb-option-card[data-index='<?php echo esc_attr( (string) $idx ); ?>'] .sydb-option-icon-input">Bild wählen</button>
												<?php if ( ! empty( $row['icon'] ) ) : ?><img class="sydb-option-preview" src="<?php echo esc_url( $row['icon'] ); ?>" alt=""><?php else : ?><span class="sydb-option-preview"></span><?php endif; ?>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
								<details class="sydb-raw-import">
									<summary><strong>Optional: alte Zeilen einfügen</strong></summary>
									<p class="sydb-muted">Nur benutzen, wenn du viele Antworten schnell aus der alten Schreibweise einfügen willst. Format: <code>wert|Deutsch|English|العربية|Bild-URL</code>. Beim Speichern haben diese Zeilen Vorrang.</p>
									<textarea name="question_options" rows="5" class="large-text code" placeholder="sommer|Sommer|Summer|الصيف|https://..."></textarea>
								</details>
							</div>
						</div>

						<div class="sydb-savebar">
							<span class="sydb-muted">Tipp: Antwort-Keys nur ändern, wenn du danach auch Parfum-Zuordnungen prüfst.</span>
							<span><?php if ( $edit_key ) : ?><button type="submit" class="button button-primary button-large">Frage speichern</button><?php else : ?><button type="submit" class="button button-primary button-large">Frage hinzufügen</button><?php endif; ?></span>
						</div>
					</form>
					<?php if ( $edit_key ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Frage wirklich löschen?');" style="margin-top:14px;">
							<?php wp_nonce_field( 'sydb_delete_question' ); ?>
							<input type="hidden" name="action" value="sydb_delete_question">
							<input type="hidden" name="question_key" value="<?php echo esc_attr( $edit_key ); ?>">
							<button type="submit" class="button sydb-danger-link">Diese Frage löschen</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<template id="sydb-option-template">
			<div class="sydb-option-card" data-index="__INDEX__">
				<div class="sydb-option-card-head"><span class="sydb-option-card-title">Antwort <span class="sydb-option-number"></span></span><button type="button" class="button sydb-danger-link sydb-remove-option">Entfernen</button></div>
				<div class="sydb-option-grid">
					<label class="sydb-admin-label"><span>Key</span><input name="question_option_rows[__INDEX__][key]" type="text" placeholder="z.B. sommer"></label>
					<label class="sydb-admin-label"><span>Deutsch</span><input name="question_option_rows[__INDEX__][de]" type="text" placeholder="Deutsch"></label>
					<label class="sydb-admin-label"><span>English</span><input name="question_option_rows[__INDEX__][en]" type="text" placeholder="English"></label>
					<label class="sydb-admin-label"><span>العربية</span><input name="question_option_rows[__INDEX__][ar]" type="text" dir="rtl" placeholder="العربية"></label>
				</div>
				<div class="sydb-option-image-row"><input class="sydb-option-icon-input" name="question_option_rows[__INDEX__][icon]" type="url" placeholder="Bild-URL"><button type="button" class="button sydb-media-select" data-target=".sydb-option-card[data-index='__INDEX__'] .sydb-option-icon-input">Bild wählen</button><span class="sydb-option-preview"></span></div>
			</div>
		</template>
		<script>
		(function($){
			function refreshOptionCards(){
				$('#sydb-option-list .sydb-option-card').each(function(i){
					$(this).find('.sydb-option-number').text(i+1);
				});
			}
			$('#sydb-add-option').on('click',function(){
				var idx = Date.now().toString();
				var tpl = $('#sydb-option-template').html().replace(/__INDEX__/g, idx);
				$('#sydb-option-list').append(tpl);
				refreshOptionCards();
			});
			$(document).on('click','.sydb-remove-option',function(){
				if($('#sydb-option-list .sydb-option-card').length <= 1){
					$(this).closest('.sydb-option-card').find('input').val('');
					return;
				}
				$(this).closest('.sydb-option-card').remove();
				refreshOptionCards();
			});
			$(document).on('input change','.sydb-option-icon-input',function(){
				var card = $(this).closest('.sydb-option-card');
				var target = card.find('.sydb-option-preview');
				var url = $(this).val();
				if(url){
					if(!target.is('img')){ target.replaceWith('<img class="sydb-option-preview" alt="">'); target = card.find('.sydb-option-preview'); }
					target.attr('src', url);
				}
			});
			$(document).off('click.sydbMediaSelect','.sydb-media-select').on('click.sydbMediaSelect','.sydb-media-select',function(e){
				e.preventDefault();
				var button = $(this);
				var target = $(button.attr('data-target'));
				if(!target.length){ return; }
				if(typeof wp === 'undefined' || !wp.media){ alert('WordPress-Mediathek konnte nicht geladen werden. Bitte Seite neu laden.'); return; }
				var mediaType = button.attr('data-media-type') || (button.text().toLowerCase().indexOf('mp3') !== -1 ? 'audio' : 'image');
				var frame = wp.media({title: mediaType === 'audio' ? 'MP3 auswählen' : 'Bild auswählen',library: { type: mediaType },button: { text: mediaType === 'audio' ? 'MP3 übernehmen' : 'Bild übernehmen' },multiple: false});
				frame.on('select',function(){
					var att = frame.state().get('selection').first().toJSON();
					target.val(att.url).trigger('input').trigger('change');
				});
				frame.open();
			});
			refreshOptionCards();
		})(jQuery);
		</script>
		<?php
		$this->admin_wrap_end();
	}

	public function handle_save_question() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		check_admin_referer( 'sydb_save_question' );

		$questions    = $this->plugin->get_questions();
		$original_key = sanitize_key( wp_unslash( $_POST['original_key'] ?? '' ) );
		$raw_question = (array) wp_unslash( $_POST['question'] ?? array() );

		$options      = array();
		$options_i18n = array();
		$option_icons = array();

		// Neuer moderner Karten-Editor.
		$option_rows = isset( $_POST['question_option_rows'] ) && is_array( $_POST['question_option_rows'] ) ? (array) wp_unslash( $_POST['question_option_rows'] ) : array();
		foreach ( $option_rows as $row ) {
			$row   = is_array( $row ) ? $row : array();
			$value = sanitize_key( $row['key'] ?? '' );
			$de    = sanitize_text_field( $row['de'] ?? '' );
			$en    = sanitize_text_field( $row['en'] ?? '' );
			$ar    = sanitize_text_field( $row['ar'] ?? '' );
			$icon  = esc_url_raw( $row['icon'] ?? '' );
			if ( '' !== $value && '' !== $de ) {
				$options[ $value ] = $de;
				$options_i18n[ $value ] = array( 'de' => $de, 'en' => $en, 'ar' => $ar );
				$option_icons[ $value ] = $icon;
			}
		}

		// Fallback/Power-Import: alte Zeilen-Schreibweise. Wenn hier etwas eingetragen ist, ersetzt es die Kartenwerte.
		$legacy_raw = trim( (string) wp_unslash( $_POST['question_options'] ?? '' ) );
		if ( '' !== $legacy_raw ) {
			$options      = array();
			$options_i18n = array();
			$option_icons = array();
			$options_lines = explode( "\n", $legacy_raw );
			foreach ( $options_lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || false === strpos( $line, '|' ) ) {
					continue;
				}
				$parts = array_map( 'trim', explode( '|', $line ) );
				$value = sanitize_key( $parts[0] ?? '' );
				$de    = sanitize_text_field( $parts[1] ?? '' );
				$en    = sanitize_text_field( $parts[2] ?? '' );
				$ar    = sanitize_text_field( $parts[3] ?? '' );
				$icon  = esc_url_raw( $parts[4] ?? '' );
				if ( '' !== $value && '' !== $de ) {
					$options[ $value ] = $de;
					$options_i18n[ $value ] = array( 'de' => $de, 'en' => $en, 'ar' => $ar );
					$option_icons[ $value ] = $icon;
				}
			}
		}

		$label_i18n = isset( $raw_question['label_i18n'] ) && is_array( $raw_question['label_i18n'] ) ? $raw_question['label_i18n'] : array();
		$desc_i18n  = isset( $raw_question['description_i18n'] ) && is_array( $raw_question['description_i18n'] ) ? $raw_question['description_i18n'] : array();

		$audio_i18n_raw = isset( $raw_question['audio_i18n'] ) && is_array( $raw_question['audio_i18n'] ) ? $raw_question['audio_i18n'] : array();
		$audio_i18n = array();
		foreach ( array_keys( $this->plugin->get_supported_languages() ) as $audio_lang ) {
			$audio_i18n[ $audio_lang ] = isset( $audio_i18n_raw[ $audio_lang ] ) ? esc_url_raw( $audio_i18n_raw[ $audio_lang ] ) : '';
		}

		$usage_group_icons_raw = isset( $raw_question['usage_group_icons'] ) && is_array( $raw_question['usage_group_icons'] ) ? $raw_question['usage_group_icons'] : array();
		$usage_group_icons = array(
			'alltag'  => isset( $usage_group_icons_raw['alltag'] ) ? esc_url_raw( $usage_group_icons_raw['alltag'] ) : '',
			'formell' => isset( $usage_group_icons_raw['formell'] ) ? esc_url_raw( $usage_group_icons_raw['formell'] ) : '',
		);

		$usage_stage_audio_raw = isset( $raw_question['usage_stage_audio_i18n'] ) && is_array( $raw_question['usage_stage_audio_i18n'] ) ? $raw_question['usage_stage_audio_i18n'] : array();
		$usage_stage_audio_i18n = array();
		foreach ( array( 'alltag', 'arbeit', 'formell' ) as $stage_key ) {
			$usage_stage_audio_i18n[ $stage_key ] = array();
			foreach ( array_keys( $this->plugin->get_supported_languages() ) as $audio_lang ) {
				$usage_stage_audio_i18n[ $stage_key ][ $audio_lang ] = isset( $usage_stage_audio_raw[ $stage_key ][ $audio_lang ] ) ? esc_url_raw( $usage_stage_audio_raw[ $stage_key ][ $audio_lang ] ) : '';
			}
		}

		$new_question = array(
			'key'                    => sanitize_key( $raw_question['key'] ?? '' ),
			'label'                  => sanitize_text_field( $label_i18n['de'] ?? '' ),
			'description'            => isset( $desc_i18n['de'] ) ? sanitize_textarea_field( $desc_i18n['de'] ) : '',
			'label_i18n'             => array(
				'de' => sanitize_text_field( $label_i18n['de'] ?? '' ),
				'en' => sanitize_text_field( $label_i18n['en'] ?? '' ),
				'ar' => sanitize_text_field( $label_i18n['ar'] ?? '' ),
			),
			'description_i18n'       => array(
				'de' => sanitize_textarea_field( $desc_i18n['de'] ?? '' ),
				'en' => sanitize_textarea_field( $desc_i18n['en'] ?? '' ),
				'ar' => sanitize_textarea_field( $desc_i18n['ar'] ?? '' ),
			),
			'multiple'               => ! empty( $raw_question['multiple'] ) ? 1 : 0,
			'required'               => array_key_exists( 'required', $raw_question ) ? 1 : 0,
			'order'                  => absint( $raw_question['order'] ?? 0 ),
			'options'                => $options,
			'options_i18n'           => $options_i18n,
			'option_icons'           => $option_icons,
			'usage_group_icons'      => $usage_group_icons,
			'usage_stage_audio_i18n' => $usage_stage_audio_i18n,
			'answer_display'         => ( isset( $raw_question['answer_display'] ) && in_array( sanitize_key( $raw_question['answer_display'] ), array( 'image_only', 'image_text', 'text' ), true ) ) ? sanitize_key( $raw_question['answer_display'] ) : 'image_only',
			'audio_url'              => $audio_i18n['de'] ?? '',
			'audio_i18n'             => $audio_i18n,
		);

		$updated  = array();
		$replaced = false;

		foreach ( $questions as $question ) {
			$key = sanitize_key( $question['key'] ?? '' );
			if ( $original_key && $key === $original_key ) {
				$updated[] = $new_question;
				$replaced = true;
			} elseif ( ! $original_key && $key === $new_question['key'] ) {
				$updated[] = $new_question;
				$replaced = true;
			} else {
				$updated[] = $question;
			}
		}

		if ( ! $replaced ) {
			$updated[] = $new_question;
		}

		$this->plugin->update_questions( $updated );
		wp_safe_redirect( admin_url( 'admin.php?page=sy-duftberater-questions&edit_question=' . sanitize_key( $new_question['key'] ) . '&sydb_saved=1' ) );
		exit;
	}

	public function handle_delete_question() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		check_admin_referer( 'sydb_delete_question' );

		$delete_key = sanitize_key( wp_unslash( $_POST['question_key'] ?? '' ) );
		$updated = array();

		foreach ( $this->plugin->get_questions() as $question ) {
			if ( $delete_key !== sanitize_key( $question['key'] ?? '' ) ) {
				$updated[] = $question;
			}
		}

		$this->plugin->update_questions( $updated );
		wp_safe_redirect( admin_url( 'admin.php?page=sy-duftberater-questions' ) );
		exit;
	}

	public function handle_import_examples() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}

		check_admin_referer( 'sydb_import_examples' );
		$this->plugin->maybe_import_example_parfums( true );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . SY_Duftberater::POST_TYPE_PARFUM ) );
		exit;
	}


	protected function get_current_admin_url() {
		$scheme = is_ssl() ? 'https://' : 'http://';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return esc_url_raw( $scheme . $host . $uri );
	}

	protected function get_parfum_image_url( $post_id, $size = 'thumbnail' ) {
		$image = get_the_post_thumbnail_url( $post_id, $size );
		if ( ! $image ) {
			$image = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', true );
		}
		return esc_url_raw( (string) $image );
	}

	protected function get_parfum_missing_issues( $post_id ) {
		$issues = array();
		if ( '' === $this->get_parfum_image_url( $post_id, 'thumbnail' ) ) {
			$issues['missing_image'] = 'Bild fehlt';
		}
		if ( '' === (string) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'produktlink', true ) ) {
			$issues['missing_link'] = 'Produktlink fehlt';
		}
		foreach ( array(
			'geschlecht'         => 'Geschlecht fehlt',
			'jahreszeit'         => 'Jahreszeit fehlt',
			'verwendungsbereich' => 'Bereich fehlt',
			'duftrichtungen'     => 'Duftrichtung fehlt',
		) as $field => $label ) {
			$value = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, true );
			if ( empty( $value ) || ! is_array( $value ) ) {
				$issues[ 'missing_' . $field ] = $label;
			}
		}
		$title_i18n = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', true );
		$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
		$desc_i18n  = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'description_i18n', true );
		$desc_i18n  = is_array( $desc_i18n ) ? $desc_i18n : array();
		if ( empty( $title_i18n['en'] ) || empty( $title_i18n['ar'] ) ) {
			$issues['missing_translation'] = 'Übersetzung fehlt';
		}
		if ( empty( $desc_i18n['de'] ) && '' === trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) ) {
			$issues['missing_description'] = 'Beschreibung fehlt';
		}
		if ( 'draft' === get_post_status( $post_id ) ) {
			$issues['draft'] = 'Entwurf';
		}
		return $issues;
	}

	protected function get_parfum_manager_filter_options() {
		return array(
			'geschlecht'         => $this->plugin->get_options_for_question( 'geschlecht' ),
			'jahreszeit'         => $this->plugin->get_options_for_question( 'jahreszeit' ),
			'verwendungsbereich' => $this->plugin->get_options_for_question( 'verwendungsbereich' ),
			'alter'              => $this->plugin->get_options_for_question( 'alter' ),
			'duftrichtungen'     => $this->plugin->get_options_for_question( 'duftrichtungen' ),
			'duftnoten'          => $this->plugin->get_options_for_question( 'duftnoten' ),
		);
	}

	protected function get_filtered_parfum_posts_for_manager( $filters ) {
		$status = isset( $filters['status'] ) ? sanitize_key( $filters['status'] ) : '';
		$args   = array(
			'post_type'      => SY_Duftberater::POST_TYPE_PARFUM,
			'post_status'    => in_array( $status, array( 'publish', 'draft', 'private' ), true ) ? $status : array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! empty( $filters['s'] ) ) {
			$args['s'] = sanitize_text_field( $filters['s'] );
		}
		$meta_query = array( 'relation' => 'AND' );
		foreach ( array( 'geschlecht', 'jahreszeit', 'verwendungsbereich', 'duftrichtungen' ) as $field ) {
			if ( ! empty( $filters[ $field ] ) ) {
				$meta_query[] = array(
					'key'     => SY_Duftberater::META_PREFIX . $field,
					'value'   => '"' . sanitize_key( $filters[ $field ] ) . '"',
					'compare' => 'LIKE',
				);
			}
		}
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}
		$posts = get_posts( $args );
		$issue = isset( $filters['issue'] ) ? sanitize_key( $filters['issue'] ) : '';
		if ( $issue ) {
			$posts = array_values( array_filter( $posts, function( $post ) use ( $issue ) {
				$issues = $this->get_parfum_missing_issues( $post->ID );
				return isset( $issues[ $issue ] );
			} ) );
		}
		return $posts;
	}

	protected function render_multiselect_field( $name, $choices, $selected, $size = 4 ) {
		$selected = array_map( 'sanitize_key', (array) $selected );
		echo '<select multiple size="' . esc_attr( (string) $size ) . '" name="' . esc_attr( $name ) . '[]" style="width:100%;min-height:92px;">';
		foreach ( (array) $choices as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( in_array( sanitize_key( $value ), $selected, true ), true, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	public function render_parfum_manager_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_enqueue_media();
		$options = $this->get_parfum_manager_filter_options();
		$filters = array(
			's'                 => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'status'            => sanitize_key( wp_unslash( $_GET['status'] ?? '' ) ),
			'geschlecht'        => sanitize_key( wp_unslash( $_GET['geschlecht'] ?? '' ) ),
			'jahreszeit'        => sanitize_key( wp_unslash( $_GET['jahreszeit'] ?? '' ) ),
			'verwendungsbereich'=> sanitize_key( wp_unslash( $_GET['verwendungsbereich'] ?? '' ) ),
			'duftrichtungen'    => sanitize_key( wp_unslash( $_GET['duftrichtungen'] ?? '' ) ),
			'issue'             => sanitize_key( wp_unslash( $_GET['issue'] ?? '' ) ),
		);
		$all_posts = $this->get_filtered_parfum_posts_for_manager( $filters );
		$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page  = 20;
		$total     = count( $all_posts );
		$pages     = max( 1, (int) ceil( $total / $per_page ) );
		$posts     = array_slice( $all_posts, ( $page - 1 ) * $per_page, $per_page );

		$this->admin_wrap_start( 'SY Duftberater – Parfum Manager' );
		?>
		<style>
			.sydb-manager-filter{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px;margin:18px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end;max-width:1420px}.sydb-manager-card{background:#fff;border:1px solid #dcdcde;border-radius:16px;padding:0;margin:14px 0;max-width:1420px;box-shadow:0 8px 20px rgba(0,0,0,.035)}.sydb-manager-head{display:grid;grid-template-columns:74px minmax(180px,1fr) minmax(220px,1.2fr) 180px;gap:14px;align-items:center;padding:14px 16px}.sydb-manager-img{width:60px;height:60px;object-fit:cover;border-radius:12px;background:#f6f7f7;border:1px solid #ddd}.sydb-badges{display:flex;flex-wrap:wrap;gap:6px}.sydb-badge{display:inline-flex;padding:3px 8px;border-radius:999px;background:#f0f0f1;color:#50575e;font-size:12px;font-weight:600}.sydb-badge.warn{background:#fff3cd;color:#7a5300}.sydb-badge.ok{background:#d1e7dd;color:#0f5132}.sydb-badge.bad{background:#f8d7da;color:#842029}.sydb-manager-edit{border-top:1px solid #eee;padding:16px;background:#fbfbfb}.sydb-manager-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.sydb-manager-grid label strong{display:block;margin-bottom:5px}.sydb-manager-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.sydb-admin-note{color:#646970}.sydb-pagination{display:flex;gap:6px;align-items:center;margin:16px 0}.sydb-pagination a,.sydb-pagination span{padding:5px 10px;border:1px solid #ccd0d4;background:#fff;text-decoration:none;border-radius:6px}.sydb-pagination .current{background:#2271b1;color:#fff;border-color:#2271b1}@media(max-width:900px){.sydb-manager-head{grid-template-columns:60px 1fr}.sydb-manager-head>*:nth-child(n+3){grid-column:1/-1}}
		</style>
		<p class="sydb-admin-note">Hier kannst du Parfums schneller suchen, filtern, prüfen, duplizieren und die wichtigsten Zuordnungen direkt pflegen. Die klassische WordPress-Maske bleibt weiterhin verfügbar.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:10px 0 16px;"><?php wp_nonce_field( 'sydb_export_parfums_matrix_xlsx' ); ?><input type="hidden" name="action" value="sydb_export_parfums_matrix_xlsx" /><button type="submit" class="button button-primary">Pflegeliste / Matrix exportieren</button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-import-export' ) ); ?>">Import / Export öffnen</a></form>
		<form method="get" class="sydb-manager-filter">
			<input type="hidden" name="page" value="sy-duftberater-parfum-manager" />
			<label><strong>Suche</strong><input type="search" name="s" value="<?php echo esc_attr( $filters['s'] ); ?>" class="regular-text" placeholder="Name suchen"></label>
			<label><strong>Status</strong><select name="status"><option value="">Alle</option><option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Veröffentlicht</option><option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Entwurf</option><option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privat</option></select></label>
			<label><strong>Geschlecht</strong><select name="geschlecht"><option value="">Alle</option><?php foreach ( $options['geschlecht'] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['geschlecht'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong>Jahreszeit</strong><select name="jahreszeit"><option value="">Alle</option><?php foreach ( $options['jahreszeit'] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['jahreszeit'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong>Bereich</strong><select name="verwendungsbereich"><option value="">Alle</option><?php foreach ( $options['verwendungsbereich'] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['verwendungsbereich'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong>Duftrichtung</strong><select name="duftrichtungen"><option value="">Alle</option><?php foreach ( $options['duftrichtungen'] as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['duftrichtungen'], $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><strong>Pflegefehler</strong><select name="issue"><option value="">Alle</option><option value="missing_image" <?php selected( $filters['issue'], 'missing_image' ); ?>>Bild fehlt</option><option value="missing_link" <?php selected( $filters['issue'], 'missing_link' ); ?>>Produktlink fehlt</option><option value="missing_translation" <?php selected( $filters['issue'], 'missing_translation' ); ?>>Übersetzung fehlt</option><option value="missing_duftrichtungen" <?php selected( $filters['issue'], 'missing_duftrichtungen' ); ?>>Duftrichtung fehlt</option><option value="draft" <?php selected( $filters['issue'], 'draft' ); ?>>Entwürfe</option></select></label>
			<p style="margin:0;"><button class="button button-primary">Filtern</button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-parfum-manager' ) ); ?>">Reset</a></p>
		</form>
		<p><strong><?php echo esc_html( $total ); ?></strong> Parfums gefunden. Seite <?php echo esc_html( $page ); ?> von <?php echo esc_html( $pages ); ?>.</p>
		<?php foreach ( $posts as $post ) :
			$post_id = $post->ID;
			$title_i18n = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', true );
			$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
			$desc_i18n = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'description_i18n', true );
			$desc_i18n = is_array( $desc_i18n ) ? $desc_i18n : array();
			$image = $this->get_parfum_image_url( $post_id, 'thumbnail' );
			$issues = $this->get_parfum_missing_issues( $post_id );
			$meta_values = array();
			foreach ( array_keys( $options ) as $field ) { $meta_values[ $field ] = (array) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, true ); }
		?>
			<div class="sydb-manager-card">
				<div class="sydb-manager-head">
					<div><?php if ( $image ) : ?><img class="sydb-manager-img" src="<?php echo esc_url( $image ); ?>" alt=""><?php else : ?><div class="sydb-manager-img"></div><?php endif; ?></div>
					<div><strong style="font-size:16px;"><?php echo esc_html( $post->post_title ); ?></strong><br><small>ID <?php echo esc_html( $post_id ); ?> · <?php echo esc_html( $post->post_status ); ?></small></div>
					<div class="sydb-badges"><?php if ( empty( $issues ) ) : ?><span class="sydb-badge ok">vollständig</span><?php else : foreach ( $issues as $issue_label ) : ?><span class="sydb-badge warn"><?php echo esc_html( $issue_label ); ?></span><?php endforeach; endif; ?></div>
					<div class="sydb-manager-actions"><a class="button" href="<?php echo esc_url( get_edit_post_link( $post_id, '' ) ); ?>">Klassisch bearbeiten</a><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Parfum duplizieren?');"><?php wp_nonce_field( 'sydb_duplicate_parfum_' . $post_id ); ?><input type="hidden" name="action" value="sydb_duplicate_parfum"><input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr( $this->get_current_admin_url() ); ?>"><button class="button">Duplizieren</button></form></div>
				</div>
				<details class="sydb-manager-edit">
					<summary><strong>Schnell bearbeiten</strong></summary>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px;">
						<?php wp_nonce_field( 'sydb_quick_update_parfum_' . $post_id ); ?>
						<input type="hidden" name="action" value="sydb_quick_update_parfum"><input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr( $this->get_current_admin_url() ); ?>">
						<div class="sydb-manager-grid">
							<label><strong>Titel Deutsch</strong><input type="text" name="sydb_post_title" class="large-text" value="<?php echo esc_attr( $title_i18n['de'] ?? $post->post_title ); ?>"></label>
							<label><strong>Titel Englisch</strong><input type="text" name="sydb_meta[title_i18n][en]" class="large-text" value="<?php echo esc_attr( $title_i18n['en'] ?? '' ); ?>"></label>
							<label><strong>Titel Arabisch</strong><input type="text" dir="rtl" name="sydb_meta[title_i18n][ar]" class="large-text" value="<?php echo esc_attr( $title_i18n['ar'] ?? '' ); ?>"></label>
							<label><strong>Status</strong><select name="sydb_post_status"><option value="publish" <?php selected( $post->post_status, 'publish' ); ?>>Veröffentlicht</option><option value="draft" <?php selected( $post->post_status, 'draft' ); ?>>Entwurf</option><option value="private" <?php selected( $post->post_status, 'private' ); ?>>Privat</option></select></label>
							<label><strong>Produktlink</strong><input type="url" name="sydb_meta[produktlink]" class="large-text" value="<?php echo esc_attr( get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'produktlink', true ) ); ?>"></label>
							<label><strong>Externe Bild-URL</strong><input id="sydb-image-url-<?php echo esc_attr( $post_id ); ?>" type="url" name="sydb_meta[image_url]" class="large-text" value="<?php echo esc_attr( get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', true ) ); ?>"><button type="button" class="button sydb-media-select" data-target="#sydb-image-url-<?php echo esc_attr( $post_id ); ?>">Bild wählen</button></label>
						</div>
						<div class="sydb-manager-grid" style="margin-top:14px;">
							<label><strong>Geschlecht</strong><?php $this->render_multiselect_field( 'sydb_meta[geschlecht]', $options['geschlecht'], $meta_values['geschlecht'], 4 ); ?></label>
							<label><strong>Jahreszeit</strong><?php $this->render_multiselect_field( 'sydb_meta[jahreszeit]', $options['jahreszeit'], $meta_values['jahreszeit'], 4 ); ?></label>
							<label><strong>Bereich</strong><?php $this->render_multiselect_field( 'sydb_meta[verwendungsbereich]', $options['verwendungsbereich'], $meta_values['verwendungsbereich'], 5 ); ?></label>
							<label><strong>Alter</strong><?php $this->render_multiselect_field( 'sydb_meta[alter]', $options['alter'], $meta_values['alter'], 4 ); ?></label>
							<label><strong>Duftrichtungen</strong><?php $this->render_multiselect_field( 'sydb_meta[duftrichtungen]', $options['duftrichtungen'], $meta_values['duftrichtungen'], 7 ); ?></label>
							<label><strong>Ausschlusskategorien</strong><?php $this->render_multiselect_field( 'sydb_meta[duftnoten]', $options['duftnoten'], $meta_values['duftnoten'], 7 ); ?></label>
						</div>
						<p style="margin-top:14px;"><label><input type="checkbox" name="sydb_meta[raucher_geeignet]" value="1" <?php checked( (int) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'raucher_geeignet', true ), 1 ); ?>> Für Raucher geeignet</label></p>
						<p><button class="button button-primary">Schnelländerungen speichern</button></p>
					</form>
				</details>
			</div>
		<?php endforeach; ?>
		<?php if ( $pages > 1 ) : ?>
			<div class="sydb-pagination">
				<?php for ( $i = 1; $i <= $pages; $i++ ) : $url = add_query_arg( array_merge( $_GET, array( 'paged' => $i ) ), admin_url( 'admin.php' ) ); ?>
					<?php if ( $i === $page ) : ?><span class="current"><?php echo esc_html( $i ); ?></span><?php else : ?><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $i ); ?></a><?php endif; ?>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
		<script>
		(function($){
			$(document).off('click.sydbManagerMedia','.sydb-media-select').on('click.sydbManagerMedia','.sydb-media-select',function(e){
				e.preventDefault();var button=$(this),target=$(button.attr('data-target'));if(!target.length){return;}if(typeof wp==='undefined'||!wp.media){alert('Mediathek konnte nicht geladen werden.');return;}var frame=wp.media({title:'Bild auswählen',library:{type:'image'},button:{text:'Bild übernehmen'},multiple:false});frame.on('select',function(){var att=frame.state().get('selection').first().toJSON();target.val(att.url).trigger('change');});frame.open();
			});
		})(jQuery);
		</script>
		<?php
		$this->admin_wrap_end();
	}

	public function handle_quick_update_parfum() {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || SY_Duftberater::POST_TYPE_PARFUM !== get_post_type( $post_id ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_quick_update_parfum_' . $post_id );
		$meta = (array) wp_unslash( $_POST['sydb_meta'] ?? array() );
		$title_de = sanitize_text_field( wp_unslash( $_POST['sydb_post_title'] ?? '' ) );
		$status = sanitize_key( wp_unslash( $_POST['sydb_post_status'] ?? 'draft' ) );
		if ( ! in_array( $status, array( 'publish', 'draft', 'private' ), true ) ) {
			$status = 'draft';
		}
		if ( '' !== $title_de ) {
			wp_update_post( array( 'ID' => $post_id, 'post_title' => $title_de, 'post_status' => $status ) );
		} else {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ) );
		}
		foreach ( array( 'geschlecht', 'jahreszeit', 'verwendungsbereich', 'alter', 'duftrichtungen', 'duftnoten' ) as $field ) {
			$values = array_values( array_filter( array_map( 'sanitize_key', (array) ( $meta[ $field ] ?? array() ) ) ) );
			update_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, $values );
		}
		$current_title_i18n = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', true );
		$current_title_i18n = is_array( $current_title_i18n ) ? $current_title_i18n : array();
		$title_i18n = isset( $meta['title_i18n'] ) && is_array( $meta['title_i18n'] ) ? $meta['title_i18n'] : array();
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', array(
			'de' => $title_de ? $title_de : ( $current_title_i18n['de'] ?? get_the_title( $post_id ) ),
			'en' => sanitize_text_field( $title_i18n['en'] ?? ( $current_title_i18n['en'] ?? '' ) ),
			'ar' => sanitize_text_field( $title_i18n['ar'] ?? ( $current_title_i18n['ar'] ?? '' ) ),
		) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'raucher_geeignet', ! empty( $meta['raucher_geeignet'] ) ? 1 : 0 );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'produktlink', esc_url_raw( $meta['produktlink'] ?? '' ) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', esc_url_raw( $meta['image_url'] ?? '' ) );
		$return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : admin_url( 'admin.php?page=sy-duftberater-parfum-manager' );
		wp_safe_redirect( add_query_arg( 'sydb_saved', '1', $return_url ) );
		exit;
	}

	public function handle_duplicate_parfum() {
		$post_id = absint( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || SY_Duftberater::POST_TYPE_PARFUM !== get_post_type( $post_id ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_duplicate_parfum_' . $post_id );
		$source = get_post( $post_id );
		$new_id = wp_insert_post( array(
			'post_type'    => SY_Duftberater::POST_TYPE_PARFUM,
			'post_title'   => $source->post_title . ' Kopie',
			'post_content' => $source->post_content,
			'post_status'  => 'draft',
		), true );
		if ( ! is_wp_error( $new_id ) && $new_id ) {
			foreach ( $this->plugin->get_parfum_meta_fields() as $field ) {
				$value = get_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, true );
				update_post_meta( $new_id, SY_Duftberater::META_PREFIX . $field, $value );
			}
			$thumb_id = get_post_thumbnail_id( $post_id );
			if ( $thumb_id ) {
				set_post_thumbnail( $new_id, $thumb_id );
			}
		}
		$return_url = isset( $_POST['return_url'] ) ? esc_url_raw( wp_unslash( $_POST['return_url'] ) ) : admin_url( 'admin.php?page=sy-duftberater-parfum-manager' );
		wp_safe_redirect( add_query_arg( 'sydb_duplicated', '1', $return_url ) );
		exit;
	}

	public function render_health_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}
		$posts = $this->get_filtered_parfum_posts_for_manager( array() );
		$issue_counts = array();
		$problem_rows = array();
		foreach ( $posts as $post ) {
			$issues = $this->get_parfum_missing_issues( $post->ID );
			foreach ( $issues as $key => $label ) {
				$issue_counts[ $key ] = ( $issue_counts[ $key ] ?? 0 ) + 1;
			}
			if ( ! empty( $issues ) ) {
				$problem_rows[] = array( 'post' => $post, 'issues' => $issues );
			}
		}
		$upload = wp_upload_dir();
		$checks = array(
			array( 'label' => 'Upload-Ordner beschreibbar', 'ok' => ! empty( $upload['basedir'] ) && is_writable( $upload['basedir'] ) ),
			array( 'label' => 'ZipArchive für XLSX/Backup verfügbar', 'ok' => class_exists( 'ZipArchive' ) ),
			array( 'label' => 'SimpleXML für XLSX-Import verfügbar', 'ok' => function_exists( 'simplexml_load_string' ) ),
			array( 'label' => 'GD-Bildfunktionen verfügbar', 'ok' => extension_loaded( 'gd' ) || function_exists( 'imagecreatefromstring' ) ),
			array( 'label' => 'wp_mail() verfügbar', 'ok' => function_exists( 'wp_mail' ) ),
		);
		$this->admin_wrap_start( 'SY Duftberater – Pflege-Status' );
		?>
		<style>.sydb-health-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;max-width:1200px;margin:18px 0}.sydb-health-card{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:16px}.sydb-health-card strong{font-size:26px;display:block}.sydb-status-ok{color:#087c2f;font-weight:700}.sydb-status-bad{color:#b32d2e;font-weight:700}</style>
		<p>Diese Seite zeigt dir sofort, welche Daten fehlen und ob wichtige Server-Funktionen für Import, Export, PDFs und E-Mails verfügbar sind.</p>
		<div class="sydb-health-grid">
			<div class="sydb-health-card"><span>Parfums gesamt</span><strong><?php echo esc_html( count( $posts ) ); ?></strong></div>
			<div class="sydb-health-card"><span>Problemfälle</span><strong><?php echo esc_html( count( $problem_rows ) ); ?></strong></div>
			<div class="sydb-health-card"><span>Bilder fehlen</span><strong><?php echo esc_html( $issue_counts['missing_image'] ?? 0 ); ?></strong></div>
			<div class="sydb-health-card"><span>Produktlinks fehlen</span><strong><?php echo esc_html( $issue_counts['missing_link'] ?? 0 ); ?></strong></div>
			<div class="sydb-health-card"><span>Übersetzungen fehlen</span><strong><?php echo esc_html( $issue_counts['missing_translation'] ?? 0 ); ?></strong></div>
			<div class="sydb-health-card"><span>Duftrichtungen fehlen</span><strong><?php echo esc_html( $issue_counts['missing_duftrichtungen'] ?? 0 ); ?></strong></div>
		</div>
		<h2>Systemchecks</h2>
		<table class="widefat striped" style="max-width:900px;"><tbody><?php foreach ( $checks as $check ) : ?><tr><td><?php echo esc_html( $check['label'] ); ?></td><td><?php echo $check['ok'] ? '<span class="sydb-status-ok">OK</span>' : '<span class="sydb-status-bad">Fehlt / prüfen</span>'; ?></td></tr><?php endforeach; ?></tbody></table>
		<h2 style="margin-top:24px;">Parfums mit Pflegebedarf</h2>
		<table class="widefat striped"><thead><tr><th>Name</th><th>Status</th><th>Fehler / Hinweise</th><th>Aktion</th></tr></thead><tbody>
		<?php if ( empty( $problem_rows ) ) : ?><tr><td colspan="4">Keine Pflegeprobleme gefunden.</td></tr><?php else : foreach ( array_slice( $problem_rows, 0, 250 ) as $row ) : ?><tr><td><strong><?php echo esc_html( $row['post']->post_title ); ?></strong></td><td><?php echo esc_html( $row['post']->post_status ); ?></td><td><?php echo esc_html( implode( ', ', $row['issues'] ) ); ?></td><td><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-parfum-manager&s=' . rawurlencode( $row['post']->post_title ) ) ); ?>">Im Manager öffnen</a></td></tr><?php endforeach; endif; ?>
		</tbody></table>
		<?php
		$this->admin_wrap_end();
	}

	public function render_matching_simulator_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}
		$answers = array();
		$ran = false;
		if ( isset( $_POST['sydb_simulator_submit'] ) ) {
			check_admin_referer( 'sydb_matching_simulator' );
			$raw_answers = (array) wp_unslash( $_POST['answers'] ?? array() );
			foreach ( $this->plugin->get_questions() as $question ) {
				$key = sanitize_key( $question['key'] ?? '' );
				if ( ! $key ) { continue; }
				if ( ! empty( $question['multiple'] ) ) {
					$answers[ $key ] = array_values( array_filter( array_map( 'sanitize_key', (array) ( $raw_answers[ $key ] ?? array() ) ) ) );
				} else {
					$answers[ $key ] = sanitize_key( $raw_answers[ $key ] ?? '' );
				}
			}
			$ran = true;
		}
		$matcher = new SY_Duftberater_Matcher( $this->plugin );
		$results = $ran ? $matcher->get_results( $answers ) : array();
		$this->admin_wrap_start( 'SY Duftberater – Matching Simulator' );
		?>
		<style>.sydb-sim-grid{display:grid;grid-template-columns:minmax(280px,420px) minmax(320px,1fr);gap:20px;align-items:start;max-width:1320px}.sydb-sim-box{background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:18px}.sydb-sim-field{display:block;margin-bottom:12px}.sydb-sim-field strong{display:block;margin-bottom:5px}.sydb-result-row{border:1px solid #e2e4e7;border-radius:12px;padding:14px;margin:0 0 12px;background:#fff}.sydb-result-row h3{margin:0 0 6px}.sydb-score{font-weight:800;font-size:20px;color:#2271b1}</style>
		<div class="sydb-sim-grid">
			<div class="sydb-sim-box">
				<h2 style="margin-top:0;">Test-Antworten</h2>
				<form method="post">
					<?php wp_nonce_field( 'sydb_matching_simulator' ); ?>
					<?php foreach ( $this->plugin->get_questions() as $question ) : $key = sanitize_key( $question['key'] ?? '' ); if ( ! $key ) { continue; } $opts = (array) ( $question['options'] ?? array() ); ?>
						<label class="sydb-sim-field"><strong><?php echo esc_html( $question['label'] ?? $key ); ?></strong>
						<?php if ( ! empty( $question['multiple'] ) ) : ?>
							<select multiple size="<?php echo esc_attr( (string) min( 8, max( 3, count( $opts ) ) ) ); ?>" name="answers[<?php echo esc_attr( $key ); ?>][]" style="width:100%;">
								<?php foreach ( $opts as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( in_array( sanitize_key( $value ), (array) ( $answers[ $key ] ?? array() ), true ) ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
							</select>
						<?php else : ?>
							<select name="answers[<?php echo esc_attr( $key ); ?>]" style="width:100%;"><option value="">— auswählen —</option><?php foreach ( $opts as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $answers[ $key ] ?? '', sanitize_key( $value ) ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
						<?php endif; ?>
						</label>
					<?php endforeach; ?>
					<p><button class="button button-primary" name="sydb_simulator_submit" value="1">Matching testen</button></p>
				</form>
			</div>
			<div class="sydb-sim-box">
				<h2 style="margin-top:0;">Ergebnis</h2>
				<?php if ( ! $ran ) : ?><p>Wähle links Antworten aus und teste, welche Top-3 das Plugin aktuell ausgeben würde.</p><?php elseif ( empty( $results ) ) : ?><p>Keine passenden Parfums gefunden. Prüfe Geschlecht, Ausschlussnoten oder fehlende Parfum-Zuordnungen.</p><?php else : foreach ( $results as $index => $result ) : $parfum = $result['parfum'] ?? array(); ?>
					<div class="sydb-result-row"><h3><?php echo esc_html( 'Top ' . ( $index + 1 ) . ': ' . ( $parfum['name'] ?? '' ) ); ?></h3><div class="sydb-score"><?php echo esc_html( (string) ( $result['percentage'] ?? 0 ) ); ?>% Match</div><p><?php echo esc_html( $result['reason'] ?? '' ); ?></p><?php if ( ! empty( $parfum['produktlink'] ) ) : ?><p><a class="button" href="<?php echo esc_url( $parfum['produktlink'] ); ?>" target="_blank" rel="noopener">Produkt öffnen</a></p><?php endif; ?></div>
				<?php endforeach; endif; ?>
			</div>
		</div>
		<?php
		$this->admin_wrap_end();
	}

	public function register_parfum_meta_boxes() {
		add_meta_box(
			'sydb-parfum-details',
			'Parfum-Attribute',
			array( $this, 'render_parfum_meta_box' ),
			SY_Duftberater::POST_TYPE_PARFUM,
			'normal',
			'high'
		);
	}

	public function render_parfum_meta_box( $post ) {
		wp_nonce_field( 'sydb_save_parfum_meta', 'sydb_parfum_meta_nonce' );

		$choices = array(
			'geschlecht'         => $this->plugin->get_options_for_question( 'geschlecht' ),
			'jahreszeit'         => $this->plugin->get_options_for_question( 'jahreszeit' ),
			'verwendungsbereich' => $this->plugin->get_options_for_question( 'verwendungsbereich' ),
			'alter'              => $this->plugin->get_options_for_question( 'alter' ),
			'duftrichtungen'     => $this->plugin->get_options_for_question( 'duftrichtungen' ),
			'duftnoten'          => $this->plugin->get_options_for_question( 'duftnoten' ),
		);

		$question_groups = array(
			'verwendungsbereich' => $this->plugin->get_question_groups( 'verwendungsbereich' ),
			'duftnoten'          => $this->plugin->get_question_groups( 'duftnoten' ),
		);

		$values = array();
		foreach ( $this->plugin->get_parfum_meta_fields() as $field ) {
			$values[ $field ] = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . $field, true );
		}
		?>
		<p><strong>Hinweis:</strong> Oben kannst du weiterhin den normalen WordPress-Titel pflegen. Für die mehrsprachige Ausgabe im Duftberater nutzt du die Felder unten: Titel DE/EN/AR und Beschreibung DE/EN/AR. Für Fotos kannst du rechts das Beitragsbild verwenden oder unten eine externe Bild-URL hinterlegen.</p>
		<p><strong>Wichtig:</strong> Die Zuordnung dieses Parfums soll direkt der Roadmap folgen. Trage den Duft also genau in die passenden Geschlechter, Jahreszeiten, Verwendungsbereiche, Duftrichtungen und Altersgruppen ein.</p><p><strong>Clean Version:</strong> Nicht benötigte Zusatzfelder wurden entfernt, damit die Parfum-Pflege im Backend einfacher und übersichtlicher ist.</p>

		<?php
		$title_i18n = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'title_i18n', true );
		$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
		$desc_i18n = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'description_i18n', true );
		$desc_i18n = is_array( $desc_i18n ) ? $desc_i18n : array();
		?>
		<div style="border:1px solid #d8c797;background:#fffdf8;border-radius:14px;padding:18px;margin:18px 0 24px;">
			<h3 style="margin-top:0;">Mehrsprachige Produkttexte</h3>
			<p class="description">Diese Texte werden auf der Ergebnisseite automatisch je nach gewählter Sprache angezeigt. Leere EN/AR-Felder fallen automatisch auf Deutsch zurück.</p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:14px;">
				<div style="border:1px solid #eee;border-radius:12px;padding:14px;background:#fff;">
					<strong>DE</strong>
					<label style="display:block;margin-top:10px;">Titel Deutsch<input type="text" class="large-text" name="sydb_meta[title_i18n][de]" value="<?php echo esc_attr( $title_i18n['de'] ?? get_the_title( $post ) ); ?>"></label>
					<label style="display:block;margin-top:10px;">Beschreibung Deutsch<textarea class="large-text" rows="4" name="sydb_meta[description_i18n][de]"><?php echo esc_textarea( $desc_i18n['de'] ?? wp_strip_all_tags( $post->post_content ) ); ?></textarea></label>
				</div>
				<div style="border:1px solid #eee;border-radius:12px;padding:14px;background:#fff;">
					<strong>EN</strong>
					<label style="display:block;margin-top:10px;">Title English<input type="text" class="large-text" name="sydb_meta[title_i18n][en]" value="<?php echo esc_attr( $title_i18n['en'] ?? '' ); ?>"></label>
					<label style="display:block;margin-top:10px;">Description English<textarea class="large-text" rows="4" name="sydb_meta[description_i18n][en]"><?php echo esc_textarea( $desc_i18n['en'] ?? '' ); ?></textarea></label>
				</div>
				<div style="border:1px solid #eee;border-radius:12px;padding:14px;background:#fff;">
					<strong>AR</strong>
					<label style="display:block;margin-top:10px;">العنوان بالعربية<input type="text" class="large-text" dir="rtl" name="sydb_meta[title_i18n][ar]" value="<?php echo esc_attr( $title_i18n['ar'] ?? '' ); ?>"></label>
					<label style="display:block;margin-top:10px;">الوصف بالعربية<textarea class="large-text" rows="4" dir="rtl" name="sydb_meta[description_i18n][ar]"><?php echo esc_textarea( $desc_i18n['ar'] ?? '' ); ?></textarea></label>
				</div>
			</div>
		</div>

		<table class="form-table" role="presentation">
			<?php foreach ( $choices as $field_key => $field_options ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( 'duftnoten' === $field_key ? 'Ausschlusskategorien aus Frage 7' : ucfirst( str_replace( '_', ' ', $field_key ) ) ); ?></th>
					<td>
						<?php $selected = (array) ( $values[ $field_key ] ?? array() ); ?>
						<?php $groups = $question_groups[ $field_key ] ?? array(); ?>
						<?php if ( ! empty( $groups ) ) : ?>
							<div style="display:grid;gap:14px;">
								<?php foreach ( $groups as $group ) : ?>
									<div style="border:1px solid #ddd;border-radius:12px;padding:12px;background:#fafafa;">
										<strong style="display:block;margin-bottom:8px;"><?php echo esc_html( $group['label'] ?? '' ); ?></strong>
										<?php if ( ! empty( $group['description'] ) ) : ?><p style="margin:0 0 10px;color:#555;"><?php echo esc_html( $group['description'] ); ?></p><?php endif; ?>
										<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
											<?php foreach ( (array) ( $group['options'] ?? array() ) as $value ) : ?>
												<?php if ( empty( $field_options[ $value ] ) ) { continue; } ?>
												<label><input type="checkbox" name="sydb_meta[<?php echo esc_attr( $field_key ); ?>][]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $selected, true ) ); ?>> <?php echo esc_html( $field_options[ $value ] ); ?></label>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						<?php else : ?>
							<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;">
								<?php foreach ( $field_options as $value => $label ) : ?>
									<label><input type="checkbox" name="sydb_meta[<?php echo esc_attr( $field_key ); ?>][]" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( $value, $selected, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row">Für Raucher geeignet</th>
				<td><label><input type="checkbox" name="sydb_meta[raucher_geeignet]" value="1" <?php checked( ! empty( $values['raucher_geeignet'] ) ); ?>> Ja</label></td>
			</tr>
			<tr>
				<th scope="row"><label for="sydb-produktlink">Produktlink</label></th>
				<td><input id="sydb-produktlink" type="url" name="sydb_meta[produktlink]" class="large-text" value="<?php echo esc_attr( (string) $values['produktlink'] ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><label for="sydb-image-url">Externe Bild-URL</label></th>
				<td><input id="sydb-image-url" type="url" name="sydb_meta[image_url]" class="large-text" value="<?php echo esc_attr( (string) $values['image_url'] ); ?>"></td>
			</tr>
		</table>
		<?php
	}

	public function save_parfum_meta( $post_id ) {
		if ( ! isset( $_POST['sydb_parfum_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sydb_parfum_meta_nonce'] ) ), 'sydb_save_parfum_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$meta = (array) wp_unslash( $_POST['sydb_meta'] ?? array() );

		$array_fields = array( 'geschlecht', 'jahreszeit', 'verwendungsbereich', 'alter', 'duftrichtungen', 'duftnoten' );
		foreach ( $array_fields as $field ) {
			$values = array_values( array_filter( array_map( 'sanitize_key', (array) ( $meta[ $field ] ?? array() ) ) ) );
			update_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, $values );
		}

		$title_i18n = isset( $meta['title_i18n'] ) && is_array( $meta['title_i18n'] ) ? $meta['title_i18n'] : array();
		$desc_i18n  = isset( $meta['description_i18n'] ) && is_array( $meta['description_i18n'] ) ? $meta['description_i18n'] : array();
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', array(
			'de' => sanitize_text_field( $title_i18n['de'] ?? '' ),
			'en' => sanitize_text_field( $title_i18n['en'] ?? '' ),
			'ar' => sanitize_text_field( $title_i18n['ar'] ?? '' ),
		) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'description_i18n', array(
			'de' => sanitize_textarea_field( $desc_i18n['de'] ?? '' ),
			'en' => sanitize_textarea_field( $desc_i18n['en'] ?? '' ),
			'ar' => sanitize_textarea_field( $desc_i18n['ar'] ?? '' ),
		) );

		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'raucher_geeignet', ! empty( $meta['raucher_geeignet'] ) ? 1 : 0 );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'produktlink', esc_url_raw( $meta['produktlink'] ?? '' ) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', esc_url_raw( $meta['image_url'] ?? '' ) );
	}

	public function filter_parfum_columns( $columns ) {
		return array(
			'cb'       => $columns['cb'] ?? '<input type="checkbox" />',
			'title'    => 'Name',
			'image'    => 'Bild',
			'gender'   => 'Geschlecht',
			'season'   => 'Jahreszeit',
			'usage'    => 'Bereich',
			'direction'=> 'Duftrichtungen',
			'date'     => 'Datum',
		);
	}

	public function render_parfum_columns( $column, $post_id ) {
		if ( 'image' === $column ) {
			$image = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
			if ( ! $image ) {
				$image = esc_url( get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', true ) );
			}
			if ( $image ) {
				echo '<img src="' . esc_url( $image ) . '" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:10px;">';
			}
		}
		if ( 'gender' === $column ) {
			echo esc_html( implode( ', ', (array) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'geschlecht', true ) ) );
		}
		if ( 'season' === $column ) {
			echo esc_html( implode( ', ', (array) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'jahreszeit', true ) ) );
		}
		if ( 'usage' === $column ) {
			echo esc_html( implode( ', ', (array) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'verwendungsbereich', true ) ) );
		}
		if ( 'direction' === $column ) {
			echo esc_html( implode( ', ', (array) get_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'duftrichtungen', true ) ) );
		}
	}



	public function render_leads_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}

		if ( isset( $_POST['sydb_leads_table_action'] ) ) {
			check_admin_referer( 'sydb_leads_table_action' );
			$single_delete = absint( $_POST['sydb_single_delete'] ?? 0 );
			if ( $single_delete ) {
				if ( SY_Duftberater::POST_TYPE_LEAD === get_post_type( $single_delete ) ) {
					wp_delete_post( $single_delete, true );
					echo '<div class="notice notice-success"><p>Eintrag wurde gelöscht.</p></div>';
				}
			} else {
				$bulk_action = sanitize_key( wp_unslash( $_POST['bulk_action'] ?? '' ) );
				$lead_ids    = isset( $_POST['lead_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['lead_ids'] ) ) : array();
				$lead_ids    = array_values( array_unique( array_filter( $lead_ids ) ) );
				$done        = 0;

				if ( $bulk_action && ! empty( $lead_ids ) ) {
					foreach ( $lead_ids as $bulk_lead_id ) {
						if ( SY_Duftberater::POST_TYPE_LEAD !== get_post_type( $bulk_lead_id ) ) {
							continue;
						}
						if ( 'delete' === $bulk_action ) {
							wp_delete_post( $bulk_lead_id, true );
							$done++;
						} elseif ( 'coupon_used' === $bulk_action ) {
							update_post_meta( $bulk_lead_id, SY_Duftberater::META_PREFIX . 'coupon_used', 1 );
							$done++;
						} elseif ( 'coupon_unused' === $bulk_action ) {
							update_post_meta( $bulk_lead_id, SY_Duftberater::META_PREFIX . 'coupon_used', 0 );
							$done++;
						}
					}

					if ( $done ) {
						if ( 'delete' === $bulk_action ) {
							echo '<div class="notice notice-success"><p>' . esc_html( $done . ' Einträge wurden gelöscht.' ) . '</p></div>';
						} elseif ( 'coupon_used' === $bulk_action ) {
							echo '<div class="notice notice-success"><p>' . esc_html( $done . ' Einträge wurden als Gutschein genutzt markiert.' ) . '</p></div>';
						} elseif ( 'coupon_unused' === $bulk_action ) {
							echo '<div class="notice notice-success"><p>' . esc_html( $done . ' Einträge wurden als Gutschein nicht genutzt markiert.' ) . '</p></div>';
						}
					}
				} elseif ( $bulk_action ) {
					echo '<div class="notice notice-warning"><p>Bitte zuerst mindestens einen Eintrag auswählen.</p></div>';
				}
			}
		}

		if ( isset( $_POST['sydb_lead_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['sydb_lead_action'] ) );
			$lead_id = absint( $_POST['lead_id'] ?? 0 );
			check_admin_referer( 'sydb_lead_action_' . $lead_id );
			if ( $lead_id && SY_Duftberater::POST_TYPE_LEAD === get_post_type( $lead_id ) ) {
				if ( 'delete' === $action ) {
					wp_delete_post( $lead_id, true );
					echo '<div class="notice notice-success"><p>Eintrag wurde gelöscht.</p></div>';
				} elseif ( 'update' === $action ) {
					$name  = sanitize_text_field( wp_unslash( $_POST['lead_name'] ?? '' ) );
					$email = sanitize_email( wp_unslash( $_POST['lead_email'] ?? '' ) );
					$used  = isset( $_POST['coupon_used'] ) ? 1 : 0;
					update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_name', $name );
					update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_email', $email );
					update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'coupon_used', $used );
					wp_update_post( array( 'ID' => $lead_id, 'post_title' => sprintf( '%s - %s', $name ? $name : 'Eintrag', current_time( 'mysql' ) ) ) );
					echo '<div class="notice notice-success"><p>Eintrag wurde gespeichert.</p></div>';
				}
			}
		}

		$filters = array(
			's'         => isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '',
			'lang'      => isset( $_REQUEST['lang'] ) ? sanitize_key( wp_unslash( $_REQUEST['lang'] ) ) : '',
			'coupon'    => isset( $_REQUEST['coupon'] ) ? sanitize_key( wp_unslash( $_REQUEST['coupon'] ) ) : '',
			'date_from' => isset( $_REQUEST['date_from'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_from'] ) ) : '',
			'date_to'   => isset( $_REQUEST['date_to'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['date_to'] ) ) : '',
		);
		$search    = $filters['s'];
		$all_leads = $this->get_filtered_lead_posts_for_export( $filters );
		$page      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page  = 50;
		$total     = count( $all_leads );
		$pages     = max( 1, (int) ceil( $total / $per_page ) );
		$leads     = array_slice( $all_leads, ( $page - 1 ) * $per_page, $per_page );
		$edit_id   = isset( $_GET['edit_lead'] ) ? absint( $_GET['edit_lead'] ) : 0;
		?>
		<div class="wrap">
			<h1>Duftberater Einträge / PDFs</h1>
			<p>Hier findest du alle Kunden, die ihre Top-3-Empfehlung als PDF angefordert haben. Du kannst Einträge suchen, bearbeiten, löschen, mehrfach auswählen und markieren, ob der 15%-Gutschein genutzt wurde.</p>

			<form method="get" style="margin:16px 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px;align-items:end;max-width:1180px;background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px;">
				<input type="hidden" name="page" value="sy-duftberater-leads" />
				<label><strong>Suche</strong><input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" class="regular-text" placeholder="Name oder E-Mail" /></label>
				<label><strong>Sprache</strong><select name="lang"><option value="">Alle</option><?php foreach ( $this->plugin->get_supported_languages() as $lang_key => $language ) : ?><option value="<?php echo esc_attr( $lang_key ); ?>" <?php selected( $filters['lang'], $lang_key ); ?>><?php echo esc_html( strtoupper( $lang_key ) ); ?></option><?php endforeach; ?></select></label>
				<label><strong>Gutschein</strong><select name="coupon"><option value="">Alle</option><option value="used" <?php selected( $filters['coupon'], 'used' ); ?>>Genutzt</option><option value="unused" <?php selected( $filters['coupon'], 'unused' ); ?>>Nicht genutzt</option></select></label>
				<label><strong>Von</strong><input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>"></label>
				<label><strong>Bis</strong><input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>"></label>
				<p style="margin:0;"><button class="button button-primary">Filtern</button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-leads' ) ); ?>">Reset</a></p>
			</form>
			<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:8px 0 16px;">
				<span><strong><?php echo esc_html( $total ); ?></strong> Einträge gefunden · Seite <?php echo esc_html( $page ); ?> von <?php echo esc_html( $pages ); ?></span>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sydb_export_leads_csv' ); ?>
					<input type="hidden" name="action" value="sydb_export_leads_csv" />
					<?php foreach ( $filters as $filter_key => $filter_value ) : ?><input type="hidden" name="<?php echo esc_attr( $filter_key ); ?>" value="<?php echo esc_attr( $filter_value ); ?>"><?php endforeach; ?>
					<button class="button">Gefilterte Einträge als CSV exportieren</button>
				</form>
			</div>

			<?php if ( $edit_id && SY_Duftberater::POST_TYPE_LEAD === get_post_type( $edit_id ) ) :
				$edit_name  = get_post_meta( $edit_id, SY_Duftberater::META_PREFIX . 'lead_name', true );
				$edit_email = get_post_meta( $edit_id, SY_Duftberater::META_PREFIX . 'lead_email', true );
				$edit_used  = (int) get_post_meta( $edit_id, SY_Duftberater::META_PREFIX . 'coupon_used', true );
			?>
				<div style="background:#fff;border:1px solid #ccd0d4;border-radius:12px;padding:18px;max-width:760px;margin:18px 0;">
					<h2 style="margin-top:0;">Eintrag bearbeiten</h2>
					<form method="post" style="display:grid;gap:12px;">
						<?php wp_nonce_field( 'sydb_lead_action_' . $edit_id ); ?>
						<input type="hidden" name="sydb_lead_action" value="update" />
						<input type="hidden" name="lead_id" value="<?php echo esc_attr( $edit_id ); ?>" />
						<label><strong>Name</strong><br><input type="text" class="regular-text" name="lead_name" value="<?php echo esc_attr( $edit_name ); ?>" /></label>
						<label><strong>E-Mail</strong><br><input type="email" class="regular-text" name="lead_email" value="<?php echo esc_attr( $edit_email ); ?>" /></label>
						<label><input type="checkbox" name="coupon_used" value="1" <?php checked( 1, $edit_used ); ?> /> Gutschein / 15% Rabatt wurde genutzt</label>
						<p><button class="button button-primary">Speichern</button> <a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-leads' ) ); ?>">Abbrechen</a></p>
					</form>
				</div>
			<?php endif; ?>

			<form method="post" id="sydb-leads-bulk-form">
				<?php wp_nonce_field( 'sydb_leads_table_action' ); ?>
				<input type="hidden" name="sydb_leads_table_action" value="1" />
				<?php foreach ( $filters as $filter_key => $filter_value ) : if ( '' !== (string) $filter_value ) : ?><input type="hidden" name="<?php echo esc_attr( $filter_key ); ?>" value="<?php echo esc_attr( $filter_value ); ?>" /><?php endif; endforeach; ?>

				<div class="tablenav top" style="margin:12px 0 10px;">
					<div class="alignleft actions bulkactions" style="display:flex;gap:8px;align-items:center;">
						<select name="bulk_action">
							<option value="">Mehrfachaktion auswählen</option>
							<option value="coupon_used">Gutschein genutzt markieren</option>
							<option value="coupon_unused">Gutschein nicht genutzt markieren</option>
							<option value="delete">Ausgewählte löschen</option>
						</select>
						<button type="submit" class="button action" onclick="var a=this.form.bulk_action.value;if(a==='delete'){return confirm('Ausgewählte Einträge wirklich löschen?');}return true;">Übernehmen</button>
						<span style="color:#646970;">Du kannst mehrere Einträge mit den Checkboxen auswählen.</span>
					</div>
					<br class="clear" />
				</div>

				<table class="widefat striped">
					<thead><tr><th style="width:36px;"><input type="checkbox" id="sydb-select-all-leads" aria-label="Alle Einträge auswählen" /></th><th>Datum</th><th>Name</th><th>E-Mail</th><th>Sprache</th><th>Top 3</th><th>Gutschein genutzt</th><th>PDF</th><th>Aktion</th></tr></thead>
					<tbody>
					<?php if ( empty( $leads ) ) : ?>
						<tr><td colspan="9">Noch keine Einträge vorhanden.</td></tr>
					<?php else : foreach ( $leads as $lead ) :
						$name  = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_name', true );
						$email = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_email', true );
						$lang  = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_lang', true );
						$pdf   = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_pdf_url', true );
						$used  = (int) get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'coupon_used', true );
						$items = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_results', true );
						$items = is_array( $items ) ? $items : array();
						$top   = array();
						foreach ( $items as $item ) { $top[] = sanitize_text_field( $item['title'] ?? '' ); }
					?>
						<tr>
							<th scope="row"><input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( $lead->ID ); ?>" class="sydb-lead-checkbox" aria-label="Eintrag auswählen" /></th>
							<td><?php echo esc_html( get_the_date( 'd.m.Y H:i', $lead ) ); ?></td>
							<td><?php echo esc_html( $name ); ?></td>
							<td><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></td>
							<td><?php echo esc_html( strtoupper( $lang ) ); ?></td>
							<td><?php echo esc_html( implode( ' | ', array_filter( $top ) ) ); ?></td>
							<td><?php echo $used ? '<span style="color:#087c2f;font-weight:700;">Ja</span>' : '<span style="color:#777;">Nein</span>'; ?></td>
							<td><?php if ( $pdf ) : ?><a class="button" href="<?php echo esc_url( $pdf ); ?>" target="_blank" rel="noopener">PDF öffnen</a> <a class="button" href="<?php echo esc_url( $pdf ); ?>" download>Herunterladen</a><?php endif; ?></td>
							<td style="display:flex;gap:6px;align-items:center;">
								<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sy-duftberater-leads&edit_lead=' . $lead->ID ) ); ?>">Bearbeiten</a>
								<button type="submit" name="sydb_single_delete" value="<?php echo esc_attr( $lead->ID ); ?>" class="button button-link-delete" onclick="return confirm('Eintrag wirklich löschen?');">Löschen</button>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</form>
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav-pages" style="margin:16px 0;display:flex;gap:6px;align-items:center;">
					<?php for ( $i = 1; $i <= $pages; $i++ ) : $url = add_query_arg( array_merge( $_GET, array( 'paged' => $i ) ), admin_url( 'admin.php' ) ); ?>
						<?php if ( $i === $page ) : ?><span class="button button-primary" style="pointer-events:none;"><?php echo esc_html( $i ); ?></span><?php else : ?><a class="button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $i ); ?></a><?php endif; ?>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
			<script>
			(function(){
				var all = document.getElementById('sydb-select-all-leads');
				if (!all) { return; }
				var boxes = Array.prototype.slice.call(document.querySelectorAll('.sydb-lead-checkbox'));
				function syncMaster(){
					var checked = boxes.filter(function(box){ return box.checked; }).length;
					all.checked = boxes.length > 0 && checked === boxes.length;
					all.indeterminate = checked > 0 && checked < boxes.length;
				}
				all.addEventListener('change', function(){ boxes.forEach(function(box){ box.checked = all.checked; }); syncMaster(); });
				boxes.forEach(function(box){ box.addEventListener('change', syncMaster); });
				syncMaster();
			})();
			</script>
		</div>
		<?php
	}


	public function render_import_export_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}

		$this->admin_wrap_start( 'SY Duftberater – Import / Export' );
		$status = isset( $_GET['sydb_status'] ) ? sanitize_key( wp_unslash( $_GET['sydb_status'] ) ) : '';
		$count  = isset( $_GET['sydb_count'] ) ? absint( $_GET['sydb_count'] ) : 0;
		$msg    = isset( $_GET['sydb_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sydb_msg'] ) ) : '';
		if ( $status ) {
			$class = 'ok' === $status ? 'notice notice-success' : 'notice notice-error';
			echo '<div class="' . esc_attr( $class ) . '"><p>';
			if ( 'ok' === $status ) {
				echo esc_html( $count . ' Parfüm-Datensätze wurden verarbeitet.' );
			} else {
				echo esc_html( $msg ? $msg : 'Import/Export konnte nicht ausgeführt werden.' );
			}
			echo '</p></div>';
		}
		$preview_key = isset( $_GET['sydb_preview'] ) ? sanitize_key( wp_unslash( $_GET['sydb_preview'] ) ) : '';
		$preview     = $preview_key ? get_transient( 'sydb_import_preview_' . $preview_key ) : false;
		?>
		<?php if ( is_array( $preview ) ) : ?>
			<div class="notice notice-info" style="padding:12px 16px;">
				<p><strong>Import-Vorschau:</strong> <?php echo esc_html( (int) ( $preview['valid'] ?? 0 ) ); ?> gültige Zeilen, <?php echo esc_html( (int) ( $preview['invalid'] ?? 0 ) ); ?> ungültige Zeilen. Es wurde noch nichts importiert.</p>
				<?php if ( ! empty( $preview['sample'] ) ) : ?>
					<table class="widefat striped" style="margin:10px 0;max-width:100%;"><thead><tr><?php foreach ( (array) ( $preview['headers'] ?? array() ) as $header ) : ?><th><?php echo esc_html( $header ); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ( (array) $preview['sample'] as $row ) : ?><tr><?php foreach ( (array) ( $preview['headers'] ?? array() ) as $header ) : ?><td><?php echo esc_html( $row[ $header ] ?? '' ); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;max-width:1180px;margin-top:20px;">
			<div style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:20px;">
				<h2 style="margin-top:0;">Parfüms exportieren</h2>
				<p>Exportiert alle Parfüm-Zuordnungen inklusive mehrsprachiger Titel und Beschreibungen.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px;">
					<?php wp_nonce_field( 'sydb_export_parfums_xlsx' ); ?>
					<input type="hidden" name="action" value="sydb_export_parfums_xlsx" />
					<button type="submit" class="button">Standard-Excel exportieren</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;">
					<?php wp_nonce_field( 'sydb_export_parfums_matrix_xlsx' ); ?>
					<input type="hidden" name="action" value="sydb_export_parfums_matrix_xlsx" />
					<button type="submit" class="button button-primary">Pflegeliste / Matrix exportieren</button>
				</form>
				<p class="description">Die Pflegeliste ist wie deine Excel-Tabelle aufgebaut: pro Merkmal eine eigene Spalte mit 1/0. Sie kann nach dem Bearbeiten wieder importiert werden.</p>
			</div>
			<div style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:20px;">
				<h2 style="margin-top:0;">Parfüms importieren</h2>
				<p>Importiert CSV oder XLSX. Bestehende Parfüms mit gleichem WordPress-Titel werden aktualisiert, neue werden angelegt.</p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sydb_import_parfums_file' ); ?>
					<input type="hidden" name="action" value="sydb_import_parfums_file" />
					<p><input type="file" name="sydb_parfums_file" accept=".csv,.xlsx" required></p>
					<p><label><input type="checkbox" name="sydb_preview_only" value="1" checked> Erst nur Vorschau anzeigen</label></p>
					<p><label><input type="checkbox" name="sydb_delete_existing" value="1"> Vorhandene Parfüms vor Import löschen</label></p>
					<button type="submit" class="button button-primary">Datei prüfen / Import starten</button>
				</form>
				<p class="description">Spalten werden über die Überschriften erkannt. Standard-Export und Matrix/Pflegeliste werden unterstützt. Für sichere Pflege zuerst Vorschau aktiviert lassen.</p>
			</div>
			<div style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:20px;">
				<h2 style="margin-top:0;">Komplett-Backup</h2>
				<p>Exportiert Einstellungen, Fragen und Parfüm-Daten als JSON. Ideal vor größeren Änderungen.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'sydb_export_setup' ); ?>
					<input type="hidden" name="action" value="sydb_export_setup" />
					<button type="submit" class="button button-primary">Setup-Backup herunterladen</button>
				</form>
			</div>
			<div style="background:#fff;border:1px solid #ddd;border-radius:14px;padding:20px;">
				<h2 style="margin-top:0;">Backup wiederherstellen</h2>
				<p>Importiert ein JSON-Backup aus diesem Plugin. Optional können vorhandene Parfüms vorher gelöscht werden.</p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Backup wirklich importieren? Vorher am besten ein aktuelles Backup herunterladen.');">
					<?php wp_nonce_field( 'sydb_import_setup' ); ?>
					<input type="hidden" name="action" value="sydb_import_setup" />
					<p><input type="file" name="sydb_setup_file" accept=".json" required></p>
					<p><label><input type="checkbox" name="sydb_delete_existing" value="1"> Vorhandene Parfüms vor Backup-Import löschen</label></p>
					<button type="submit" class="button">Backup importieren</button>
				</form>
			</div>
		</div>
		<?php
		$this->admin_wrap_end();
	}

	protected function get_parfum_export_headers() {
		return array(
			'post_title', 'title_de', 'title_en', 'title_ar', 'description_de', 'description_en', 'description_ar',
			'geschlecht', 'jahreszeit', 'verwendungsbereich', 'alter', 'duftrichtungen', 'duftnoten',
			'raucher_geeignet', 'produktlink', 'image_url', 'status'
		);
	}

	protected function get_all_parfum_posts_for_export() {
		return get_posts( array(
			'post_type'      => SY_Duftberater::POST_TYPE_PARFUM,
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	protected function get_parfum_export_rows() {
		$rows = array();
		foreach ( $this->get_all_parfum_posts_for_export() as $post ) {
			$title_i18n = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'title_i18n', true );
			$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
			$desc_i18n  = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'description_i18n', true );
			$desc_i18n  = is_array( $desc_i18n ) ? $desc_i18n : array();
			$image_url  = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'image_url', true );
			$rows[] = array(
				'post_title'          => $post->post_title,
				'title_de'            => $title_i18n['de'] ?? $post->post_title,
				'title_en'            => $title_i18n['en'] ?? '',
				'title_ar'            => $title_i18n['ar'] ?? '',
				'description_de'      => $desc_i18n['de'] ?? wp_strip_all_tags( $post->post_content ),
				'description_en'      => $desc_i18n['en'] ?? '',
				'description_ar'      => $desc_i18n['ar'] ?? '',
				'geschlecht'          => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'geschlecht', true ) ),
				'jahreszeit'          => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'jahreszeit', true ) ),
				'verwendungsbereich'  => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'verwendungsbereich', true ) ),
				'alter'               => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'alter', true ) ),
				'duftrichtungen'      => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'duftrichtungen', true ) ),
				'duftnoten'           => implode( ',', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'duftnoten', true ) ),
				'raucher_geeignet'    => (int) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'raucher_geeignet', true ),
				'produktlink'         => get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'produktlink', true ),
				'image_url'           => $image_url ? $image_url : get_the_post_thumbnail_url( $post->ID, 'large' ),
				'status'              => $post->post_status,
			);
		}
		return $rows;
	}


	protected function get_parfum_matrix_columns() {
		return array(
			array( 'header' => 'Nummer', 'type' => 'number' ),
			array( 'header' => 'WordPress-ID', 'type' => 'wp_id' ),
			array( 'header' => 'Parfumname DE', 'type' => 'title', 'lang' => 'de' ),
			array( 'header' => 'Name EN', 'type' => 'title', 'lang' => 'en' ),
			array( 'header' => 'Name AR', 'type' => 'title', 'lang' => 'ar' ),
			array( 'header' => 'Beschreibung DE', 'type' => 'description', 'lang' => 'de' ),
			array( 'header' => 'Beschreibung EN', 'type' => 'description', 'lang' => 'en' ),
			array( 'header' => 'Beschreibung AR', 'type' => 'description', 'lang' => 'ar' ),
			array( 'header' => 'für Herren', 'type' => 'choice', 'field' => 'geschlecht', 'value' => 'herren' ),
			array( 'header' => 'für Damen', 'type' => 'choice', 'field' => 'geschlecht', 'value' => 'damen' ),
			array( 'header' => 'Unisex', 'type' => 'choice', 'field' => 'geschlecht', 'value' => 'unisex' ),
			array( 'header' => 'Sommer', 'type' => 'choice', 'field' => 'jahreszeit', 'value' => 'sommer' ),
			array( 'header' => 'Winter', 'type' => 'choice', 'field' => 'jahreszeit', 'value' => 'winter' ),
			array( 'header' => 'Frühling', 'type' => 'choice', 'field' => 'jahreszeit', 'value' => 'fruehling' ),
			array( 'header' => 'Herbst', 'type' => 'choice', 'field' => 'jahreszeit', 'value' => 'herbst' ),
			array( 'header' => 'Für den Alltag', 'type' => 'derived_any', 'field' => 'verwendungsbereich', 'values' => array( 'zuhause', 'ausgehen', 'arbeit', 'buero', 'grosse_raeume' ) ),
			array( 'header' => 'Für Zuhause', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'zuhause' ),
			array( 'header' => 'Zum Ausgehen / Freizeit', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'ausgehen' ),
			array( 'header' => 'Für die Arbeit', 'type' => 'derived_any', 'field' => 'verwendungsbereich', 'values' => array( 'arbeit', 'buero', 'grosse_raeume' ) ),
			array( 'header' => 'Büroarbeit und geschlossene Räume', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'buero' ),
			array( 'header' => 'Arbeit in großen Räumlichkeiten und Öffentlichkeit', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'grosse_raeume' ),
			array( 'header' => 'Formell und wichtige Anlässe', 'type' => 'derived_any', 'field' => 'verwendungsbereich', 'values' => array( 'meeting', 'date', 'private_anlaesse' ) ),
			array( 'header' => 'Meeting', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'meeting' ),
			array( 'header' => 'date', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'date' ),
			array( 'header' => 'private (feierliche) Anlässe', 'type' => 'choice', 'field' => 'verwendungsbereich', 'value' => 'private_anlaesse' ),
			array( 'header' => 'Ja / Nein', 'type' => 'smoker' ),
			array( 'header' => 'Zitrisch', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'zitrisch' ),
			array( 'header' => 'Blumig', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'blumig' ),
			array( 'header' => 'Fruchtig', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'fruchtig' ),
			array( 'header' => 'Grün', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'gruen' ),
			array( 'header' => 'Oud', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'oud' ),
			array( 'header' => 'Würzig', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'wuerzig' ),
			array( 'header' => 'Holzig', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'holzig' ),
			array( 'header' => 'Amber', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'amber' ),
			array( 'header' => 'Gourmand', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'gourmand' ),
			array( 'header' => 'Aquatisch', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'aquatisch' ),
			array( 'header' => 'Ledrig', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'ledrig' ),
			array( 'header' => 'Moschus', 'type' => 'choice', 'field' => 'duftrichtungen', 'value' => 'moschus' ),
			array( 'header' => '10 bis 20', 'type' => 'choice', 'field' => 'alter', 'value' => '10_20' ),
			array( 'header' => '20 bis 30', 'type' => 'choice', 'field' => 'alter', 'value' => '20_30' ),
			array( 'header' => '30 bis 40', 'type' => 'choice', 'field' => 'alter', 'value' => '30_40' ),
			array( 'header' => 'über 40', 'type' => 'choice', 'field' => 'alter', 'value' => 'ueber_40' ),
			array( 'header' => 'Zitrusnoten (Bergamotte, Zitrone, Limette, Orange, Grapefruit)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'zitrusnoten' ),
			array( 'header' => 'Blumige Noten (Rose, Jasmin, Ylang-Ylang, Iris, Veilchen, Tuberose)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'blumige_noten' ),
			array( 'header' => 'Fruchtige Noten (Apfel, Pfirsich, Birne, Beeren, Mango)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'fruchtige_noten' ),
			array( 'header' => 'Holznoten (Sandelholz, Zedernholz, Vetiver, Guajakholz)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'holznoten' ),
			array( 'header' => 'Gewürznoten (Zimt, Kardamom, Pfeffer, Nelke, Muskat)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'gewuerznoten' ),
			array( 'header' => 'Harze / Balsame (Benzoe, Myrrhe, Weihrauch, Labdanum)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'harze_balsame' ),
			array( 'header' => 'Süße / Gourmand Noten (Vanille, Tonkabohne, Karamell, Kakao)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'suesse_gourmand' ),
			array( 'header' => 'Erdige Noten (Patchouli, Moos, Erde)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'erdige_noten' ),
			array( 'header' => 'Aromatische Noten (Lavendel, Rosmarin, Minze, Basilikum)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'aromatische_noten' ),
			array( 'header' => 'Animalische Noten (Moschus, Ambergris, Leder)', 'type' => 'choice', 'field' => 'duftnoten', 'value' => 'animalische_noten' ),
			array( 'header' => 'Produktlink', 'type' => 'meta', 'field' => 'produktlink' ),
			array( 'header' => 'Bild-URL', 'type' => 'image' ),
			array( 'header' => 'Status', 'type' => 'status' ),
		);
	}

	protected function get_parfum_number_from_title( $title ) {
		if ( preg_match( '/(\d+)/', (string) $title, $m ) ) {
			return $m[1];
		}
		return '';
	}

	protected function get_parfum_matrix_export_rows() {
		$rows    = array();
		$columns = $this->get_parfum_matrix_columns();
		foreach ( $this->get_all_parfum_posts_for_export() as $post ) {
			$title_i18n = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'title_i18n', true );
			$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
			$desc_i18n = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'description_i18n', true );
			$desc_i18n = is_array( $desc_i18n ) ? $desc_i18n : array();
			$row = array();
			foreach ( $columns as $column ) {
				$header = $column['header'];
				$type   = $column['type'];
				if ( 'number' === $type ) {
					$row[ $header ] = $this->get_parfum_number_from_title( $post->post_title );
				} elseif ( 'wp_id' === $type ) {
					$row[ $header ] = $post->ID;
				} elseif ( 'title' === $type ) {
					$lang = $column['lang'];
					$row[ $header ] = 'de' === $lang ? ( $title_i18n['de'] ?? $post->post_title ) : ( $title_i18n[ $lang ] ?? '' );
				} elseif ( 'description' === $type ) {
					$lang = $column['lang'];
					$row[ $header ] = 'de' === $lang ? ( $desc_i18n['de'] ?? wp_strip_all_tags( $post->post_content ) ) : ( $desc_i18n[ $lang ] ?? '' );
				} elseif ( 'choice' === $type ) {
					$values = array_map( 'sanitize_key', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . $column['field'], true ) );
					$row[ $header ] = in_array( $column['value'], $values, true ) ? 1 : 0;
				} elseif ( 'derived_any' === $type ) {
					$values = array_map( 'sanitize_key', (array) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . $column['field'], true ) );
					$row[ $header ] = array_intersect( $values, (array) $column['values'] ) ? 1 : 0;
				} elseif ( 'smoker' === $type ) {
					$row[ $header ] = (int) get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'raucher_geeignet', true );
				} elseif ( 'meta' === $type ) {
					$row[ $header ] = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . $column['field'], true );
				} elseif ( 'image' === $type ) {
					$image = get_post_meta( $post->ID, SY_Duftberater::META_PREFIX . 'image_url', true );
					$row[ $header ] = $image ? $image : get_the_post_thumbnail_url( $post->ID, 'large' );
				} elseif ( 'status' === $type ) {
					$row[ $header ] = $post->post_status;
				}
			}
			$rows[] = $row;
		}
		return $rows;
	}

	public function handle_export_parfums_matrix_xlsx() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_export_parfums_matrix_xlsx' );
		$columns = $this->get_parfum_matrix_columns();
		$headers = wp_list_pluck( $columns, 'header' );
		$rows    = $this->get_parfum_matrix_export_rows();
		if ( class_exists( 'ZipArchive' ) ) {
			$this->download_xlsx( $headers, $rows, 'sy-duftberater-pflegeliste-matrix.xlsx' );
		} else {
			$this->download_csv( $headers, $rows, 'sy-duftberater-pflegeliste-matrix.csv' );
		}
		exit;
	}

	protected function normalize_import_header_key( $header ) {
		$header = remove_accents( (string) $header );
		$header = strtolower( $header );
		$header = str_replace( array( '/', '+', '&' ), ' ', $header );
		$header = preg_replace( '/[^a-z0-9]+/', '_', $header );
		$header = trim( $header, '_' );
		return sanitize_key( $header );
	}

	protected function is_truthy_import_value( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, array( '1', 'ja', 'yes', 'true', 'wahr', 'x', '✓', 'ok' ), true );
	}

	protected function get_matrix_values_from_import_row( $data, $field ) {
		$values = array();
		foreach ( $this->get_parfum_matrix_columns() as $column ) {
			if ( 'choice' !== ( $column['type'] ?? '' ) || ( $column['field'] ?? '' ) !== $field ) {
				continue;
			}
			$key = $this->normalize_import_header_key( $column['header'] );
			if ( isset( $data[ $key ] ) && $this->is_truthy_import_value( $data[ $key ] ) ) {
				$values[] = sanitize_key( $column['value'] );
			}
		}
		return array_values( array_unique( $values ) );
	}

	public function handle_export_parfums_xlsx() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_export_parfums_xlsx' );

		$headers = $this->get_parfum_export_headers();
		$rows    = $this->get_parfum_export_rows();

		if ( class_exists( 'ZipArchive' ) ) {
			$this->download_xlsx( $headers, $rows, 'sy-duftberater-parfums.xlsx' );
		} else {
			$this->download_csv( $headers, $rows, 'sy-duftberater-parfums.csv' );
		}
		exit;
	}

	protected function download_csv( $headers, $rows, $filename ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		$out = fopen( 'php://output', 'w' );
		fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		fputcsv( $out, $headers, ';' );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $headers as $header ) {
				$line[] = $row[ $header ] ?? '';
			}
			fputcsv( $out, $line, ';' );
		}
		fclose( $out );
	}

	protected function xlsx_col_name( $index ) {
		$name = '';
		$index++;
		while ( $index > 0 ) {
			$mod   = ( $index - 1 ) % 26;
			$name  = chr( 65 + $mod ) . $name;
			$index = (int) floor( ( $index - $mod ) / 26 );
		}
		return $name;
	}

	protected function download_xlsx( $headers, $rows, $filename ) {
		$tmp = wp_tempnam( $filename );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			$this->download_csv( $headers, $rows, str_replace( '.xlsx', '.csv', $filename ) );
			return;
		}

		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Parfums" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );

		$sheet_rows = array_merge( array( $headers ), array_map( function( $row ) use ( $headers ) {
			$out = array();
			foreach ( $headers as $header ) {
				$out[] = $row[ $header ] ?? '';
			}
			return $out;
		}, $rows ) );
		$xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
		foreach ( $sheet_rows as $r => $row ) {
			$xml .= '<row r="' . ( $r + 1 ) . '">';
			foreach ( $row as $c => $value ) {
				$cell = $this->xlsx_col_name( $c ) . ( $r + 1 );
				$xml .= '<c r="' . esc_attr( $cell ) . '" t="inlineStr"><is><t>' . htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</t></is></c>';
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData></worksheet>';
		$zip->addFromString( 'xl/worksheets/sheet1.xml', $xml );
		$zip->close();

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		readfile( $tmp );
		@unlink( $tmp );
	}

	public function handle_import_parfums_file() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_import_parfums_file' );

		if ( empty( $_FILES['sydb_parfums_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['sydb_parfums_file']['tmp_name'] ) ) {
			$this->redirect_import_export( 'error', 0, 'Keine Datei hochgeladen.' );
		}

		$file = $_FILES['sydb_parfums_file'];
		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		$rows = array();

		if ( 'xlsx' === $ext ) {
			$rows = $this->read_xlsx_rows( $file['tmp_name'] );
		} else {
			$rows = $this->read_csv_rows( $file['tmp_name'] );
		}

		$rows = $this->normalize_parfum_import_rows( $rows );

		if ( empty( $rows ) || count( $rows ) < 2 ) {
			$this->redirect_import_export( 'error', 0, 'Die Datei enthält keine importierbaren Zeilen.' );
		}

		$headers_preview = array_map( array( $this, 'normalize_import_header_key' ), (array) $rows[0] );
		if ( ! empty( $_POST['sydb_preview_only'] ) ) {
			$valid = 0;
			$invalid = 0;
			$sample = array();
			foreach ( array_slice( $rows, 1 ) as $preview_row ) {
				$data = array();
				foreach ( $headers_preview as $i => $key ) {
					if ( '' !== $key ) {
						$data[ $key ] = isset( $preview_row[ $i ] ) ? trim( (string) $preview_row[ $i ] ) : '';
					}
				}
				$title = sanitize_text_field( $data['post_title'] ?? ( $data['title_de'] ?? ( $data['parfumname_de'] ?? '' ) ) );
				if ( '' === $title && ! empty( $data['nummer'] ) ) {
					$title = 'Duft Nr. ' . sanitize_text_field( $data['nummer'] );
				}
				if ( '' === $title ) {
					$invalid++;
				} else {
					$valid++;
					if ( count( $sample ) < 5 ) {
						$sample[] = $data;
					}
				}
			}
			$key = wp_generate_password( 12, false, false );
			set_transient( 'sydb_import_preview_' . $key, array( 'headers' => $headers_preview, 'valid' => $valid, 'invalid' => $invalid, 'sample' => $sample ), 10 * MINUTE_IN_SECONDS );
			wp_safe_redirect( add_query_arg( array( 'page' => 'sy-duftberater-import-export', 'sydb_preview' => $key ), admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( ! empty( $_POST['sydb_delete_existing'] ) ) {
			foreach ( $this->get_all_parfum_posts_for_export() as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}

		$headers = array_map( array( $this, 'normalize_import_header_key' ), array_shift( $rows ) );
		$count   = 0;
		foreach ( $rows as $row ) {
			$data = array();
			foreach ( $headers as $i => $key ) {
				if ( '' !== $key ) {
					$data[ $key ] = isset( $row[ $i ] ) ? trim( (string) $row[ $i ] ) : '';
				}
			}
			if ( $this->import_parfum_row( $data ) ) {
				$count++;
			}
		}

		$this->redirect_import_export( 'ok', $count );
	}

	protected function redirect_import_export( $status, $count = 0, $msg = '' ) {
		$url = add_query_arg( array(
			'page'        => 'sy-duftberater-import-export',
			'sydb_status' => $status,
			'sydb_count'  => absint( $count ),
			'sydb_msg'    => rawurlencode( $msg ),
		), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	protected function read_csv_rows( $path ) {
		$rows   = array();
		$handle = fopen( $path, 'r' );
		if ( ! $handle ) {
			return $rows;
		}
		$first = fgets( $handle );
		if ( false === $first ) {
			fclose( $handle );
			return $rows;
		}
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delimiter = substr_count( $first, ';' ) >= substr_count( $first, ',' ) ? ';' : ',';
		$rows[] = str_getcsv( $first, $delimiter );
		while ( false !== ( $row = fgetcsv( $handle, 0, $delimiter ) ) ) {
			$rows[] = $row;
		}
		fclose( $handle );
		return $rows;
	}


	protected function normalize_parfum_import_rows( $rows ) {
		$rows = array_values( array_filter( (array) $rows, function( $row ) {
			$row = array_map( 'trim', array_map( 'strval', (array) $row ) );
			return '' !== implode( '', $row );
		} ) );
		if ( empty( $rows ) ) {
			return array();
		}

		$header_index = 0;
		foreach ( $rows as $idx => $row ) {
			$normalized = array_map( array( $this, 'normalize_import_header_key' ), (array) $row );
			if ( in_array( 'nummer', $normalized, true ) || in_array( 'post_title', $normalized, true ) || in_array( 'parfumname_de', $normalized, true ) || in_array( 'title_de', $normalized, true ) ) {
				$header_index = $idx;
				break;
			}
		}
		if ( $header_index > 0 ) {
			$rows = array_slice( $rows, $header_index );
		}

		// Unterstützt die vorhandene Alowidat-Matrix mit zweizeiligem Kopf:
		// Zeile 1: Kategorien, Zeile 2: konkrete Optionen.
		if ( isset( $rows[1] ) ) {
			$first  = array_map( array( $this, 'normalize_import_header_key' ), (array) $rows[0] );
			$second = array_map( array( $this, 'normalize_import_header_key' ), (array) $rows[1] );
			$looks_like_two_line_matrix = in_array( 'nummer', $first, true ) && ( in_array( 'fur_herren', $second, true ) || in_array( 'sommer', $second, true ) || in_array( 'zitrisch', $second, true ) );
			if ( $looks_like_two_line_matrix ) {
				$max = max( count( $rows[0] ), count( $rows[1] ) );
				$merged = array();
				for ( $i = 0; $i < $max; $i++ ) {
					$second_val = isset( $rows[1][ $i ] ) ? trim( (string) $rows[1][ $i ] ) : '';
					$first_val  = isset( $rows[0][ $i ] ) ? trim( (string) $rows[0][ $i ] ) : '';
					$merged[ $i ] = '' !== $second_val ? $second_val : $first_val;
				}
				$rows = array_merge( array( $merged ), array_slice( $rows, 2 ) );
			}
		}

		return $rows;
	}

	protected function read_xlsx_rows( $path ) {
		$rows = array();
		if ( ! class_exists( 'ZipArchive' ) ) {
			return $rows;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return $rows;
		}
		$shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$shared     = $shared_xml ? $this->extract_xlsx_shared_strings( $shared_xml ) : array();
		$sheet_xml  = $zip->getFromName( 'xl/worksheets/sheet1.xml' );
		if ( ! $sheet_xml ) {
			// Fallback für XLSX-Dateien, bei denen das erste Tabellenblatt anders benannt/referenziert ist.
			for ( $i = 1; $i <= 10; $i++ ) {
				$try = $zip->getFromName( 'xl/worksheets/sheet' . $i . '.xml' );
				if ( $try ) {
					$sheet_xml = $try;
					break;
				}
			}
		}
		$zip->close();
		if ( ! $sheet_xml ) {
			return $rows;
		}
		return $this->extract_xlsx_sheet_rows( $sheet_xml, $shared );
	}

	protected function extract_xlsx_shared_strings( $shared_xml ) {
		$shared = array();
		if ( function_exists( 'simplexml_load_string' ) ) {
			$xml = @simplexml_load_string( $shared_xml );
			if ( $xml ) {
				$items = $xml->xpath( '//*[local-name()="si"]' );
				if ( is_array( $items ) ) {
					foreach ( $items as $si ) {
						$texts = $si->xpath( './/*[local-name()="t"]' );
						$text  = '';
						if ( is_array( $texts ) ) {
							foreach ( $texts as $t ) {
								$text .= (string) $t;
							}
						}
						$shared[] = $text;
					}
				}
			}
		}

		if ( empty( $shared ) && preg_match_all( '/<(?:[A-Za-z0-9_]+:)?si\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?si>/s', (string) $shared_xml, $matches ) ) {
			foreach ( $matches[1] as $si_xml ) {
				$text = '';
				if ( preg_match_all( '/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/s', $si_xml, $texts ) ) {
					foreach ( $texts[1] as $t ) {
						$text .= $this->decode_xlsx_xml_text( $t );
					}
				}
				$shared[] = $text;
			}
		}
		return $shared;
	}

	protected function extract_xlsx_sheet_rows( $sheet_xml, $shared ) {
		$rows = array();
		if ( function_exists( 'simplexml_load_string' ) ) {
			$xml = @simplexml_load_string( $sheet_xml );
			if ( $xml ) {
				$row_nodes = $xml->xpath( '//*[local-name()="sheetData"]/*[local-name()="row"]' );
				if ( is_array( $row_nodes ) ) {
					foreach ( $row_nodes as $row_xml ) {
						$row = array();
						$cells = $row_xml->xpath( './*[local-name()="c"]' );
						if ( ! is_array( $cells ) ) {
							continue;
						}
						foreach ( $cells as $cell ) {
							$ref = (string) $cell['r'];
							$col = $this->xlsx_col_index_from_ref( $ref );
							$row[ $col ] = $this->extract_xlsx_cell_value( $cell, $shared );
						}
						if ( ! empty( $row ) ) {
							ksort( $row );
							$max_col = max( array_keys( $row ) );
							$filled = array_fill( 0, $max_col + 1, '' );
							foreach ( $row as $col_index => $cell_value ) {
								$filled[ $col_index ] = $cell_value;
							}
							$rows[] = $filled;
						}
					}
				}
			}
		}

		if ( ! empty( $rows ) ) {
			return $rows;
		}

		// Fallback ohne SimpleXML: wichtig bei Hostings, auf denen die PHP-Extension deaktiviert ist.
		if ( preg_match_all( '/<(?:[A-Za-z0-9_]+:)?row\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?row>/s', (string) $sheet_xml, $row_matches ) ) {
			foreach ( $row_matches[1] as $row_xml ) {
				$row = array();
				if ( preg_match_all( '/<(?:[A-Za-z0-9_]+:)?c\b([^>]*)>(.*?)<\/(?:[A-Za-z0-9_]+:)?c>/s', $row_xml, $cell_matches, PREG_SET_ORDER ) ) {
					foreach ( $cell_matches as $cell_match ) {
						$attrs = $cell_match[1];
						$body  = $cell_match[2];
						$ref   = '';
						$type  = '';
						if ( preg_match( '/\br="([^"]+)"/', $attrs, $m ) ) {
							$ref = $m[1];
						}
						if ( preg_match( '/\bt="([^"]+)"/', $attrs, $m ) ) {
							$type = $m[1];
						}
						if ( '' === $ref ) {
							continue;
						}
						$col = $this->xlsx_col_index_from_ref( $ref );
						$value = '';
						if ( 's' === $type && preg_match( '/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/s', $body, $m ) ) {
							$idx = (int) trim( $m[1] );
							$value = $shared[ $idx ] ?? '';
						} elseif ( 'inlineStr' === $type && preg_match_all( '/<(?:[A-Za-z0-9_]+:)?t\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?t>/s', $body, $texts ) ) {
							foreach ( $texts[1] as $t ) {
								$value .= $this->decode_xlsx_xml_text( $t );
							}
						} elseif ( preg_match( '/<(?:[A-Za-z0-9_]+:)?v\b[^>]*>(.*?)<\/(?:[A-Za-z0-9_]+:)?v>/s', $body, $m ) ) {
							$value = $this->decode_xlsx_xml_text( $m[1] );
						}
						$row[ $col ] = $value;
					}
				}
				if ( ! empty( $row ) ) {
					ksort( $row );
					$max_col = max( array_keys( $row ) );
					$filled = array_fill( 0, $max_col + 1, '' );
					foreach ( $row as $col_index => $cell_value ) {
						$filled[ $col_index ] = $cell_value;
					}
					$rows[] = $filled;
				}
			}
		}
		return $rows;
	}

	protected function extract_xlsx_cell_value( $cell, $shared ) {
		$type  = (string) $cell['t'];
		$value = '';
		if ( 's' === $type ) {
			$v = $cell->xpath( './*[local-name()="v"]' );
			$idx = ( is_array( $v ) && isset( $v[0] ) ) ? (int) $v[0] : -1;
			$value = $shared[ $idx ] ?? '';
		} elseif ( 'inlineStr' === $type ) {
			$texts = $cell->xpath( './/*[local-name()="t"]' );
			if ( is_array( $texts ) ) {
				foreach ( $texts as $t ) {
					$value .= (string) $t;
				}
			}
		} else {
			$v = $cell->xpath( './*[local-name()="v"]' );
			$value = ( is_array( $v ) && isset( $v[0] ) ) ? (string) $v[0] : '';
		}
		return $value;
	}

	protected function decode_xlsx_xml_text( $text ) {
		return html_entity_decode( (string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	protected function xlsx_col_index_from_ref( $ref ) {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $ref ) );
		$index = 0;
		for ( $i = 0; $i < strlen( $letters ); $i++ ) {
			$index = $index * 26 + ( ord( $letters[ $i ] ) - 64 );
		}
		return max( 0, $index - 1 );
	}

	protected function parse_list_value( $value ) {
		$value = str_replace( array( '|', ';' ), ',', (string) $value );
		$parts = array_map( 'trim', explode( ',', $value ) );
		$parts = array_filter( array_map( 'sanitize_key', $parts ) );
		return array_values( array_unique( $parts ) );
	}

	protected function import_parfum_row( $data ) {
		$post_title = $data['post_title'] ?? ( $data['title_de'] ?? ( $data['parfumname_de'] ?? '' ) );
		if ( '' === (string) $post_title && ! empty( $data['nummer'] ) ) {
			$post_title = 'Duft Nr. ' . sanitize_text_field( $data['nummer'] );
		}
		$post_title = sanitize_text_field( $post_title );
		if ( '' === $post_title ) {
			return false;
		}
		$status = in_array( $data['status'] ?? 'publish', array( 'publish', 'draft', 'private' ), true ) ? $data['status'] : 'publish';

		$wp_id    = absint( $data['wordpress_id'] ?? ( $data['wp_id'] ?? 0 ) );
		$existing = $wp_id && SY_Duftberater::POST_TYPE_PARFUM === get_post_type( $wp_id ) ? get_post( $wp_id ) : get_page_by_title( $post_title, OBJECT, SY_Duftberater::POST_TYPE_PARFUM );
		$description_de = $data['description_de'] ?? ( $data['beschreibung_de'] ?? '' );
		$postarr = array(
			'post_type'    => SY_Duftberater::POST_TYPE_PARFUM,
			'post_title'   => $post_title,
			'post_content' => sanitize_textarea_field( $description_de ),
			'post_status'  => $status,
		);
		if ( $existing ) {
			$postarr['ID'] = $existing->ID;
			$post_id = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return false;
		}

		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'title_i18n', array(
			'de' => sanitize_text_field( $data['title_de'] ?? ( $data['parfumname_de'] ?? $post_title ) ),
			'en' => sanitize_text_field( $data['title_en'] ?? ( $data['name_en'] ?? '' ) ),
			'ar' => sanitize_text_field( $data['title_ar'] ?? ( $data['name_ar'] ?? '' ) ),
		) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'description_i18n', array(
			'de' => sanitize_textarea_field( $description_de ),
			'en' => sanitize_textarea_field( $data['description_en'] ?? ( $data['beschreibung_en'] ?? '' ) ),
			'ar' => sanitize_textarea_field( $data['description_ar'] ?? ( $data['beschreibung_ar'] ?? '' ) ),
		) );
		foreach ( array( 'geschlecht', 'jahreszeit', 'verwendungsbereich', 'alter', 'duftrichtungen', 'duftnoten' ) as $field ) {
			$values = isset( $data[ $field ] ) && '' !== (string) $data[ $field ] ? $this->parse_list_value( $data[ $field ] ) : $this->get_matrix_values_from_import_row( $data, $field );
			update_post_meta( $post_id, SY_Duftberater::META_PREFIX . $field, $values );
		}
		$smoker = $data['raucher_geeignet'] ?? ( $data['ja_nein'] ?? '' );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'raucher_geeignet', $this->is_truthy_import_value( $smoker ) ? 1 : 0 );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'produktlink', esc_url_raw( $data['produktlink'] ?? '' ) );
		update_post_meta( $post_id, SY_Duftberater::META_PREFIX . 'image_url', esc_url_raw( $data['image_url'] ?? ( $data['bild_url'] ?? '' ) ) );
		return true;
	}


	public function handle_export_setup() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_export_setup' );
		$data = array(
			'exported_at' => current_time( 'mysql' ),
			'plugin'      => 'SY Duftberater',
			'build'       => defined( 'SY_DUFTBERATER_VERSION' ) ? SY_DUFTBERATER_VERSION : '',
			'settings'    => $this->get_settings(),
			'questions'   => $this->plugin->get_questions(),
			'parfums'     => $this->get_parfum_export_rows(),
		);
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="sy-duftberater-backup-' . gmdate( 'Y-m-d-H-i' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	public function handle_import_setup() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_import_setup' );
		if ( empty( $_FILES['sydb_setup_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['sydb_setup_file']['tmp_name'] ) ) {
			$this->redirect_import_export( 'error', 0, 'Keine Backup-Datei hochgeladen.' );
		}
		$raw = file_get_contents( $_FILES['sydb_setup_file']['tmp_name'] );
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			$this->redirect_import_export( 'error', 0, 'Backup-Datei ist kein gültiges JSON.' );
		}
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			update_option( SY_Duftberater::OPTION_SETTINGS, $this->sanitize_settings( $data['settings'] ) );
		}
		if ( isset( $data['questions'] ) && is_array( $data['questions'] ) ) {
			$this->plugin->update_questions( $data['questions'] );
		}
		if ( ! empty( $_POST['sydb_delete_existing'] ) ) {
			foreach ( $this->get_all_parfum_posts_for_export() as $post ) {
				wp_delete_post( $post->ID, true );
			}
		}
		$count = 0;
		if ( isset( $data['parfums'] ) && is_array( $data['parfums'] ) ) {
			foreach ( $data['parfums'] as $row ) {
				if ( is_array( $row ) && $this->import_parfum_row( $row ) ) {
					$count++;
				}
			}
		}
		$this->redirect_import_export( 'ok', $count );
	}

	protected function get_filtered_lead_posts_for_export( $filters ) {
		$args = array(
			'post_type'      => SY_Duftberater::POST_TYPE_LEAD,
			'post_status'    => 'private',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$date_query = array();
		if ( ! empty( $filters['date_from'] ) ) {
			$date_query['after'] = sanitize_text_field( $filters['date_from'] );
		}
		if ( ! empty( $filters['date_to'] ) ) {
			$date_query['before'] = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
			$date_query['inclusive'] = true;
		}
		if ( ! empty( $date_query ) ) {
			$args['date_query'] = array( $date_query );
		}
		$meta_query = array( 'relation' => 'AND' );
		if ( ! empty( $filters['lang'] ) ) {
			$meta_query[] = array( 'key' => SY_Duftberater::META_PREFIX . 'lead_lang', 'value' => sanitize_key( $filters['lang'] ) );
		}
		if ( isset( $filters['coupon'] ) && '' !== $filters['coupon'] ) {
			$meta_query[] = array( 'key' => SY_Duftberater::META_PREFIX . 'coupon_used', 'value' => 'used' === $filters['coupon'] ? '1' : '0' );
		}
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}
		$leads = get_posts( $args );
		if ( ! empty( $filters['s'] ) ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['s'] ) : strtolower( $filters['s'] );
			$leads = array_values( array_filter( $leads, function( $lead ) use ( $needle ) {
				$name  = (string) get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_name', true );
				$email = (string) get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_email', true );
				$title = (string) $lead->post_title;
				$haystack = $name . ' ' . $email . ' ' . $title;
				$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
				return false !== strpos( $haystack, $needle );
			} ) );
		}
		return $leads;
	}

	public function handle_export_leads_csv() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			wp_die( 'Keine Berechtigung.' );
		}
		check_admin_referer( 'sydb_export_leads_csv' );
		$filters = array(
			's'         => sanitize_text_field( wp_unslash( $_POST['s'] ?? '' ) ),
			'lang'      => sanitize_key( wp_unslash( $_POST['lang'] ?? '' ) ),
			'coupon'    => sanitize_key( wp_unslash( $_POST['coupon'] ?? '' ) ),
			'date_from' => sanitize_text_field( wp_unslash( $_POST['date_from'] ?? '' ) ),
			'date_to'   => sanitize_text_field( wp_unslash( $_POST['date_to'] ?? '' ) ),
		);
		$headers = array( 'datum', 'name', 'email', 'sprache', 'top_3', 'gutschein_genutzt', 'pdf_url' );
		$rows = array();
		foreach ( $this->get_filtered_lead_posts_for_export( $filters ) as $lead ) {
			$items = get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_results', true );
			$items = is_array( $items ) ? $items : array();
			$top = array();
			foreach ( $items as $item ) {
				$top[] = sanitize_text_field( $item['title'] ?? '' );
			}
			$rows[] = array(
				'datum'              => get_the_date( 'Y-m-d H:i:s', $lead ),
				'name'               => get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_name', true ),
				'email'              => get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_email', true ),
				'sprache'            => get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_lang', true ),
				'top_3'              => implode( ' | ', array_filter( $top ) ),
				'gutschein_genutzt'  => (int) get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'coupon_used', true ) ? 'ja' : 'nein',
				'pdf_url'            => get_post_meta( $lead->ID, SY_Duftberater::META_PREFIX . 'lead_pdf_url', true ),
			);
		}
		$this->download_csv( $headers, $rows, 'sy-duftberater-leads.csv' );
		exit;
	}


	protected function render_settings_tab_button( $target, $label, $active = false ) {
		echo '<button type="button" class="sydb-settings-tab' . ( $active ? ' is-active' : '' ) . '" data-target="' . esc_attr( $target ) . '">' . esc_html( $label ) . '</button>';
	}

	protected function render_settings_panel_start( $id, $active = false ) {
		echo '<section id="' . esc_attr( $id ) . '" class="sydb-settings-panel' . ( $active ? ' is-active' : '' ) . '"><table class="form-table" role="presentation">';
	}

	protected function render_settings_panel_end() {
		echo '</table></section>';
	}

	protected function render_settings_row( $label, $callback ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		call_user_func( $callback );
		echo '</td></tr>';
	}

	public function render_settings_page() {
		if ( ! current_user_can( SY_Duftberater::CAP_MANAGE ) ) {
			return;
		}

		$this->admin_wrap_start( 'SY Duftberater – Einstellungen' );
		wp_enqueue_media();
		$settings = $this->get_settings();
		?>
		<style>
			.sydb-settings-shell{max-width:1180px;background:#fff;border:1px solid #dcdcde;border-radius:16px;margin-top:20px;overflow:hidden}.sydb-settings-tabs{display:flex;gap:0;flex-wrap:wrap;background:#f6f7f7;border-bottom:1px solid #dcdcde}.sydb-settings-tab{border:0;background:transparent;padding:13px 16px;cursor:pointer;font-weight:700;color:#50575e}.sydb-settings-tab.is-active{background:#fff;color:#1d2327;box-shadow:inset 0 -3px #2271b1}.sydb-settings-body{padding:22px}.sydb-settings-panel{display:none}.sydb-settings-panel.is-active{display:block}.sydb-brand-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}.sydb-brand-grid label strong{display:block;margin-bottom:5px}.sydb-settings-panel .form-table{margin-top:0}.sydb-settings-panel .form-table th{width:240px}
		</style>
		<form method="post" action="options.php" class="sydb-settings-shell">
			<?php settings_fields( 'sy_duftberater_settings_group' ); ?>
			<div class="sydb-settings-tabs">
				<?php $this->render_settings_tab_button( 'sydb-tab-general', 'Allgemein', true ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-design', 'Design' ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-languages', 'Sprachen' ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-pdf-mail', 'PDF & E-Mail' ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-audio', 'Audio' ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-texts', 'Frontend-Texte' ); ?>
				<?php $this->render_settings_tab_button( 'sydb-tab-advanced', 'Erweitert' ); ?>
			</div>
			<div class="sydb-settings-body">
				<?php
				$this->render_settings_panel_start( 'sydb-tab-general', true );
				$this->render_settings_row( 'Anzahl Ergebnisse', array( $this, 'render_result_count_field' ) );
				$this->render_settings_row( 'Button-Text Start', array( $this, 'render_start_button_field' ) );
				$this->render_settings_row( 'Standardsprache der Startseite', array( $this, 'render_default_language_field' ) );
				$this->render_settings_row( 'Layout / Vollbreite', array( $this, 'render_layout_options_field' ) );
				$this->render_settings_panel_end();

				$this->render_settings_panel_start( 'sydb-tab-design' );
				$this->render_settings_row( 'Primärfarbe / Akzentfarbe', array( $this, 'render_accent_color_field' ) );
				$this->render_settings_row( 'Frontend-Logo-Link', array( $this, 'render_frontend_logo_link_field' ) );
				$this->render_settings_row( 'Frontend-Farbmodus', array( $this, 'render_frontend_theme_field' ) );
				$this->render_settings_panel_end();

				$this->render_settings_panel_start( 'sydb-tab-languages' );
				$this->render_settings_row( 'Sprachbilder / Flaggen', array( $this, 'render_language_images_field' ) );
				$this->render_settings_panel_end();

				$this->render_settings_panel_start( 'sydb-tab-pdf-mail' );
				$this->render_settings_row( 'PDF-Endvorlage je Sprache', array( $this, 'render_pdf_templates_field' ) );
				$this->render_settings_row( 'Automatischer PDF-Download am Ende', array( $this, 'render_auto_download_field' ) );
				$this->render_settings_panel_end();


				$this->render_settings_panel_start( 'sydb-tab-audio' );
				$this->render_settings_row( 'Ton global deaktivieren', array( $this, 'render_audio_disabled_field' ) );
				$this->render_settings_row( 'Klickton MP3', array( $this, 'render_click_sound_field' ) );
				$this->render_settings_row( 'Begrüßung MP3 je Sprache', array( $this, 'render_welcome_audio_field' ) );
				$this->render_settings_row( 'Ergebnisseite MP3 je Sprache', array( $this, 'render_result_audio_field' ) );
				$this->render_settings_row( 'Abschluss-Popup MP3 je Sprache', array( $this, 'render_completion_audio_field' ) );
				$this->render_settings_panel_end();

				$this->render_settings_panel_start( 'sydb-tab-texts' );
				$this->render_settings_row( 'Mehrsprachige Frontend-Texte', array( $this, 'render_ui_i18n_field' ) );
				$this->render_settings_panel_end();

				$this->render_settings_panel_start( 'sydb-tab-advanced' );
				$this->render_settings_row( 'Beispiel-Daten', array( $this, 'render_example_data_field' ) );
				$this->render_settings_panel_end();
				?>
				<?php submit_button( 'Einstellungen speichern' ); ?>
			</div>
		</form>
		<script>
		(function($){
			$('.sydb-settings-tab').on('click',function(){
				var target=$(this).data('target');
				$('.sydb-settings-tab').removeClass('is-active');
				$(this).addClass('is-active');
				$('.sydb-settings-panel').removeClass('is-active');
				$('#'+target).addClass('is-active');
				if(history.replaceState){history.replaceState(null,'','#'+target);}
			});
			if(window.location.hash && $(window.location.hash).hasClass('sydb-settings-panel')){
				$('.sydb-settings-tab[data-target="'+window.location.hash.substring(1)+'"]').trigger('click');
			}
			$(document).off('click.sydbMediaSelect','.sydb-media-select').on('click.sydbMediaSelect','.sydb-media-select',function(e){
				e.preventDefault();
				var button = $(this);
				var target = $(button.attr('data-target'));
				if(!target.length){ return; }
				if(typeof wp === 'undefined' || !wp.media){ alert('WordPress-Mediathek konnte nicht geladen werden. Bitte Seite neu laden.'); return; }
				var mediaType = button.attr('data-media-type') || (button.text().toLowerCase().indexOf('mp3') !== -1 ? 'audio' : 'image');
				var frame = wp.media({title:mediaType === 'audio' ? 'MP3 auswählen' : 'Bild auswählen',library:{type:mediaType},button:{text:mediaType === 'audio' ? 'MP3 übernehmen' : 'Bild übernehmen'},multiple:false});
				frame.on('select',function(){
					var att = frame.state().get('selection').first().toJSON();
					target.val(att.url).trigger('input').trigger('change');
					var idTarget = button.attr('data-id-target');
					if(idTarget){ $(idTarget).val(att.id || 0).trigger('change'); button.closest('.sydb-pdf-image-field').find('small span').text(att.id || 'wird nach Auswahl gespeichert'); }
					var previewTarget = button.attr('data-preview');
					if(previewTarget){ $(previewTarget).html('<img src="'+att.url.replace(/"/g,'&quot;')+'" alt="" />'); }
				});
				frame.open();
			});
			$(document).off('click.sydbMediaRemove','.sydb-media-remove').on('click.sydbMediaRemove','.sydb-media-remove',function(e){
				e.preventDefault();
				var button = $(this);
				var target = $(button.attr('data-target'));
				if(target.length){ target.val('').trigger('input').trigger('change'); }
				var idTarget = button.attr('data-id-target');
				if(idTarget){ $(idTarget).val('0').trigger('change'); }
				var previewTarget = button.attr('data-preview');
				if(previewTarget){ $(previewTarget).html('<span>Keine Grafik gewählt</span>'); }
				button.closest('.sydb-pdf-image-field').find('small span').text('wird nach Auswahl gespeichert');
			});
			$(document).off('input.sydbPdfImageUrl','.sydb-pdf-image-field input[id$="_url"]').on('input.sydbPdfImageUrl','.sydb-pdf-image-field input[id$="_url"]',function(){
				var input = $(this);
				var box = input.closest('.sydb-pdf-image-field');
				var val = $.trim(input.val());
				var idTarget = box.find('input[type="hidden"][id$="_id"]');
				if(idTarget.length){ idTarget.val('0'); }
				box.find('small span').text('wird nach Auswahl gespeichert');
				var preview = box.find('.sydb-pdf-image-preview');
				if(preview.length){
					if(val){ preview.html('<img src="'+val.replace(/"/g,'&quot;')+'" alt="" />'); }
					else { preview.html('<span>Keine Grafik gewählt</span>'); }
				}
			});
		})(jQuery);
		</script>
		<?php
		$this->admin_wrap_end();
	}

}
