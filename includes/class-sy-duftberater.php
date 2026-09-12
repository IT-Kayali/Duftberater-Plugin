<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once SY_DUFTBERATER_PATH . 'includes/class-sy-duftberater-admin.php';
require_once SY_DUFTBERATER_PATH . 'includes/class-sy-duftberater-frontend.php';
require_once SY_DUFTBERATER_PATH . 'includes/class-sy-duftberater-matcher.php';

class SY_Duftberater {

	const OPTION_SETTINGS            = 'sy_duftberater_settings';
	const OPTION_QUESTIONS           = 'sy_duftberater_questions';
	const OPTION_EXAMPLES_IMPORTED   = 'sy_duftberater_examples_imported';
	const OPTION_DATASET_VERSION    = 'sy_duftberater_dataset_version';
	const OPTION_PDF_TEMPLATE_VERSION = 'sy_duftberater_pdf_template_version';
	const CAP_MANAGE                 = 'manage_sy_duftberater';
	const POST_TYPE_PARFUM           = 'sydb_parfum';
	const POST_TYPE_LEAD             = 'sydb_lead';
	const META_PREFIX                = '_sydb_';

	protected $admin;
	protected $frontend;
	protected $matcher;

	public function init() {
		$this->load_dependencies();
		$this->register_hooks();
	}

	protected function load_dependencies() {
		$this->matcher  = new SY_Duftberater_Matcher( $this );
		$this->admin    = new SY_Duftberater_Admin( $this );
		$this->frontend = new SY_Duftberater_Frontend( $this, $this->matcher );
	}

	protected function register_hooks() {
		register_activation_hook( SY_DUFTBERATER_FILE, array( $this, 'activate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_parfum_post_type' ) );
		add_action( 'init', array( $this, 'register_lead_post_type' ) );
		add_action( 'init', array( $this, 'maybe_sync_dataset' ), 20 );
		add_action( 'admin_init', array( $this, 'ensure_role_capabilities' ), 5 );
		add_action( 'admin_init', array( $this, 'maybe_upgrade_pdf_templates' ), 20 );
		add_action( 'plugins_loaded', array( $this->admin, 'init' ) );
		add_action( 'plugins_loaded', array( $this->frontend, 'init' ) );
	}

	public function maybe_upgrade_pdf_templates() {
		if ( ! is_admin() ) {
			return;
		}
		$stored_version = (string) get_option( self::OPTION_PDF_TEMPLATE_VERSION, '' );
		if ( $stored_version === SY_DUFTBERATER_VERSION ) {
			return;
		}

		$settings = get_option( self::OPTION_SETTINGS, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$defaults = $this->get_default_settings();
		$settings = wp_parse_args( $settings, $defaults );

		// Deep-merge the PDF template structure so older installations automatically receive new fields
		// without overwriting existing uploaded images or custom texts.
		$current_pdf = isset( $settings['pdf_templates'] ) && is_array( $settings['pdf_templates'] ) ? $settings['pdf_templates'] : array();
		$default_pdf = $this->get_default_pdf_templates();
		foreach ( $default_pdf as $lang => $tpl_defaults ) {
			$current_lang = isset( $current_pdf[ $lang ] ) && is_array( $current_pdf[ $lang ] ) ? $current_pdf[ $lang ] : array();
			$current_pdf[ $lang ] = wp_parse_args( $current_lang, $tpl_defaults );
		}
		$settings['pdf_templates'] = $current_pdf;

		// Version 1.28.30: Arabic PDF intro is now fully editable in the PDF template.
		// If an older Arabic default intro exists without {name}, add the editable placeholder once.
		if ( isset( $settings['pdf_templates']['ar']['intro_text'] ) ) {
			$ar_intro = (string) $settings['pdf_templates']['ar']['intro_text'];
			if ( false === strpos( $ar_intro, '{name}' ) && ( false !== mb_strpos( $ar_intro, 'إجاباتك' ) || false !== mb_strpos( $ar_intro, 'اخترنا لك' ) ) ) {
				$settings['pdf_templates']['ar']['intro_text'] = "مرحباً {name}،\n" . trim( $ar_intro );
			}
		}

		// Version 1.28.12: Duftnoten sind jetzt ein positiver Matching-Faktor statt Negativfilter.
		// Alte gespeicherte Standardtexte werden automatisch aktualisiert, eigene stark abweichende Texte bleiben unangetastet.
		$default_ui = $this->get_default_i18n_texts();
		if ( ! isset( $settings['ui_i18n'] ) || ! is_array( $settings['ui_i18n'] ) ) {
			$settings['ui_i18n'] = array();
		}
		$current_hint = isset( $settings['ui_i18n']['hint_duftnoten'] ) && is_array( $settings['ui_i18n']['hint_duftnoten'] ) ? $settings['ui_i18n']['hint_duftnoten'] : array();
		$hint_de = strtolower( (string) ( $current_hint['de'] ?? '' ) );
		$hint_en = strtolower( (string) ( $current_hint['en'] ?? '' ) );
		if ( false !== strpos( $hint_de, 'negativfilter' ) || false !== strpos( $hint_de, 'vermeiden' ) || false !== strpos( $hint_en, 'avoid' ) || false !== strpos( $hint_en, 'negative filter' ) ) {
			$settings['ui_i18n']['hint_duftnoten'] = $default_ui['hint_duftnoten'];
		}

		update_option( self::OPTION_SETTINGS, $settings );

		$current_questions = get_option( self::OPTION_QUESTIONS, array() );
		if ( is_array( $current_questions ) && ! empty( $current_questions ) ) {
			$default_questions = $this->get_default_questions();
			$default_duftnoten = null;
			foreach ( $default_questions as $default_question ) {
				if ( 'duftnoten' === sanitize_key( $default_question['key'] ?? '' ) ) {
					$default_duftnoten = $default_question;
					break;
				}
			}
			if ( is_array( $default_duftnoten ) ) {
				$questions_changed = false;
				foreach ( $current_questions as &$question ) {
					if ( 'duftnoten' !== sanitize_key( $question['key'] ?? '' ) ) {
						continue;
					}
					$label_de = strtolower( (string) ( $question['label_i18n']['de'] ?? ( $question['label'] ?? '' ) ) );
					$label_en = strtolower( (string) ( $question['label_i18n']['en'] ?? '' ) );
					$desc_de  = strtolower( (string) ( $question['description_i18n']['de'] ?? ( $question['description'] ?? '' ) ) );
					$desc_en  = strtolower( (string) ( $question['description_i18n']['en'] ?? '' ) );
					if ( false !== strpos( $label_de, 'unerwünsch' ) || false !== strpos( $label_en, 'unwanted' ) || false !== strpos( $desc_de, 'ausschluss' ) || false !== strpos( $desc_de, 'negativfilter' ) || false !== strpos( $desc_en, 'exclude' ) || false !== strpos( $desc_en, 'negative filter' ) ) {
						$question['label']            = $default_duftnoten['label'];
						$question['description']      = $default_duftnoten['description'];
						$question['label_i18n']       = $default_duftnoten['label_i18n'];
						$question['description_i18n'] = $default_duftnoten['description_i18n'];
						$questions_changed = true;
					}
				}
				unset( $question );
				if ( $questions_changed ) {
					update_option( self::OPTION_QUESTIONS, $current_questions );
				}
			}
		}

		// Version 1.28.12: Keine automatische Massen-Regenerierung alter Lead-PDFs beim Update.
		// Auf manchen Hostings kann dieser Vorgang beim Aktivieren/Öffnen des Backends einen Internal Server Error auslösen.
		// Neue PDFs werden weiterhin normal erzeugt. Bereits gespeicherte PDFs bleiben unverändert.

		update_option( self::OPTION_PDF_TEMPLATE_VERSION, SY_DUFTBERATER_VERSION );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'sy-duftberater', false, dirname( plugin_basename( SY_DUFTBERATER_FILE ) ) . '/languages' );
	}


	public function ensure_role_capabilities() {
		$roles = array( 'administrator', 'shop_manager' );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			$role->add_cap( self::CAP_MANAGE );
			$role->add_cap( 'upload_files' );
			$role->add_cap( 'edit_posts' );
			$role->add_cap( 'read' );
		}
	}

	public function activate() {
		$this->ensure_role_capabilities();
		$current_settings = get_option( self::OPTION_SETTINGS, array() );
		update_option( self::OPTION_SETTINGS, wp_parse_args( $current_settings, $this->get_default_settings() ) );

		$current_questions = get_option( self::OPTION_QUESTIONS, array() );
		if ( empty( $current_questions ) || ! is_array( $current_questions ) ) {
			update_option( self::OPTION_QUESTIONS, $this->get_default_questions() );
		}

		$this->register_parfum_post_type();
		$this->register_lead_post_type();
		flush_rewrite_rules();

		if ( (int) $this->get_setting( 'enable_example_data', 1 ) === 1 ) {
			// Sicherer Import ohne Löschen: bestehende Parfums werden anhand des Namens aktualisiert.
			$this->sync_default_dataset_parfums();
			update_option( self::OPTION_EXAMPLES_IMPORTED, 1 );
			update_option( self::OPTION_DATASET_VERSION, SY_DUFTBERATER_DATASET_VERSION );
		}
	}

	public function register_parfum_post_type() {
		register_post_type(
			self::POST_TYPE_PARFUM,
			array(
				'labels' => array(
					'name'               => 'Parfums',
					'singular_name'      => 'Parfum',
					'add_new'            => 'Neu hinzufügen',
					'add_new_item'       => 'Parfum hinzufügen',
					'edit_item'          => 'Parfum bearbeiten',
					'new_item'           => 'Neues Parfum',
					'view_item'          => 'Parfum ansehen',
					'search_items'       => 'Parfums durchsuchen',
					'not_found'          => 'Keine Parfums gefunden',
					'not_found_in_trash' => 'Keine Parfums im Papierkorb gefunden',
					'menu_name'          => 'Parfums',
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'supports'            => array( 'title', 'editor', 'thumbnail' ),
				'menu_icon'           => 'dashicons-format-image',
				'has_archive'         => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}


	public function register_lead_post_type() {
		register_post_type(
			self::POST_TYPE_LEAD,
			array(
				'labels' => array(
					'name'          => 'Duftberater Einträge',
					'singular_name' => 'Duftberater Eintrag',
					'menu_name'     => 'Einträge',
				),
				'public'            => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'supports'          => array( 'title' ),
				'capability_type'   => 'post',
				'map_meta_cap'      => true,
				'has_archive'       => false,
				'rewrite'           => false,
			)
		);
	}

	public function get_default_settings() {
		return array(
			'result_count'        => 3,
			'start_button_text'   => 'Jetzt Duft finden',
			'accent_color'        => '#d4af37',
			'frontend_theme'      => 'dark',
			'default_language'    => 'de',
			'enable_example_data' => 1,
			'language_images'     => $this->get_default_language_images(),
			'pdf_logo_url'        => 'https://alowidat.de/wp-content/uploads/2025/06/Alowidat-Final-Logo.ai-600-x-298-px.png',
			'frontend_logo_link' => '',
			'brand_name'          => 'Alowidat',
			'brand_address'       => '',
			'brand_phone'         => '',
			'brand_email'         => '',
			'brand_website'       => '',
			'coupon_percent'      => 15,
			'audio_disabled'     => 0,
			'click_sound_enabled' => 1,
			'click_sound_url'     => 'https://alowidat.de/wp-content/uploads/2026/05/virtualzero-mouse-tap-single-studio-vocal-hd-379364.mp3',
			'welcome_audio_i18n'  => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'result_audio_i18n'   => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'completion_audio_i18n' => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'auto_download_enabled' => 1,
			'frontend_fullwidth' => 0,
			'hide_plugin_header' => 0,
			'hide_plugin_footer' => 0,
			'ui_i18n'             => $this->get_default_i18n_texts(),
			'html_i18n'           => $this->get_default_html_i18n_texts(),
			'pdf_templates'       => $this->get_default_pdf_templates(),
		);
	}

	public function get_supported_languages() {
		return array(
			'de' => array( 'label' => 'Deutsch', 'native' => 'Deutsch', 'dir' => 'ltr', 'icon' => 'DE' ),
			'en' => array( 'label' => 'English', 'native' => 'English', 'dir' => 'ltr', 'icon' => 'EN' ),
			'ar' => array( 'label' => 'Arabisch', 'native' => 'العربية', 'dir' => 'rtl', 'icon' => 'AR' ),
		);
	}


	public function get_default_language_images() {
		return array(
			'de' => 'https://alowidat.de/wp-content/uploads/2026/05/d.jpg',
			'en' => 'https://alowidat.de/wp-content/uploads/2026/05/e.png',
			'ar' => 'https://alowidat.de/wp-content/uploads/2026/05/s.jpg',
		);
	}

	public function get_language_image( $lang ) {
		$lang     = $this->normalize_lang( $lang );
		$settings = $this->get_settings();
		$defaults = $this->get_default_language_images();
		$images   = isset( $settings['language_images'] ) && is_array( $settings['language_images'] ) ? $settings['language_images'] : array();
		$url      = isset( $images[ $lang ] ) && '' !== (string) $images[ $lang ] ? $images[ $lang ] : ( $defaults[ $lang ] ?? '' );
		return esc_url_raw( $url );
	}

	public function normalize_lang( $lang ) {
		$lang = sanitize_key( $lang );
		return isset( $this->get_supported_languages()[ $lang ] ) ? $lang : 'de';
	}

	public function get_default_html_i18n_texts() {
		return array(
			'pdf_body_html'   => array( 'de' => '<p>Hallo <strong>{name}</strong>,</p><p>basierend auf deinen Antworten haben wir diese drei Düfte für dich ausgewählt. Beim Besuch unserer Filiale erhältst du mit dieser Empfehlung 15% Rabatt.</p>', 'en' => '<p>Hello <strong>{name}</strong>,</p><p>Based on your answers, we selected these three fragrances for you. When visiting our store, you receive a 15% discount with this recommendation.</p>', 'ar' => '<p>مرحباً <strong>{name}</strong>،</p><p>بناءً على إجاباتك اخترنا لك هذه العطور الثلاثة. عند زيارة فرعنا ستحصل على خصم 15٪ مع هذه التوصية.</p>' ),
			'pdf_footer_html' => array( 'de' => '<p>Dein Alowidat Duftberater</p>', 'en' => '<p>Your Alowidat fragrance advisor</p>', 'ar' => '<p>مستشار العطور من الويدات</p>' ),
			'mail_body_html'  => array( 'de' => '<p>Hallo <strong>{name}</strong>,</p><p>vielen Dank für deine Anfrage. Im Anhang findest du deine persönliche Duftempfehlung als PDF.</p><p>Viele Grüße<br>Alowidat</p>', 'en' => '<p>Hello <strong>{name}</strong>,</p><p>Thank you for your request. Your personal fragrance recommendation PDF is attached to this email.</p><p>Best regards<br>Alowidat</p>', 'ar' => '<p>مرحباً <strong>{name}</strong>،</p><p>شكراً لطلبك. ستجد ملف ترشيحاتك الشخصية للعطور مرفقاً مع هذه الرسالة.</p><p>مع أطيب التحيات<br>Alowidat</p>' ),
		);
	}


	public function get_default_pdf_templates() {
		$image_defaults = array();
		foreach ( array(
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
		) as $key ) {
			$image_defaults[ $key . '_id' ]  = 0;
			$image_defaults[ $key . '_url' ] = '';
		}

		$base = array_merge( $image_defaults, array(
			'intro_text'   => "Hallo {name},\nbasierend auf deinen Antworten haben wir diese drei Düfte für dich ausgewählt.\nBeim Besuch unserer Filiale erhältst du mit diesem Ergebnis 15% Rabatt.",
			'result_title' => 'Deine Top 3 Duftempfehlungen',
			'website'      => 'www.alowidat.de',
			'email'        => 'info@alowidat.de',
			'phone'        => '+49 177 7975434',
		) );

		return array(
			'de' => $base,
			'en' => array_merge( $base, array(
				'intro_text'   => "Hello {name},\nBased on your answers, we selected these three fragrances for you.\nWhen visiting our store, you receive a 15% discount with this result.",
				'result_title' => 'Your Top 3 fragrance recommendations',
			) ),
			'ar' => array_merge( $base, array(
				'intro_text'   => "مرحباً {name}،\nبناءً على إجاباتك اخترنا لك هذه العطور الثلاثة.\nعند زيارة فرعنا ستحصل على خصم 15٪ مع هذه النتيجة.",
				'result_title' => 'أفضل 3 توصيات للعطور',
			) ),
		);
	}


	public function get_pdf_template( $lang ) {
		$lang     = $this->normalize_lang( $lang );
		$settings = $this->get_settings();
		$defaults = $this->get_default_pdf_templates();
		$current  = isset( $settings['pdf_templates'] ) && is_array( $settings['pdf_templates'] ) ? $settings['pdf_templates'] : array();
		$template = isset( $current[ $lang ] ) && is_array( $current[ $lang ] ) ? $current[ $lang ] : array();
		return wp_parse_args( $template, $defaults[ $lang ] ?? $defaults['de'] );
	}

	public function get_default_i18n_texts() {
		return array(
			'header_title'     => array( 'de' => 'Dein persönlicher Duftberater', 'en' => 'Your personal fragrance advisor', 'ar' => 'مستشارك الشخصي للعطور' ),
			'header_subtitle'  => array( 'de' => 'Finde Schritt für Schritt einen Duft, der zu deinem Stil, deinem Anlass und deiner Ausstrahlung passt.', 'en' => 'Find a fragrance step by step that fits your style, occasion and personality.', 'ar' => 'اعثر خطوة بخطوة على عطر يناسب أسلوبك ومناسبتك وحضورك.' ),
			'progress_step'   => array( 'de' => 'Schritt', 'en' => 'Step', 'ar' => 'الخطوة' ),
			'progress_of'     => array( 'de' => 'von', 'en' => 'of', 'ar' => 'من' ),
			'language_kicker' => array( 'de' => 'Sprache wählen', 'en' => 'Choose language', 'ar' => 'اختر اللغة' ),
			'language_title'  => array( 'de' => 'Wähle deine Sprache', 'en' => 'Choose your language', 'ar' => 'اختر لغتك' ),
			'language_text'   => array( 'de' => 'Starte den Duftberater in der Sprache, die für dich am angenehmsten ist.', 'en' => 'Start the fragrance advisor in the language that feels best for you.', 'ar' => 'ابدأ مستشار العطور باللغة الأنسب لك.' ),
			'welcome_kicker'  => array( 'de' => 'Exklusive Duftberatung', 'en' => 'Exclusive fragrance advisor', 'ar' => 'استشارة عطرية خاصة' ),
			'welcome_title'   => array( 'de' => 'Willkommen bei deinem persönlichen Duftberater.', 'en' => 'Welcome to your personal fragrance advisor.', 'ar' => 'مرحباً بك في مستشارك الشخصي للعطور.' ),
			'welcome_text'    => array( 'de' => 'Lass dich Schritt für Schritt beraten und finde den Duft, der perfekt zu dir passt.', 'en' => 'Let us guide you step by step and find the fragrance that perfectly suits you.', 'ar' => 'دعنا نرشدك خطوة بخطوة لتجد العطر الذي يناسبك تماماً.' ),
			'welcome_badge_1' => array( 'de' => 'Persönlich abgestimmt', 'en' => 'Personally matched', 'ar' => 'اختيار شخصي' ),
			'welcome_badge_2' => array( 'de' => 'Elegant geführt', 'en' => 'Elegantly guided', 'ar' => 'إرشاد أنيق' ),
			'welcome_badge_3' => array( 'de' => 'Top 3 Empfehlungen', 'en' => 'Top 3 recommendations', 'ar' => 'أفضل 3 ترشيحات' ),
			'start_button'    => array( 'de' => 'Jetzt Duft finden', 'en' => 'Find fragrance now', 'ar' => 'ابدأ البحث عن العطر' ),
			'back_button'     => array( 'de' => 'Zurück', 'en' => 'Back', 'ar' => 'رجوع' ),
			'next_button'     => array( 'de' => 'Weiter', 'en' => 'Next', 'ar' => 'التالي' ),
			'restart_button'  => array( 'de' => 'Neu starten', 'en' => 'Start again', 'ar' => 'ابدأ من جديد' ),
			'show_results_button' => array( 'de' => 'Ergebnisse anzeigen', 'en' => 'Show results', 'ar' => 'عرض النتائج' ),
			'completion_popup_text' => array( 'de' => 'Vielen Dank, dass du den intelligenten Duftberater von Alowidat genutzt hast. Wir wünschen dir ein besonderes Dufterlebnis mit Alowidat.', 'en' => 'Thank you for using the smart fragrance advisor from Alowidat. We wish you a special fragrance experience with Alowidat.', 'ar' => 'شكرًا لاستخدامك مستشارك الذكي للعطور من العوايدات. نتمنى لك تجربة عطرية مميزة مع العوايدات.' ),
			'completion_popup_restart' => array( 'de' => 'Erneut starten', 'en' => 'Start again', 'ar' => 'ابدأ من جديد' ),
			'result_kicker'   => array( 'de' => 'Ergebnis', 'en' => 'Result', 'ar' => 'النتيجة' ),
			'result_title'    => array( 'de' => 'Deine Top 3 Duftempfehlungen', 'en' => 'Your top 3 fragrance recommendations', 'ar' => 'أفضل 3 عطور مناسبة لك' ),
			'result_text'     => array( 'de' => 'Deine drei besten Matches werden von links nach rechts als Top 1, Top 2 und Top 3 gezeigt.', 'en' => 'Your three best matches are shown from left to right as Top 1, Top 2 and Top 3.', 'ar' => 'تظهر أفضل ثلاث نتائج من اليمين إلى اليسار بترتيب واضح.' ),
			'lead_form_title' => array( 'de' => 'Erhalte deine Top 3 als PDF', 'en' => 'Get your top 3 as a PDF', 'ar' => 'احصل على أفضل 3 عطور كملف PDF' ),
			'lead_form_text'  => array( 'de' => 'Trage deinen Namen und deine E-Mail ein. Du erhältst deine persönliche Duftempfehlung als moderne PDF-Datei.', 'en' => 'Enter your name and email. You will receive your personal fragrance recommendations as a modern PDF file.', 'ar' => 'أدخل اسمك وبريدك الإلكتروني لتحصل على ترشيحاتك الشخصية للعطور كملف PDF.' ),
			'lead_name_label' => array( 'de' => 'Dein Name', 'en' => 'Your name', 'ar' => 'اسمك' ),
			'lead_email_label'=> array( 'de' => 'Deine E-Mail', 'en' => 'Your email', 'ar' => 'بريدك الإلكتروني' ),
			'lead_submit'     => array( 'de' => 'PDF per E-Mail erhalten', 'en' => 'Receive PDF by email', 'ar' => 'استلام PDF عبر البريد' ),
			'lead_success'    => array( 'de' => 'Vielen Dank! Deine PDF wurde erstellt und per E-Mail gesendet.', 'en' => 'Thank you! Your PDF has been created and sent by email.', 'ar' => 'شكراً لك! تم إنشاء ملف PDF وإرساله عبر البريد الإلكتروني.' ),
			'lead_error'      => array( 'de' => 'Bitte Name und gültige E-Mail eingeben.', 'en' => 'Please enter your name and a valid email address.', 'ar' => 'يرجى إدخال الاسم وبريد إلكتروني صحيح.' ),
			'lead_pdf_link'   => array( 'de' => 'PDF öffnen', 'en' => 'Open PDF', 'ar' => 'فتح ملف PDF' ),
			'lead_pdf_download' => array( 'de' => 'PDF herunterladen', 'en' => 'Download PDF', 'ar' => 'تحميل ملف PDF' ),
			'pdf_title'       => array( 'de' => 'Deine persönliche Duftempfehlung', 'en' => 'Your personal fragrance recommendation', 'ar' => 'ترشيحاتك الشخصية للعطور' ),
			'pdf_intro'       => array( 'de' => 'Basierend auf deinen Antworten haben wir diese drei Düfte für dich ausgewählt.', 'en' => 'Based on your answers, we selected these three fragrances for you.', 'ar' => 'بناءً على إجاباتك اخترنا لك هذه العطور الثلاثة.' ),
			'pdf_greeting'    => array( 'de' => 'Hallo {name}, vielen Dank für die Nutzung unseres Online-Duftberaters.', 'en' => 'Hello {name}, thank you for using our online fragrance advisor.', 'ar' => 'مرحباً {name}، شكراً لاستخدامك مستشار العطور الإلكتروني.' ),
			'pdf_coupon_text'  => array( 'de' => 'Beim Besuch unserer Filiale erhältst du mit dieser Empfehlung 15% Rabatt.', 'en' => 'When visiting our store, you receive a 15% discount with this recommendation.', 'ar' => 'عند زيارة فرعنا ستحصل على خصم 15 بالمئة مع هذه التوصية.' ),
			'pdf_body'         => array( 'de' => 'Beim Besuch unserer Filiale erhältst du mit dieser Empfehlung 15% Rabatt.\n\nDu kannst diesen Bereich im Backend frei mit eigenem Text oder einfachem HTML bearbeiten.', 'en' => 'When visiting our store, you receive a 15% discount with this recommendation.\n\nYou can freely edit this section in the backend with your own text or simple HTML.', 'ar' => 'عند زيارة فرعنا ستحصل على خصم 15 بالمئة مع هذه التوصية.\n\nيمكنك تعديل هذا القسم من لوحة التحكم بنصك الخاص أو باستخدام HTML بسيط.' ),
			'pdf_footer'       => array( 'de' => 'Dein Alowidat Duftberater', 'en' => 'Your Alowidat fragrance advisor', 'ar' => 'مستشار العطور من الويدات' ),
			'pdf_body_text'    => array( 'de' => "Hallo {name},\n\nBasierend auf deinen Antworten haben wir diese drei Düfte für dich ausgewählt. Beim Besuch unserer Filiale erhältst du mit dieser Empfehlung 15% Rabatt.", 'en' => "Hello {name},\n\nBased on your answers, we selected these three fragrances for you. When visiting our store, you receive a 15% discount with this recommendation.", 'ar' => "مرحباً {name}،\n\nبناءً على إجاباتك اخترنا لك هذه العطور الثلاثة. عند زيارة فرعنا ستحصل على خصم 15٪ مع هذه التوصية." ),
			'pdf_footer_text'  => array( 'de' => 'Dein Alowidat Duftberater', 'en' => 'Your Alowidat fragrance advisor', 'ar' => 'مستشار العطور من الويدات' ),
			'mail_subject'     => array( 'de' => 'Deine persönliche Duftempfehlung', 'en' => 'Your personal fragrance recommendation', 'ar' => 'ترشيحاتك الشخصية للعطور' ),
			'mail_body_text'   => array( 'de' => "Hallo {name},\n\nVielen Dank für deine Anfrage. Im Anhang findest du deine persönliche Duftempfehlung als PDF.\n\nViele Grüße\nAlowidat", 'en' => "Hello {name},\n\nThank you for your request. Your personal fragrance recommendation PDF is attached to this email.\n\nBest regards\nAlowidat", 'ar' => "مرحباً {name}،\n\nشكراً لطلبك. ستجد ملف ترشيحاتك الشخصية للعطور مرفقاً مع هذه الرسالة.\n\nمع أطيب التحيات\nAlowidat" ),
			'loading_text'    => array( 'de' => 'Wir berechnen gerade passende Düfte für deine Auswahl …', 'en' => 'We are calculating suitable fragrances for your selection …', 'ar' => 'نحسب الآن العطور المناسبة لاختياراتك …' ),
			'lead_sending_text' => array( 'de' => 'Deine PDF wird erstellt und per E-Mail gesendet …', 'en' => 'Your PDF is being created and sent by email …', 'ar' => 'يتم الآن إنشاء ملف PDF وإرساله عبر البريد الإلكتروني …' ),
			'empty_text'      => array( 'de' => 'Aktuell konnten keine passenden Ergebnisse geladen werden.', 'en' => 'No suitable results could be loaded right now.', 'ar' => 'لم نتمكن حالياً من عرض نتائج مناسبة.' ),
			'selection_empty'=> array( 'de' => 'Deine ausgewählten Antworten erscheinen hier ab dem ersten Schritt.', 'en' => 'Your selected answers will appear here from the first step.', 'ar' => 'ستظهر إجاباتك المختارة هنا بعد الخطوة الأولى.' ),
			'click_next'     => array( 'de' => 'Ein Klick führt direkt zum nächsten Schritt.', 'en' => 'One click takes you directly to the next step.', 'ar' => 'نقرة واحدة تنقلك مباشرة إلى الخطوة التالية.' ),
			'multi_hint'     => array( 'de' => 'Mehrfachauswahl aktiv. Du kannst mehrere Antworten kombinieren.', 'en' => 'Multiple selection is active. You can combine several answers.', 'ar' => 'يمكنك اختيار أكثر من إجابة.' ),
			'optional_hint'  => array( 'de' => 'Diese Frage ist optional. Du kannst sie bei Bedarf überspringen.', 'en' => 'This question is optional. You can skip it if needed.', 'ar' => 'هذا السؤال اختياري ويمكنك تخطيه.' ),
			'single_subtitle'=> array( 'de' => 'Ein Klick übernimmt diese Auswahl', 'en' => 'One click selects this answer', 'ar' => 'نقرة واحدة لاختيار هذه الإجابة' ),
			'multi_subtitle' => array( 'de' => 'Mehrfachauswahl möglich', 'en' => 'Multiple selection possible', 'ar' => 'يمكن اختيار أكثر من إجابة' ),
			'product_button' => array( 'de' => 'Produkt ansehen', 'en' => 'View product', 'ar' => 'عرض المنتج' ),
			'match_label'    => array( 'de' => 'Match', 'en' => 'Match', 'ar' => 'تطابق' ),
			'usage_main_alltag_subtitle' => array( 'de' => 'Zuhause, Ausgehen und Freizeit oder Arbeit', 'en' => 'At home, going out, leisure or work', 'ar' => 'في المنزل، الخروج، وقت الفراغ أو العمل' ),
			'usage_main_formell_subtitle' => array( 'de' => 'Meeting, Date oder private feierliche Anlässe', 'en' => 'Meeting, date or private special occasions', 'ar' => 'اجتماع، موعد أو مناسبات خاصة' ),
			'hint_duftnoten' => array( 'de' => '', 'en' => '', 'ar' => '' ),
			'hint_duftrichtungen_office' => array( 'de' => 'Für Büro und Meetings priorisiert der Berater eher klare, gepflegte und nicht zu aggressive Profile.', 'en' => 'For office and meetings, the advisor prioritizes clean, polished and not too intense profiles.', 'ar' => 'للمكتب والاجتماعات يعطي المستشار أولوية للعطور النظيفة والأنيقة وغير القوية جداً.' ),
			'hint_duftrichtungen_date' => array( 'de' => 'Für Dates priorisiert der Berater charakterstarke und sinnliche Duftprofile.', 'en' => 'For dates, the advisor prioritizes distinctive and sensual fragrance profiles.', 'ar' => 'للمواعيد يعطي المستشار أولوية للعطور الجذابة والحسية ذات الطابع الواضح.' ),
			'hint_usage_sommer' => array( 'de' => 'Im Sommer stehen Alltag, Büro und leichte Freizeit-Anlässe meist weiter oben.', 'en' => 'In summer, everyday use, office and light leisure occasions are usually prioritized.', 'ar' => 'في الصيف تكون الاستخدامات اليومية والمكتب والمناسبات الخفيفة غالباً أنسب.' ),
			'hint_usage_winter' => array( 'de' => 'Im Winter rücken starke, abendliche und formellere Einsatzbereiche oft weiter nach oben.', 'en' => 'In winter, stronger evening and more formal occasions are often prioritized.', 'ar' => 'في الشتاء تكون الاستخدامات القوية والمسائية والرسمية غالباً أكثر مناسبة.' ),
			'ajax_error_text' => array( 'de' => 'Die Ergebnisse konnten gerade nicht geladen werden.', 'en' => 'The results could not be loaded right now.', 'ar' => 'تعذر تحميل النتائج حالياً.' ),
			'ajax_exception_text' => array( 'de' => 'Beim Laden der Ergebnisse ist ein Fehler aufgetreten.', 'en' => 'An error occurred while loading the results.', 'ar' => 'حدث خطأ أثناء تحميل النتائج.' ),
		);
	}

	public function get_i18n_text( $group, $lang = 'de', $fallback = '' ) {
		$lang = $this->normalize_lang( $lang );
		if ( is_array( $group ) ) {
			if ( isset( $group[ $lang ] ) && '' !== (string) $group[ $lang ] ) {
				return (string) $group[ $lang ];
			}
			if ( isset( $group['de'] ) && '' !== (string) $group['de'] ) {
				return (string) $group['de'];
			}
		}
		return (string) $fallback;
	}

	public function get_ui_text( $key, $lang = 'de' ) {
		$settings = $this->get_settings();
		$ui       = isset( $settings['ui_i18n'] ) && is_array( $settings['ui_i18n'] ) ? $settings['ui_i18n'] : array();
		$defaults = $this->get_default_i18n_texts();
		return $this->get_i18n_text( $ui[ $key ] ?? ( $defaults[ $key ] ?? array() ), $lang, $this->get_i18n_text( $defaults[ $key ] ?? array(), $lang, '' ) );
	}

	public function get_html_text( $key, $lang = 'de' ) {
		$settings = $this->get_settings();
		$html     = isset( $settings['html_i18n'] ) && is_array( $settings['html_i18n'] ) ? $settings['html_i18n'] : array();
		$defaults = $this->get_default_html_i18n_texts();
		return $this->get_i18n_text( $html[ $key ] ?? ( $defaults[ $key ] ?? array() ), $lang, $this->get_i18n_text( $defaults[ $key ] ?? array(), $lang, '' ) );
	}

	public function get_ui_texts_for_js() {
		$out = array();
		foreach ( array_keys( $this->get_supported_languages() ) as $lang ) {
			foreach ( $this->get_default_i18n_texts() as $key => $value ) {
				$out[ $lang ][ $key ] = $this->get_ui_text( $key, $lang );
			}
		}
		return $out;
	}

	public function get_settings() {
		return wp_parse_args( get_option( self::OPTION_SETTINGS, array() ), $this->get_default_settings() );
	}

	public function get_setting( $key, $default = null ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public function get_default_questions() {
		$questions = include SY_DUFTBERATER_PATH . 'includes/data-questions.php';
		return is_array( $questions ) ? $questions : array();
	}

	public function sanitize_questions( $questions ) {
		$sanitized = array();

		if ( ! is_array( $questions ) ) {
			return $this->get_default_questions();
		}

		foreach ( $questions as $index => $question ) {
			$key = sanitize_key( $question['key'] ?? '' );
			if ( empty( $key ) ) {
				$key = 'frage_' . ( $index + 1 );
			}

			$label_i18n = $this->sanitize_i18n_field( $question['label_i18n'] ?? array(), $question['label'] ?? '' );
			$desc_i18n  = $this->sanitize_i18n_field( $question['description_i18n'] ?? array(), $question['description'] ?? '', true );

			$options      = array();
			$options_i18n = array();
			$option_icons = array();
			$usage_group_icons = array();
			$raw_usage_group_icons = isset( $question['usage_group_icons'] ) && is_array( $question['usage_group_icons'] ) ? $question['usage_group_icons'] : array();
			foreach ( array( 'alltag', 'formell' ) as $usage_group_key ) {
				$usage_group_icons[ $usage_group_key ] = isset( $raw_usage_group_icons[ $usage_group_key ] ) ? esc_url_raw( $raw_usage_group_icons[ $usage_group_key ] ) : '';
			}

			$usage_stage_audio_i18n = array();
			$raw_usage_stage_audio_i18n = isset( $question['usage_stage_audio_i18n'] ) && is_array( $question['usage_stage_audio_i18n'] ) ? $question['usage_stage_audio_i18n'] : array();
			foreach ( array( 'alltag', 'arbeit', 'formell' ) as $usage_stage_key ) {
				$usage_stage_audio_i18n[ $usage_stage_key ] = array();
				foreach ( array_keys( $this->get_supported_languages() ) as $audio_lang ) {
					$usage_stage_audio_i18n[ $usage_stage_key ][ $audio_lang ] = isset( $raw_usage_stage_audio_i18n[ $usage_stage_key ][ $audio_lang ] ) ? esc_url_raw( $raw_usage_stage_audio_i18n[ $usage_stage_key ][ $audio_lang ] ) : '';
				}
			}
			$answer_display = isset( $question['answer_display'] ) ? sanitize_key( $question['answer_display'] ) : 'image_only';
			$audio_url      = isset( $question['audio_url'] ) ? esc_url_raw( $question['audio_url'] ) : '';
			$audio_i18n     = array();
			$raw_audio_i18n = isset( $question['audio_i18n'] ) && is_array( $question['audio_i18n'] ) ? $question['audio_i18n'] : array();
			foreach ( array_keys( $this->get_supported_languages() ) as $audio_lang ) {
				$audio_i18n[ $audio_lang ] = isset( $raw_audio_i18n[ $audio_lang ] ) ? esc_url_raw( $raw_audio_i18n[ $audio_lang ] ) : '';
			}
			// Kein Rückfall auf audio_url, wenn audio_i18n bewusst leer gespeichert wurde.
			if ( ! in_array( $answer_display, array( 'image_only', 'image_text', 'text' ), true ) ) {
				$answer_display = 'image_only';
			}
			$raw_i18n     = isset( $question['options_i18n'] ) && is_array( $question['options_i18n'] ) ? $question['options_i18n'] : array();
			$raw_icons    = isset( $question['option_icons'] ) && is_array( $question['option_icons'] ) ? $question['option_icons'] : array();

			if ( ! empty( $question['options'] ) && is_array( $question['options'] ) ) {
				foreach ( $question['options'] as $value => $label ) {
					$option_key = sanitize_key( $value );
					$option_i18n = $this->sanitize_i18n_field( $raw_i18n[ $option_key ] ?? array(), $label );
					$option_label = $option_i18n['de'];
					if ( '' !== $option_key && '' !== $option_label ) {
						$options[ $option_key ] = $option_label;
						$options_i18n[ $option_key ] = $option_i18n;
						$option_icons[ $option_key ] = isset( $raw_icons[ $option_key ] ) ? esc_url_raw( $raw_icons[ $option_key ] ) : '';
					}
				}
			}

			if ( 'alter' !== $key ) {
				$options = $this->sort_options_alphabetically( $options );
			}
			$ordered_i18n = array();
			$ordered_icons = array();
			foreach ( $options as $option_key => $option_label ) {
				$ordered_i18n[ $option_key ] = $options_i18n[ $option_key ] ?? array( 'de' => $option_label, 'en' => '', 'ar' => '' );
				$ordered_icons[ $option_key ] = $option_icons[ $option_key ] ?? '';
			}
			$options_i18n = $ordered_i18n;
			$option_icons = $ordered_icons;

			if ( empty( $options ) ) {
				continue;
			}

			// Feste Roadmap-Reihenfolge: Altersgruppe direkt nach Geschlecht, auch bei alten gespeicherten Fragen.
			$forced_order = array(
				'geschlecht'         => 1,
				'alter'              => 2,
				'jahreszeit'         => 3,
				'verwendungsbereich' => 4,
				'raucher'            => 5,
				'duftrichtungen'     => 6,
				'duftnoten'          => 7,
			);
			$order = isset( $forced_order[ $key ] ) ? (int) $forced_order[ $key ] : ( isset( $question['order'] ) ? absint( $question['order'] ) : ( $index + 1 ) );

			$sanitized[] = array(
				'key'              => $key,
				'label'            => $label_i18n['de'],
				'description'      => $desc_i18n['de'],
				'label_i18n'       => $label_i18n,
				'description_i18n' => $desc_i18n,
				'multiple'         => ! empty( $question['multiple'] ) ? 1 : 0,
				'required'         => array_key_exists( 'required', $question ) ? ( ! empty( $question['required'] ) ? 1 : 0 ) : 1,
				'order'            => $order,
				'options'          => $options,
				'options_i18n'     => $options_i18n,
				'option_icons'     => $option_icons,
				'usage_group_icons' => $usage_group_icons,
				'usage_stage_audio_i18n' => $usage_stage_audio_i18n,
				'answer_display'   => $answer_display,
				'audio_url'        => $audio_url,
				'audio_i18n'       => $audio_i18n,
			);
		}

		usort(
			$sanitized,
			function( $a, $b ) {
				return (int) $a['order'] <=> (int) $b['order'];
			}
		);

		return ! empty( $sanitized ) ? array_values( $sanitized ) : $this->get_default_questions();
	}



	protected function sanitize_i18n_field( $value, $fallback = '', $textarea = false ) {
		$value = is_array( $value ) ? $value : array();
		$fallback = (string) $fallback;
		$out = array();
		foreach ( array_keys( $this->get_supported_languages() ) as $lang ) {
			$raw = isset( $value[ $lang ] ) ? (string) $value[ $lang ] : ( 'de' === $lang ? $fallback : '' );
			$out[ $lang ] = $textarea ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
		}
		if ( '' === $out['de'] ) {
			$out['de'] = $textarea ? sanitize_textarea_field( $fallback ) : sanitize_text_field( $fallback );
		}
		return $out;
	}

	protected function sort_options_alphabetically( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$sorted = $options;
		if ( function_exists( 'collator_create' ) ) {
			$collator = collator_create( 'de_DE' );
			if ( $collator ) {
				uksort(
					$sorted,
					function ( $a, $b ) use ( $sorted, $collator ) {
						return collator_compare( $collator, (string) $sorted[ $a ], (string) $sorted[ $b ] );
					}
				);
				return $sorted;
			}
		}

		uksort(
			$sorted,
			function ( $a, $b ) use ( $sorted ) {
				return strnatcasecmp( remove_accents( (string) $sorted[ $a ] ), remove_accents( (string) $sorted[ $b ] ) );
			}
		);

		return $sorted;
	}

	protected function sort_group_option_values( $question_key, $values ) {
		$question_key = sanitize_key( $question_key );
		$labels       = $this->get_options_for_question( $question_key );
		$values       = array_values( array_filter( array_map( 'sanitize_key', (array) $values ) ) );

		usort(
			$values,
			function ( $a, $b ) use ( $labels ) {
				$label_a = isset( $labels[ $a ] ) ? remove_accents( (string) $labels[ $a ] ) : $a;
				$label_b = isset( $labels[ $b ] ) ? remove_accents( (string) $labels[ $b ] ) : $b;
				return strnatcasecmp( $label_a, $label_b );
			}
		);

		return $values;
	}

	public function get_questions() {
		$questions = get_option( self::OPTION_QUESTIONS, array() );
		$questions = $this->maybe_migrate_questions( $questions );
		return $this->sanitize_questions( $questions );
	}

	protected function maybe_migrate_questions( $questions ) {
		if ( ! is_array( $questions ) ) {
			return $questions;
		}

		$defaults       = $this->get_default_questions();
		$default_by_key = array();
		foreach ( $defaults as $default_question ) {
			$default_by_key[ sanitize_key( $default_question['key'] ?? '' ) ] = $default_question;
		}

		$existing_by_key = array();
		foreach ( $questions as $question ) {
			$key = sanitize_key( $question['key'] ?? '' );
			if ( $key ) {
				$existing_by_key[ $key ] = $question;
			}
		}

		$merged = array();
		foreach ( $default_by_key as $key => $default_question ) {
			if ( isset( $existing_by_key[ $key ] ) ) {
				$merged[] = $this->merge_question_with_defaults( $default_question, $existing_by_key[ $key ] );
				unset( $existing_by_key[ $key ] );
			} else {
				$merged[] = $default_question;
			}
		}

		foreach ( $existing_by_key as $leftover ) {
			$merged[] = $leftover;
		}

		$sanitized_existing = $this->sanitize_questions( $questions );
		$sanitized_merged   = $this->sanitize_questions( $merged );

		if ( wp_json_encode( $sanitized_existing ) !== wp_json_encode( $sanitized_merged ) ) {
			update_option( self::OPTION_QUESTIONS, $sanitized_merged );
		}

		return $sanitized_merged;
	}

	protected function merge_question_with_defaults( $default_question, $existing_question ) {
		$merged = array_replace( (array) $default_question, (array) $existing_question );

		// Alte gespeicherte Frage-Einstellungen können einzelne Antwortoptionen verloren haben.
		// Deshalb fehlende Standard-Antworten pro Frage wieder ergänzen, ohne vorhandene Texte/Bilder zu überschreiben.
		foreach ( array( 'options', 'options_i18n', 'option_icons' ) as $field ) {
			$defaults = isset( $default_question[ $field ] ) && is_array( $default_question[ $field ] ) ? $default_question[ $field ] : array();
			$existing = isset( $existing_question[ $field ] ) && is_array( $existing_question[ $field ] ) ? $existing_question[ $field ] : array();
			$merged[ $field ] = array_replace( $defaults, $existing );
		}

		// Unterfragen/Audio für Verwendungsbereich ebenfalls mit Defaults auffüllen.
		foreach ( array( 'usage_group_icons', 'usage_stage_audio_i18n', 'audio_i18n' ) as $field ) {
			$defaults = isset( $default_question[ $field ] ) && is_array( $default_question[ $field ] ) ? $default_question[ $field ] : array();
			$existing = isset( $existing_question[ $field ] ) && is_array( $existing_question[ $field ] ) ? $existing_question[ $field ] : array();
			$merged[ $field ] = array_replace_recursive( $defaults, $existing );
		}

		return $merged;
	}

	public function update_questions( $questions ) {
		$sanitized = $this->sanitize_questions( $questions );
		update_option( self::OPTION_QUESTIONS, $sanitized );
		return $sanitized;
	}

	public function get_question_by_key( $key ) {
		$key = sanitize_key( $key );
		foreach ( $this->get_questions() as $question ) {
			if ( $key === sanitize_key( $question['key'] ?? '' ) ) {
				return $question;
			}
		}
		return null;
	}

	public function get_options_for_question( $key ) {
		$question = $this->get_question_by_key( $key );
		return is_array( $question ) && ! empty( $question['options'] ) ? (array) $question['options'] : array();
	}


	public function get_usage_group_icon( $group_key ) {
		$group_key = sanitize_key( $group_key );
		$question = $this->get_question_by_key( 'verwendungsbereich' );
		$icons = is_array( $question ) && ! empty( $question['usage_group_icons'] ) && is_array( $question['usage_group_icons'] ) ? $question['usage_group_icons'] : array();
		if ( ! empty( $icons[ $group_key ] ) ) {
			return esc_url( $icons[ $group_key ] );
		}
		return 'https://alowidat.de/wp-content/uploads/2026/05/muster.jpg';
	}


	public function get_question_groups( $key, $lang = 'de' ) {
		$key = sanitize_key( $key );

		if ( 'verwendungsbereich' === $key ) {
			return array(
				array(
					'label'   => 'Alltag',
					'options' => $this->sort_group_option_values( 'verwendungsbereich', array( 'zuhause', 'ausgehen', 'arbeit', 'buero', 'grosse_raeume' ) ),
				),
				array(
					'label'   => 'Formell / Wichtige Anlässe',
					'options' => $this->sort_group_option_values( 'verwendungsbereich', array( 'meeting', 'date', 'private_anlaesse' ) ),
				),
			);
		}

		if ( 'duftnoten' === $key ) {
			$catalog = $this->get_note_catalog();
			$groups  = array();
			foreach ( array( 'zitrusnoten', 'fruchtige_noten', 'blumige_noten', 'suesse_gourmand', 'holznoten', 'gewuerznoten', 'harze_balsame', 'aromatische_noten', 'animalische_noten', 'erdige_noten' ) as $group_key ) {
				if ( empty( $catalog[ $group_key ] ) ) {
					continue;
				}
				$groups[] = array(
					'label'       => $this->get_option_label_i18n( 'duftnoten', $group_key, $lang ),
					'options'     => array( $group_key ),
					'description' => implode( ', ', array_map( array( $this, 'get_note_label' ), (array) $catalog[ $group_key ]['notes'] ) ),
				);
			}
			return $groups;
		}

		return array();
	}

	public function get_question_text( $question, $field = 'label', $lang = 'de' ) {
		$lang = $this->normalize_lang( $lang );
		$key  = 'description' === $field ? 'description_i18n' : 'label_i18n';
		return $this->get_i18n_text( $question[ $key ] ?? array(), $lang, $question[ $field ] ?? '' );
	}

	public function get_option_label_i18n( $question_key, $value, $lang = 'de' ) {
		$question = $this->get_question_by_key( $question_key );
		$value    = sanitize_key( $value );
		if ( is_array( $question ) && ! empty( $question['options_i18n'][ $value ] ) ) {
			return $this->get_i18n_text( $question['options_i18n'][ $value ], $lang, $question['options'][ $value ] ?? '' );
		}
		$options = is_array( $question ) && ! empty( $question['options'] ) ? $question['options'] : array();
		return $options[ $value ] ?? ucfirst( str_replace( '_', ' ', $value ) );
	}

	public function get_option_label( $question_key, $value, $lang = 'de' ) {
		return $this->get_option_label_i18n( $question_key, $value, $lang );
	}


	public function get_default_option_icons() {
		// Aktueller Standard für ALLE Antwort-Bilder. Der Admin kann pro Antwort weiterhin eine eigene URL pflegen.
		$bottle = 'https://alowidat.de/wp-content/uploads/2026/05/muster.jpg';
		$defaults = array();
		foreach ( $this->get_questions() as $question ) {
			$key = sanitize_key( $question['key'] ?? '' );
			if ( ! $key ) {
				continue;
			}
			$defaults[ $key ] = array();
			foreach ( array_keys( (array) ( $question['options'] ?? array() ) ) as $value ) {
				$defaults[ $key ][ sanitize_key( $value ) ] = $bottle;
			}
		}
		return $defaults;
	}

	public function get_option_icon( $question_key, $value ) {
		$question = $this->get_question_by_key( $question_key );
		$value    = sanitize_key( $value );
		if ( is_array( $question ) && ! empty( $question['option_icons'][ $value ] ) ) {
			return esc_url_raw( $question['option_icons'][ $value ] );
		}

		$defaults = $this->get_default_option_icons();
		if ( ! empty( $defaults[ $question_key ][ $value ] ) ) {
			return esc_url_raw( $defaults[ $question_key ][ $value ] );
		}

		return '';
	}

	public function get_i18n_data_attributes( $values ) {
		$attrs = '';
		foreach ( array_keys( $this->get_supported_languages() ) as $lang ) {
			$attrs .= ' data-' . esc_attr( $lang ) . '="' . esc_attr( $this->get_i18n_text( $values, $lang ) ) . '"';
		}
		return $attrs;
	}


	public function get_note_catalog() {
		return array(
			'zitrusnoten'       => array( 'label' => 'Zitrusnoten', 'notes' => array( 'bergamotte', 'zitrone', 'limette', 'orange', 'grapefruit' ) ),
			'fruchtige_noten'   => array( 'label' => 'Fruchtige Noten', 'notes' => array( 'apfel', 'pfirsich', 'birne', 'beeren', 'mango' ) ),
			'blumige_noten'     => array( 'label' => 'Blumige Noten', 'notes' => array( 'rose', 'jasmin', 'ylang_ylang', 'iris', 'veilchen', 'tuberose', 'orangenbluete' ) ),
			'suesse_gourmand'   => array( 'label' => 'Süße / Gourmand Noten', 'notes' => array( 'vanille', 'tonkabohne', 'karamell', 'kakao', 'kastanie' ) ),
			'holznoten'         => array( 'label' => 'Holznoten', 'notes' => array( 'sandelholz', 'zedernholz', 'vetiver', 'guajakholz', 'oud' ) ),
			'gewuerznoten'      => array( 'label' => 'Gewürznoten', 'notes' => array( 'zimt', 'kardamom', 'pfeffer', 'nelke', 'muskat' ) ),
			'harze_balsame'     => array( 'label' => 'Harze / Balsame', 'notes' => array( 'benzoe', 'myrrhe', 'weihrauch', 'labdanum', 'ambra' ) ),
			'aromatische_noten' => array( 'label' => 'Aromatische Noten', 'notes' => array( 'lavendel', 'rosmarin', 'minze', 'basilikum' ) ),
			'animalische_noten' => array( 'label' => 'Animalische Noten', 'notes' => array( 'moschus', 'ambergris', 'leder' ) ),
			'erdige_noten'      => array( 'label' => 'Erdige Noten', 'notes' => array( 'patchouli', 'moos', 'erde' ) ),
		);
	}

	public function get_note_label( $note_key ) {
		$note_key = sanitize_key( $note_key );
		foreach ( $this->get_note_catalog() as $group ) {
			foreach ( (array) ( $group['notes'] ?? array() ) as $note ) {
				if ( $note_key === sanitize_key( $note ) ) {
					return ucwords( str_replace( '_', ' ', $note ) );
				}
			}
		}
		return ucwords( str_replace( '_', ' ', $note_key ) );
	}

	public function get_note_options() {
		$options = array();
		foreach ( $this->get_note_catalog() as $group ) {
			foreach ( (array) ( $group['notes'] ?? array() ) as $note ) {
				$key = sanitize_key( $note );
				$options[ $key ] = $this->get_note_label( $key );
			}
		}
		asort( $options );
		return $options;
	}

	public function get_parfum_meta_fields() {
		return array(
			'geschlecht',
			'jahreszeit',
			'verwendungsbereich',
			'raucher_geeignet',
			'alter',
			'duftrichtungen',
			'duftnoten',
			'produktlink',
			'image_url',
			'title_i18n',
			'description_i18n',
		);
	}

	public function get_parfums() {
		$posts   = get_posts(
			array(
				'post_type'      => self::POST_TYPE_PARFUM,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$parfums = array();

		foreach ( $posts as $post ) {
			$parfums[] = $this->map_parfum_post( $post );
		}

		if ( empty( $parfums ) && (int) $this->get_setting( 'enable_example_data', 1 ) === 1 ) {
			$parfums = include SY_DUFTBERATER_PATH . 'includes/data-parfums.php';
			$parfums = is_array( $parfums ) ? $parfums : array();
		}

		return $parfums;
	}

	public function map_parfum_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}

		$image = get_the_post_thumbnail_url( $post, 'large' );
		if ( ! $image ) {
			$image = esc_url_raw( get_post_meta( $post->ID, self::META_PREFIX . 'image_url', true ) );
		}


		$title_i18n = get_post_meta( $post->ID, self::META_PREFIX . 'title_i18n', true );
		$title_i18n = is_array( $title_i18n ) ? $title_i18n : array();
		$title_i18n = array(
			'de' => $title_i18n['de'] ?? $post->post_title,
			'en' => $title_i18n['en'] ?? '',
			'ar' => $title_i18n['ar'] ?? '',
		);

		$description_i18n = get_post_meta( $post->ID, self::META_PREFIX . 'description_i18n', true );
		$description_i18n = is_array( $description_i18n ) ? $description_i18n : array();
		$description_i18n = array(
			'de' => $description_i18n['de'] ?? wp_strip_all_tags( $post->post_content ),
			'en' => $description_i18n['en'] ?? '',
			'ar' => $description_i18n['ar'] ?? '',
		);

		return array(
			'id'                 => (string) $post->ID,
			'name'               => $this->get_i18n_text( $title_i18n, 'de', $post->post_title ),
			'name_i18n'          => $title_i18n,
			'geschlecht'         => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'geschlecht', true ) ) ),
			'jahreszeit'         => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'jahreszeit', true ) ) ),
			'verwendungsbereich' => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'verwendungsbereich', true ) ) ),
			'raucher_geeignet'   => (int) get_post_meta( $post->ID, self::META_PREFIX . 'raucher_geeignet', true ),
			'alter'              => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'alter', true ) ) ),
			'duftrichtungen'     => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'duftrichtungen', true ) ) ),
			'duftnoten'          => array_values( array_filter( (array) get_post_meta( $post->ID, self::META_PREFIX . 'duftnoten', true ) ) ),
			'beschreibung'       => $this->get_i18n_text( $description_i18n, 'de', wp_strip_all_tags( $post->post_content ) ),
			'beschreibung_i18n'  => $description_i18n,
			'bild'               => $image,
			'produktlink'        => esc_url_raw( get_post_meta( $post->ID, self::META_PREFIX . 'produktlink', true ) ),
		);
	}

	public function maybe_import_example_parfums( $force = false ) {
		$already_imported = (int) get_option( self::OPTION_EXAMPLES_IMPORTED, 0 );
		$existing_posts   = get_posts(
			array(
				'post_type'      => self::POST_TYPE_PARFUM,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! $force && ( $already_imported || ! empty( $existing_posts ) ) ) {
			return;
		}

		$items = include SY_DUFTBERATER_PATH . 'includes/data-parfums.php';
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$post_id = wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE_PARFUM,
					'post_status'  => 'publish',
					'post_title'   => sanitize_text_field( $item['name'] ?? '' ),
					'post_content' => wp_kses_post( $item['beschreibung'] ?? '' ),
				)
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::META_PREFIX . 'geschlecht', array_values( array_map( 'sanitize_key', (array) ( $item['geschlecht'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'jahreszeit', array_values( array_map( 'sanitize_key', (array) ( $item['jahreszeit'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'verwendungsbereich', array_values( array_map( 'sanitize_key', (array) ( $item['verwendungsbereich'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'raucher_geeignet', ! empty( $item['raucher_geeignet'] ) ? 1 : 0 );
			update_post_meta( $post_id, self::META_PREFIX . 'alter', array_values( array_map( 'sanitize_key', (array) ( $item['alter'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'duftrichtungen', array_values( array_map( 'sanitize_key', (array) ( $item['duftrichtungen'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'duftnoten', array_values( array_map( 'sanitize_key', (array) ( $item['duftnoten'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'produktlink', esc_url_raw( $item['produktlink'] ?? '' ) );
			update_post_meta( $post_id, self::META_PREFIX . 'image_url', esc_url_raw( $item['bild'] ?? '' ) );
			update_post_meta( $post_id, self::META_PREFIX . 'title_i18n', array(
				'de' => sanitize_text_field( $item['name'] ?? '' ),
				'en' => sanitize_text_field( $item['name_en'] ?? '' ),
				'ar' => sanitize_text_field( $item['name_ar'] ?? '' ),
			) );
			update_post_meta( $post_id, self::META_PREFIX . 'description_i18n', array(
				'de' => wp_kses_post( $item['beschreibung'] ?? '' ),
				'en' => wp_kses_post( $item['beschreibung_en'] ?? '' ),
				'ar' => wp_kses_post( $item['beschreibung_ar'] ?? '' ),
			) );
		}


		update_option( self::OPTION_EXAMPLES_IMPORTED, 1 );
	}


	public function maybe_sync_dataset() {
		if ( ! defined( 'SY_DUFTBERATER_DATASET_VERSION' ) ) {
			return;
		}

		if ( (int) $this->get_setting( 'enable_example_data', 1 ) !== 1 ) {
			return;
		}

		$current_version = (string) get_option( self::OPTION_DATASET_VERSION, '' );
		if ( SY_DUFTBERATER_DATASET_VERSION === $current_version ) {
			return;
		}

		// Ab Version 1.28.12 wird die Matrix sicher synchronisiert:
		// keine Massenlöschung, keine PDF-Regenerierung, nur Update/Anlage anhand der Duftnummer.
		$this->sync_default_dataset_parfums();
		update_option( self::OPTION_EXAMPLES_IMPORTED, 1 );
		update_option( self::OPTION_DATASET_VERSION, SY_DUFTBERATER_DATASET_VERSION );
	}

	protected function sync_default_dataset_parfums() {
		$items = include SY_DUFTBERATER_PATH . 'includes/data-parfums.php';
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			$name = sanitize_text_field( $item['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}

			$existing = get_page_by_title( $name, OBJECT, self::POST_TYPE_PARFUM );

			$postarr = array(
				'post_type'    => self::POST_TYPE_PARFUM,
				'post_status'  => 'publish',
				'post_title'   => $name,
				'post_content' => wp_kses_post( $item['beschreibung'] ?? '' ),
			);

			if ( $existing ) {
				$postarr['ID'] = $existing->ID;
				$post_id = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			update_post_meta( $post_id, self::META_PREFIX . 'geschlecht', array_values( array_map( 'sanitize_key', (array) ( $item['geschlecht'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'jahreszeit', array_values( array_map( 'sanitize_key', (array) ( $item['jahreszeit'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'verwendungsbereich', array_values( array_map( 'sanitize_key', (array) ( $item['verwendungsbereich'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'raucher_geeignet', ! empty( $item['raucher_geeignet'] ) ? 1 : 0 );
			update_post_meta( $post_id, self::META_PREFIX . 'alter', array_values( array_map( 'sanitize_key', (array) ( $item['alter'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'duftrichtungen', array_values( array_map( 'sanitize_key', (array) ( $item['duftrichtungen'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'duftnoten', array_values( array_map( 'sanitize_key', (array) ( $item['duftnoten'] ?? array() ) ) ) );
			update_post_meta( $post_id, self::META_PREFIX . 'produktlink', esc_url_raw( $item['produktlink'] ?? '' ) );
			update_post_meta( $post_id, self::META_PREFIX . 'image_url', esc_url_raw( $item['bild'] ?? '' ) );
			update_post_meta( $post_id, self::META_PREFIX . 'title_i18n', array(
				'de' => sanitize_text_field( $item['name'] ?? '' ),
				'en' => sanitize_text_field( $item['name_en'] ?? '' ),
				'ar' => sanitize_text_field( $item['name_ar'] ?? '' ),
			) );
			update_post_meta( $post_id, self::META_PREFIX . 'description_i18n', array(
				'de' => wp_kses_post( $item['beschreibung'] ?? '' ),
				'en' => wp_kses_post( $item['beschreibung_en'] ?? '' ),
				'ar' => wp_kses_post( $item['beschreibung_ar'] ?? '' ),
			) );
		}
	}

	protected function purge_all_parfums() {
		$post_ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE_PARFUM,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $post_ids as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}

	public function get_stats() {
		return array(
			'question_count' => count( $this->get_questions() ),
			'parfum_count'   => count( $this->get_parfums() ),
		);
	}
}
