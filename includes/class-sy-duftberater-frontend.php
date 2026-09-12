<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SY_Duftberater_Frontend {

	protected $plugin;
	protected $matcher;

	public function __construct( $plugin, $matcher ) {
		$this->plugin  = $plugin;
		$this->matcher = $matcher;
	}

	public function init() {
		add_shortcode( 'sy_duftberater', array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'wp_ajax_sy_duftberater_match', array( $this, 'ajax_match' ) );
		add_action( 'wp_ajax_nopriv_sy_duftberater_match', array( $this, 'ajax_match' ) );
		add_action( 'wp_ajax_sy_duftberater_lead_pdf', array( $this, 'ajax_lead_pdf' ) );
		add_action( 'wp_ajax_nopriv_sy_duftberater_lead_pdf', array( $this, 'ajax_lead_pdf' ) );
		add_action( 'wp_ajax_sy_duftberater_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_sy_duftberater_refresh_nonce', array( $this, 'ajax_refresh_nonce' ) );
	}

	public function register_assets() {
		wp_register_style( 'sy-duftberater', SY_DUFTBERATER_URL . 'assets/css/sy-duftberater.css', array(), SY_DUFTBERATER_VERSION );
		wp_register_script( 'sy-duftberater', SY_DUFTBERATER_URL . 'assets/js/sy-duftberater.js', array(), SY_DUFTBERATER_VERSION, true );
	}

	public function render_shortcode() {
		// Der Duftberater enthält einen zeitabhängigen WordPress-Nonce. Seiten-Caches dürfen
		// deshalb keine alte Nonce dauerhaft ausliefern. Viele Cache-Plugins respektieren
		// DONOTCACHEPAGE; zusätzlich werden No-Cache-Header gesetzt, solange noch möglich.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}

		wp_enqueue_style( 'sy-duftberater' );
		wp_enqueue_script( 'sy-duftberater' );

		$settings  = $this->plugin->get_settings();
		$questions = $this->plugin->get_questions();
		$result_audio = isset( $settings['result_audio_i18n'] ) && is_array( $settings['result_audio_i18n'] ) ? $settings['result_audio_i18n'] : array();
		$result_audio = array(
			'de' => isset( $result_audio['de'] ) ? esc_url_raw( $result_audio['de'] ) : '',
			'en' => isset( $result_audio['en'] ) ? esc_url_raw( $result_audio['en'] ) : '',
			'ar' => isset( $result_audio['ar'] ) ? esc_url_raw( $result_audio['ar'] ) : '',
		);

		$completion_audio = isset( $settings['completion_audio_i18n'] ) && is_array( $settings['completion_audio_i18n'] ) ? $settings['completion_audio_i18n'] : array();
		$completion_audio = array(
			'de' => isset( $completion_audio['de'] ) ? esc_url_raw( $completion_audio['de'] ) : '',
			'en' => isset( $completion_audio['en'] ) ? esc_url_raw( $completion_audio['en'] ) : '',
			'ar' => isset( $completion_audio['ar'] ) ? esc_url_raw( $completion_audio['ar'] ) : '',
		);

		wp_localize_script(
			'sy-duftberater',
			'syDuftberaterData',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'sy_duftberater_nonce' ),
				'nonceAction' => 'sy_duftberater_refresh_nonce',
				'resultCount' => 3,
				'ui'          => $this->plugin->get_ui_texts_for_js(),
				'languages'   => $this->plugin->get_supported_languages(),
				'logoUrl'     => isset( $settings['pdf_logo_url'] ) ? esc_url_raw( $settings['pdf_logo_url'] ) : '',
				'autoDownloadEnabled' => isset( $settings['auto_download_enabled'] ) ? ( ! empty( $settings['auto_download_enabled'] ) ? 1 : 0 ) : 1,
				'audio'       => array(
					'disabled'          => ! empty( $settings['audio_disabled'] ) ? 1 : 0,
					'clickSoundEnabled' => ! empty( $settings['click_sound_enabled'] ) ? 1 : 0,
					'clickSoundUrl'     => isset( $settings['click_sound_url'] ) ? esc_url_raw( $settings['click_sound_url'] ) : '',
					'result'            => $result_audio,
					'completion'        => $completion_audio,
				),
			)
		);

		ob_start();
		include SY_DUFTBERATER_PATH . 'templates/quiz.php';
		return ob_get_clean();
	}

	public function ajax_refresh_nonce() {
		// Absichtlich ohne check_ajax_referer(): dieser Endpunkt wird genau dann benötigt,
		// wenn eine gecachte Seite bereits eine abgelaufene Nonce enthält.
		nocache_headers();
		wp_send_json_success( array(
			'nonce' => wp_create_nonce( 'sy_duftberater_nonce' ),
		) );
	}

	protected function sanitize_answers_from_request() {
		return array(
			'geschlecht'         => sanitize_key( wp_unslash( $_POST['geschlecht'] ?? '' ) ),
			'jahreszeit'         => sanitize_key( wp_unslash( $_POST['jahreszeit'] ?? '' ) ),
			'verwendungsbereich' => sanitize_key( wp_unslash( $_POST['verwendungsbereich'] ?? '' ) ),
			'raucher'            => sanitize_key( wp_unslash( $_POST['raucher'] ?? '' ) ),
			'alter'              => sanitize_key( wp_unslash( $_POST['alter'] ?? '' ) ),
			'duftrichtungen'     => array_map( 'sanitize_key', (array) wp_unslash( $_POST['duftrichtungen'] ?? array() ) ),
			'duftnoten'          => array_map( 'sanitize_key', (array) wp_unslash( $_POST['duftnoten'] ?? array() ) ),
			'lang'               => sanitize_key( wp_unslash( $_POST['lang'] ?? 'de' ) ),
		);
	}

	public function ajax_match() {
		check_ajax_referer( 'sy_duftberater_nonce', 'nonce' );

		$answers = $this->sanitize_answers_from_request();
		$lang    = $this->plugin->normalize_lang( $answers['lang'] ?? 'de' );
		$results = $this->matcher->get_results( $answers );
		$html    = '';

		if ( ! empty( $results ) ) {
			foreach ( $results as $index => $item ) {
				$parfum     = $item['parfum'];
				$reason     = $item['reason'];
				$score      = $item['score'];
				$percentage = (int) ( $item['percentage'] ?? 0 );
				$rank       = (int) $index + 1;

				ob_start();
				include SY_DUFTBERATER_PATH . 'templates/result-card.php';
				$html .= ob_get_clean();
			}
		} else {
			$html = '<div class="sy-duftberater-empty">' . esc_html( $this->plugin->get_ui_text( 'empty_text', $lang ) ) . '</div>';
		}

		wp_send_json_success( array( 'html' => $html ) );
	}

	public function ajax_lead_pdf() {
		check_ajax_referer( 'sy_duftberater_nonce', 'nonce' );
		$name  = sanitize_text_field( wp_unslash( $_POST['lead_name'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['lead_email'] ?? '' ) );
		$answers = $this->sanitize_answers_from_request();
		$lang = $this->plugin->normalize_lang( $answers['lang'] ?? 'de' );

		if ( '' === $name || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => $this->plugin->get_ui_text( 'lead_error', $lang ) ) );
		}

		$results = array_slice( $this->matcher->get_results( $answers ), 0, 3 );
		if ( empty( $results ) ) {
			wp_send_json_error( array( 'message' => $this->plugin->get_ui_text( 'empty_text', $lang ) ) );
		}

		$lead_id = wp_insert_post( array(
			'post_type'   => SY_Duftberater::POST_TYPE_LEAD,
			'post_status' => 'private',
			'post_title'  => sprintf( '%s - %s', $name, current_time( 'mysql' ) ),
		) );
		if ( is_wp_error( $lead_id ) || ! $lead_id ) {
			wp_send_json_error( array( 'message' => $this->plugin->get_ui_text( 'ajax_exception_text', $lang ) ) );
		}

		$pdf_items = array();
		foreach ( $results as $idx => $item ) {
			$parfum = $item['parfum'];
			$title_i18n = $parfum['name_i18n'] ?? array();
			$desc_i18n  = $parfum['beschreibung_i18n'] ?? array();
			$title      = $this->plugin->get_i18n_text( $title_i18n, $lang, $parfum['name'] ?? '' );
			$desc       = $this->plugin->get_i18n_text( $desc_i18n, $lang, $parfum['beschreibung'] ?? '' );
			// For Arabic PDFs do not fall back to German product descriptions.
			// If the Arabic product text is not maintained yet, keep the card clean instead of showing mixed DE/AR text.
			if ( 'ar' === $lang ) {
				$title = ! empty( $title_i18n['ar'] ) ? $title_i18n['ar'] : ( $parfum['name'] ?? '' );
				$desc  = ! empty( $desc_i18n['ar'] ) ? $desc_i18n['ar'] : '';
			}
			$pdf_items[] = array(
				'rank'       => $idx + 1,
				'title'      => $title,
				'description'=> $desc,
				'percentage' => (int) ( $item['percentage'] ?? 0 ),
				'link'       => esc_url_raw( $parfum['produktlink'] ?? '' ),
				'image'      => esc_url_raw( $parfum['bild'] ?? '' ),
			);
		}

		$pdf = $this->create_lead_pdf( $lead_id, $name, $email, $lang, $answers, $pdf_items );

		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_name', $name );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_email', $email );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_lang', $lang );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_answers', $answers );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_results', $pdf_items );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_pdf_url', $pdf['url'] ?? '' );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_pdf_path', $pdf['path'] ?? '' );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'coupon_used', 0 );

		$attachments = ! empty( $pdf['path'] ) && file_exists( $pdf['path'] ) ? array( $pdf['path'] ) : array();
		$replacements = array( 'name' => $name, 'email' => $email, 'pdf_url' => ( $pdf['url'] ?? '' ) );
		$subject = $this->sydb_replace_placeholders( $this->plugin->get_ui_text( 'mail_subject', $lang ), $replacements );

		// Mail-Inhalt bewusst aus dem Backend-Feld "Mail – Inhalt (Normaltext)" übernehmen.
		// Das alte HTML-Standardfeld darf den gepflegten Backend-Text nicht mehr überschreiben.
		$mail_body_raw = $this->sydb_replace_placeholders( $this->plugin->get_ui_text( 'mail_body_text', $lang ), $replacements );
		if ( empty( $attachments ) && ! empty( $pdf['url'] ) && false === strpos( $mail_body_raw, (string) $pdf['url'] ) ) {
			$mail_body_raw .= "\n\n" . $this->plugin->get_ui_text( 'lead_pdf_link', $lang ) . ': ' . (string) $pdf['url'];
		}
		$mail_plain = $this->sydb_html_to_text( $mail_body_raw );
		$mail_html  = $this->sydb_email_body_to_html( $mail_body_raw, $lang );
		wp_mail( $email, $subject, $mail_html, array( 'Content-Type: text/html; charset=UTF-8' ), $attachments );

		wp_send_json_success( array(
			'message' => $this->plugin->get_ui_text( 'lead_success', $lang ),
			'pdfUrl'  => $pdf['url'] ?? '',
			'pdfText' => $this->plugin->get_ui_text( 'lead_pdf_download', $lang ),
			'pdfDownload' => true,
		) );
	}

	protected function sydb_replace_placeholders( $content, $replacements = array() ) {
		$content = (string) $content;
		foreach ( (array) $replacements as $key => $value ) {
			$content = str_replace( '{' . $key . '}', (string) $value, $content );
		}
		return $content;
	}

	protected function sydb_email_body_to_html( $content, $lang = 'de' ) {
		$content = (string) $content;
		$dir = ( 'ar' === $lang ) ? 'rtl' : 'ltr';
		$align = ( 'ar' === $lang ) ? 'right' : 'left';
		$style = 'direction:' . $dir . ';text-align:' . $align . ';font-family:Arial,Tahoma,sans-serif;line-height:1.8;font-size:15px;';
		if ( '' === trim( $content ) ) {
			return '<div dir="' . esc_attr( $dir ) . '" style="' . esc_attr( $style ) . '"></div>';
		}

		$looks_like_html = (bool) preg_match( '/<\s*[a-zA-Z][^>]*>/', $content );
		if ( $looks_like_html ) {
			$body = wp_kses_post( $content );
		} else {
			$body = nl2br( esc_html( $content ), false );
		}

		return '<div dir="' . esc_attr( $dir ) . '" style="' . esc_attr( $style ) . '">' . $body . '</div>';
	}

	protected function sydb_html_to_text( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = preg_replace( '#<style[^>]*>.*?</style>#is', '', $html );
		$html = preg_replace( '#<script[^>]*>.*?</script>#is', '', $html );
		$html = wp_kses_post( $html );
		// Keep simple HTML structure visible in the GD-PDF renderer. Bootstrap/CSS itself cannot be rendered by GD,
		// but headings, small text, paragraphs, lists and badges become clean PDF lines.
		$html = preg_replace( '#<(br|BR)\s*/?>#', "\n", $html );
		$html = preg_replace( '#</(h1|h2|h3|h4|h5|h6|small)>#i', "\n", $html );
		$html = preg_replace( '#</(p|div|section|article|li)>#i', "\n", $html );
		$html = preg_replace( '#<li[^>]*>#i', '• ', $html );
		$text = wp_strip_all_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/u", ' ', $text );
		$text = preg_replace( "/\n[ \t]+/u", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( $text );
	}

	protected function sydb_get_preferred_text( $plain_key, $html_key, $lang, $replacements = array() ) {
		$plain = $this->sydb_replace_placeholders( $this->plugin->get_ui_text( $plain_key, $lang ), $replacements );
		$settings = $this->plugin->get_settings();
		$defaults = $this->plugin->get_default_html_i18n_texts();
		$current_html = isset( $settings['html_i18n'][ $html_key ][ $lang ] ) ? (string) $settings['html_i18n'][ $html_key ][ $lang ] : '';
		$default_html = isset( $defaults[ $html_key ][ $lang ] ) ? (string) $defaults[ $html_key ][ $lang ] : '';

		// PDF Body/Footer Manager hat Vorrang: alles, was dort gespeichert ist, soll sichtbar in die PDF gehen.
		// Wenn das Feld leer ist, fällt das Plugin auf den normalen Text zurück.
		if ( '' !== trim( $current_html ) ) {
			$html = $this->sydb_replace_placeholders( $current_html, $replacements );
			$converted = $this->sydb_html_to_text( $html );
			if ( '' !== trim( $converted ) ) {
				return $converted;
			}
		}
		return $plain;
	}

	public function regenerate_existing_lead_pdfs() {
		if ( ! function_exists( 'get_posts' ) ) {
			return 0;
		}
		$lead_ids = get_posts( array(
			'post_type'      => SY_Duftberater::POST_TYPE_LEAD,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'DESC',
		) );
		$count = 0;
		foreach ( (array) $lead_ids as $lead_id ) {
			if ( $this->regenerate_lead_pdf_for_lead( (int) $lead_id ) ) {
				$count++;
			}
		}
		return $count;
	}

	public function regenerate_lead_pdf_for_lead( $lead_id ) {
		$lead_id = absint( $lead_id );
		if ( ! $lead_id || SY_Duftberater::POST_TYPE_LEAD !== get_post_type( $lead_id ) ) {
			return false;
		}
		$name    = (string) get_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_name', true );
		$email   = (string) get_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_email', true );
		$lang    = (string) get_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_lang', true );
		$answers = get_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_answers', true );
		$items   = get_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_results', true );
		$lang    = $this->plugin->normalize_lang( $lang );
		$answers = is_array( $answers ) ? $answers : array();
		$items   = is_array( $items ) ? $items : array();
		if ( empty( $items ) ) {
			return false;
		}
		$pdf = $this->create_lead_pdf( $lead_id, $name, $email, $lang, $answers, $items );
		if ( empty( $pdf['path'] ) || ! file_exists( $pdf['path'] ) ) {
			return false;
		}
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_pdf_url', $pdf['url'] ?? '' );
		update_post_meta( $lead_id, SY_Duftberater::META_PREFIX . 'lead_pdf_path', $pdf['path'] ?? '' );
		return true;
	}

	protected function create_lead_pdf( $lead_id, $name, $email, $lang, $answers, $items ) {
		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] ) . 'sy-duftberater-pdfs';
		$url = trailingslashit( $upload['baseurl'] ) . 'sy-duftberater-pdfs';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		$filename = 'duftberater-' . absint( $lead_id ) . '-' . wp_generate_password( 8, false, false ) . '.pdf';
		$path = trailingslashit( $dir ) . $filename;
		$file_url = trailingslashit( $url ) . $filename;

		$data = array(
			'name'         => $name,
			'email'        => $email,
			'lang'         => $lang,
			'match_label'  => $this->plugin->get_ui_text( 'match_label', $lang ),
			'pdf_template' => method_exists( $this->plugin, 'get_pdf_template' ) ? $this->plugin->get_pdf_template( $lang ) : array(),
			'items'        => $items,
		);

		$pdf = $this->build_modern_pdf( $data );
		file_put_contents( $path, $pdf );
		return array( 'path' => $path, 'url' => $file_url );
	}

	protected function build_modern_pdf( $data ) {
		// v1.22.4: render the whole recommendation as a high quality JPEG page inside the PDF.
		// This avoids WinAnsi/PDF core-font issues, fixes German umlauts and prevents Arabic from becoming ?????.
		if ( function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) ) {
			$image_pdf = $this->build_modern_image_pdf( $data );
			if ( ! empty( $image_pdf ) ) {
				return $image_pdf;
			}
		}

		$is_rtl = ( isset( $data['lang'] ) && 'ar' === $data['lang'] );
		$objects = array();
		$images  = array();
		$draw    = '';
		$text    = '';

		// Premium colors inspired by the frontend light theme.
		$cream      = array( 0.973, 0.944, 0.886 ); // warm page background
		$panel      = array( 1.000, 0.985, 0.950 ); // card surface
		$panel2     = array( 0.992, 0.965, 0.910 );
		$gold       = array( 0.780, 0.620, 0.300 );
		$gold_dark  = array( 0.545, 0.410, 0.180 );
		$burgundy   = array( 0.518, 0.071, 0.106 );
		$text_dark  = array( 0.145, 0.105, 0.070 );
		$text_soft  = array( 0.390, 0.330, 0.250 );

		// Page background.
		$draw .= $this->pdf_rgb( $cream ) . " rg\n0 0 595 842 re f\n";
		$draw .= $this->pdf_rgb( $panel ) . " rg\n24 28 547 786 re f\n";
		// soft top hero band
		$draw .= $this->pdf_rgb( $burgundy ) . " rg\n24 744 547 70 re f\n";
		$draw .= $this->pdf_rgb( $gold ) . " rg\n24 742 547 6 re f\n";
		$draw .= $this->pdf_rgb( $gold ) . " RG\n24 28 547 786 re S\n";

		$logo_name = $this->pdf_prepare_image_xobject( $data['logo_url'] ?? '', $images, 1 );
		if ( $logo_name ) {
			$draw .= "q 118 0 0 46 45 757 cm /" . $logo_name . " Do Q\n";
		}

		// Hero text: use light text on dark banner.
		$text .= "BT\n";
		$text .= $this->pdf_set_color( array( 1, 0.970, 0.900 ) );
		$text .= "ET\n";

		// Intro panel.
		$draw .= $this->pdf_rgb( $panel2 ) . " rg\n42 635 511 92 re f\n";
		$draw .= $this->pdf_rgb( $gold ) . " RG\n42 635 511 92 re S\n";
		$text .= "BT\n";
		$text .= $this->pdf_set_color( $text_dark );
		$text .= $this->pdf_text_line( $data['greeting'] ?? '', 58, 704, 12, true, 480, $is_rtl );
		$text .= "ET\n";

		// Editable body block without a fixed discount badge.
		$text .= "BT\n";
		$body_lines = $this->pdf_wrap_text_with_breaks( $data['body'] ?? ( $data['coupon'] ?? '' ), $is_rtl ? 58 : 66 );
		$text .= $this->pdf_set_color( $text_dark );
		$body_y = 692;
		foreach ( array_slice( $body_lines, 0, 5 ) as $line ) {
			if ( '' === trim( $line ) ) { $body_y -= 8; continue; }
			$text .= $this->pdf_text_line( $line, 58, $body_y, 9, false, 480, $is_rtl );
			$body_y -= 12;
		}
		$text .= "ET\n";

		// Cards layout: three modern vertical cards like the frontend result cards.
		$card_w = 165;
		$card_h = 365;
		$card_y = 222;
		$xs = $is_rtl ? array( 392, 215, 38 ) : array( 38, 215, 392 );
		$items = array_values( (array) ( $data['items'] ?? array() ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$item = $items[ $i ] ?? array();
			$x = $xs[ $i ];
			$rank = isset( $item['rank'] ) ? (int) $item['rank'] : ( $i + 1 );
			$percentage = isset( $item['percentage'] ) ? (int) $item['percentage'] : 0;

			// card without shadow
			$draw .= $this->pdf_rgb( $panel ) . " rg\n" . $x . " " . $card_y . " " . $card_w . " " . $card_h . " re f\n";
			$draw .= $this->pdf_rgb( $gold ) . " RG\n" . $x . " " . $card_y . " " . $card_w . " " . $card_h . " re S\n";

			// rank ribbon
			$draw .= $this->pdf_rgb( $burgundy ) . " rg\n" . ( $x + 12 ) . " " . ( $card_y + 332 ) . " 72 20 re f\n";
			$draw .= $this->pdf_rgb( $gold ) . " rg\n" . ( $x + 92 ) . " " . ( $card_y + 332 ) . " 58 20 re f\n";

			$text .= "BT\n";
			$text .= $this->pdf_set_color( array( 1, 1, 1 ) );
			$text .= $this->pdf_text_line( 'TOP ' . $rank, $x + 22, $card_y + 339, 9, true, 60, false );
			$text .= $this->pdf_set_color( $text_dark );
			$text .= $this->pdf_text_line( $percentage . '%', $x + 105, $card_y + 339, 9, true, 45, false );
			$text .= "ET\n";

			$img_name = $this->pdf_prepare_image_xobject( $item['image'] ?? '', $images, count( $images ) + 1 );
			if ( $img_name ) {
				$draw .= "q 128 0 0 128 " . ( $x + 18 ) . " " . ( $card_y + 190 ) . " cm /" . $img_name . " Do Q\n";
			} else {
				$draw .= $this->pdf_rgb( array( 0.950, 0.910, 0.820 ) ) . " rg\n" . ( $x + 18 ) . " " . ( $card_y + 190 ) . " 128 128 re f\n";
			}

			// match progress bar
			$bar_w = max( 16, min( 128, $percentage * 1.28 ) );
			$draw .= $this->pdf_rgb( array( 0.895, 0.850, 0.750 ) ) . " rg\n" . ( $x + 18 ) . " " . ( $card_y + 176 ) . " 128 5 re f\n";
			$draw .= $this->pdf_rgb( $gold ) . " rg\n" . ( $x + 18 ) . " " . ( $card_y + 176 ) . " " . $bar_w . " 5 re f\n";

			$text .= "BT\n";
			$text .= $this->pdf_set_color( $text_dark );
			$text .= $this->pdf_text_line( $item['title'] ?? '', $x + 16, $card_y + 154, 14, true, 133, $is_rtl );
			$text .= $this->pdf_set_color( $text_soft );
			$desc_lines = $this->pdf_wrap_text( $item['description'] ?? '', $is_rtl ? 24 : 29 );
			$yy = $card_y + 132;
			foreach ( array_slice( $desc_lines, 0, 7 ) as $line ) {
				$text .= $this->pdf_text_line( $line, $x + 16, $yy, 8.5, false, 133, $is_rtl );
				$yy -= 12;
			}
			$text .= "ET\n";

			// modern CTA button at bottom.
			$draw .= $this->pdf_rgb( $burgundy ) . " rg\n" . ( $x + 16 ) . " " . ( $card_y + 20 ) . " 133 28 re f\n";
			$draw .= $this->pdf_rgb( $gold ) . " rg\n" . ( $x + 16 ) . " " . ( $card_y + 48 ) . " 133 3 re f\n";
			$text .= "BT\n";
			$text .= $this->pdf_set_color( array( 1, 0.965, 0.880 ) );
			$text .= $this->pdf_text_line( $data['product_btn'] ?? 'Produkt ansehen', $x + 31, $card_y + 30, 9, true, 110, $is_rtl );
			$text .= "ET\n";
		}

		// footer
		$draw .= $this->pdf_rgb( $burgundy ) . " rg\n42 86 511 46 re f\n";
		$text .= "BT\n";
		$text .= $this->pdf_set_color( array( 1, 0.955, 0.840 ) );
		$footer_lines = $this->pdf_wrap_text_with_breaks( $data['footer'] ?? '', $is_rtl ? 44 : 54 );
		$footer_y = 108;
		foreach ( array_slice( $footer_lines, 0, 2 ) as $line ) {
			if ( '' === trim( $line ) ) { $footer_y -= 10; continue; }
			$text .= $this->pdf_text_line( $line, 58, $footer_y, 11, true, 480, $is_rtl );
			$footer_y -= 12;
		}
		$text .= "ET\n";

		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$resources = '<< /Font << /F1 4 0 R /F2 5 0 R >>';
		if ( ! empty( $images ) ) {
			$resources .= ' /XObject << ';
			foreach ( $images as $name => $img ) {
				$resources .= '/' . $name . ' ' . $img['obj'] . ' 0 R ';
			}
			$resources .= '>>';
		}
		$resources .= ' >>';
		$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources ' . $resources . ' /Contents 6 0 R >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
		$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
		$stream = $draw . $text;
		$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";

		foreach ( $images as $name => $img ) {
			$objects[] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $img['w'] . ' /Height ' . (int) $img['h'] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $img['data'] ) . " >>\nstream\n" . $img['data'] . "\nendstream";
		}

		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 );
		foreach ( $objects as $i => $obj ) {
			$offsets[] = strlen( $pdf );
			$pdf .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n";
		$pdf .= "0000000000 65535 f \n";
		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $pdf;
	}

	protected function pdf_rgb( $rgb ) {
		return rtrim( rtrim( sprintf( '%.3F', $rgb[0] ), '0' ), '.' ) . ' ' . rtrim( rtrim( sprintf( '%.3F', $rgb[1] ), '0' ), '.' ) . ' ' . rtrim( rtrim( sprintf( '%.3F', $rgb[2] ), '0' ), '.' );
	}

	protected function pdf_set_color( $rgb ) {
		return $this->pdf_rgb( $rgb ) . " rg\n";
	}

	protected function pdf_prepare_image_xobject( $url, &$images, $index ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url || ! function_exists( 'wp_remote_get' ) || ! function_exists( 'imagecreatefromstring' ) ) { return ''; }
		$response = wp_remote_get( $url, array( 'timeout' => 8 ) );
		if ( is_wp_error( $response ) ) { return ''; }
		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) { return ''; }
		$im = @imagecreatefromstring( $body );
		if ( ! $im ) { return ''; }
		$w = imagesx( $im ); $h = imagesy( $im );
		$canvas = imagecreatetruecolor( $w, $h );
		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		imagefilledrectangle( $canvas, 0, 0, $w, $h, $white );
		imagecopy( $canvas, $im, 0, 0, 0, 0, $w, $h );
		ob_start(); imagejpeg( $canvas, null, 82 ); $jpg = ob_get_clean();
		imagedestroy( $im ); imagedestroy( $canvas );
		$name = 'Im' . (int) $index;
		$images[ $name ] = array( 'w' => $w, 'h' => $h, 'data' => $jpg, 'obj' => 6 + count( $images ) + 1 );
		return $name;
	}

	protected function pdf_text_line( $text, $x, $y, $size = 11, $bold = false, $width = 450, $rtl = false ) {
		$text = wp_strip_all_tags( (string) $text );
		if ( $rtl ) { $text = $this->pdf_simple_rtl_text( $text ); }
		$text = $this->pdf_escape( $text );
		$font = $bold ? '/F2' : '/F1';
		return $font . ' ' . (int) $size . " Tf\n1 0 0 1 " . (float) $x . ' ' . (float) $y . ' Tm (' . $text . ") Tj\n";
	}

	protected function pdf_simple_rtl_text( $text ) {
		// Built-in PDF fonts cannot shape Arabic reliably; keep readable fallback where possible.
		return (string) $text;
	}

	protected function pdf_escape( $text ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text );
			if ( false !== $converted ) { $text = $converted; }
		}
		$text = preg_replace( '/[^\x09\x0A\x0D\x20-\x7E\x80-\xFF]/', '', $text );
		return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $text );
	}

	protected function pdf_wrap_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text ) { return array( '' ); }
		$words = preg_split( '/\s+/u', $text );
		$lines = array(); $line = '';
		foreach ( $words as $word ) {
			$len = function_exists( 'mb_strlen' ) ? mb_strlen( $line . ' ' . $word ) : strlen( $line . ' ' . $word );
			if ( $len > $limit && '' !== $line ) { $lines[] = $line; $line = $word; }
			else { $line = '' === $line ? $word : $line . ' ' . $word; }
		}
		if ( '' !== $line ) { $lines[] = $line; }
		return $lines;
	}


	protected function richtext_to_plain( $text ) {
		$text = (string) $text;
		$text = preg_replace( '#<\s*br\s*/?\s*>#i', "\n", $text );
		$text = preg_replace( '#<\s*/p\s*>#i', "\n\n", $text );
		$text = preg_replace( '#<\s*p[^>]*>#i', '', $text );
		$text = preg_replace( '#<\s*/div\s*>#i', "\n", $text );
		$text = preg_replace( '#<\s*div[^>]*>#i', '', $text );
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
		// Sichtbare \\n oder /n aus Admin-Feldern vor dem Rendern in echte Zeilenumbrüche umwandeln.
		$text = str_replace( array( '\\n', '/n' ), "\n", $text );
		$text = preg_replace( "/\r\n?|\n/u", "\n", $text );
		$text = preg_replace( "/[\t ]+\n/u", "\n", $text );
		$text = preg_replace( "/\n{3,}/u", "\n\n", $text );
		return trim( $text );
	}

	protected function pdf_wrap_text_with_breaks( $text, $limit ) {
		$text = $this->richtext_to_plain( $text );
		if ( '' === $text ) { return array( '' ); }
		$paragraphs = preg_split( "/\n/u", $text );
		$lines = array();
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( preg_replace( '/\s+/u', ' ', $paragraph ) );
			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}
			foreach ( $this->pdf_wrap_text( $paragraph, $limit ) as $line ) {
				$lines[] = $line;
			}
		}
		while ( ! empty( $lines ) && '' === end( $lines ) ) { array_pop( $lines ); }
		return ! empty( $lines ) ? $lines : array( '' );
	}

	protected function gd_wrap_lines_with_breaks( $text, $font, $size, $max_width, $rtl = false ) {
		$text = $this->richtext_to_plain( $text );
		if ( '' === $text ) { return array( '' ); }
		$paragraphs = preg_split( "/\n/u", $text );
		$lines = array();
		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( preg_replace( '/\s+/u', ' ', $paragraph ) );
			if ( '' === $paragraph ) {
				$lines[] = '';
				continue;
			}
			foreach ( $this->gd_wrap_lines( $paragraph, $font, $size, $max_width, $rtl ) as $line ) {
				$lines[] = $line;
			}
		}
		while ( ! empty( $lines ) && '' === end( $lines ) ) { array_pop( $lines ); }
		return ! empty( $lines ) ? $lines : array( '' );
	}

	protected function gd_multiline_text_block( $im, $text, $x, $y, $size, $color, $font, $align = 'left', $rtl = false, $max_width = 700, $line_height = 32, $max_lines = 8, $latin_font = null ) {
		$lines = $this->gd_wrap_lines_with_breaks( $text, $font, $size, $max_width, $rtl );
		$lines = array_slice( $lines, 0, $max_lines );
		foreach ( $lines as $i => $line ) {
			if ( '' === $line ) { continue; }
			if ( $rtl && $latin_font ) {
				$this->gd_text_mixed_fonts( $im, $line, $x, $y + ( $i * $line_height ), $size, $color, $font, $latin_font, $align, $rtl, $max_width );
			} else {
				$this->gd_text( $im, $line, $x, $y + ( $i * $line_height ), $size, $color, $font, $align, $rtl, $max_width );
			}
		}
	}

	protected function gd_text_mixed_fonts( $im, $text, $x, $y, $size, $color, $arabic_font, $latin_font, $align = 'left', $rtl = false, $max_width = 900 ) {
		$visual = $this->prepare_gd_text( $text, $rtl );
		$segments = $this->gd_split_visual_font_segments( $visual );
		$total_width = 0;
		foreach ( $segments as $segment ) {
			$seg_font = $segment['latin'] ? $latin_font : $arabic_font;
			$total_width += $this->gd_raw_text_width( $segment['text'], $size, $seg_font );
		}
		if ( 'center' === $align ) {
			$x = $x - ( $total_width / 2 );
		} elseif ( 'right' === $align ) {
			$x = $x - $total_width;
		}
		$cursor = (int) $x;
		foreach ( $segments as $segment ) {
			$seg_text = $segment['text'];
			if ( '' === $seg_text ) { continue; }
			$seg_font = $segment['latin'] ? $latin_font : $arabic_font;
			imagettftext( $im, $size, 0, (int) $cursor, (int) $y, $color, $seg_font, $seg_text );
			$cursor += $this->gd_raw_text_width( $seg_text, $size, $seg_font );
		}
	}

	protected function gd_split_visual_font_segments( $text ) {
		$text = (string) $text;
		if ( '' === $text ) { return array(); }
		$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$segments = array();
		$current = '';
		$current_latin = null;
		foreach ( $chars as $ch ) {
			$is_latin = ! $this->is_arabic_visual_char( $ch );
			if ( null === $current_latin ) {
				$current_latin = $is_latin;
				$current = $ch;
				continue;
			}
			if ( $is_latin === $current_latin ) {
				$current .= $ch;
			} else {
				$segments[] = array( 'text' => $current, 'latin' => $current_latin );
				$current = $ch;
				$current_latin = $is_latin;
			}
		}
		if ( '' !== $current ) {
			$segments[] = array( 'text' => $current, 'latin' => (bool) $current_latin );
		}
		return $segments;
	}

	protected function is_arabic_visual_char( $ch ) {
		return (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{08A0}-\x{08FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', (string) $ch );
	}

	protected function gd_raw_text_width( $text, $size, $font ) {
		$bbox = @imagettfbbox( $size, 0, $font, (string) $text );
		if ( ! is_array( $bbox ) ) { return 0; }
		return abs( $bbox[2] - $bbox[0] );
	}

	protected function build_modern_image_pdf( $data ) {
		$w = 1240;
		$h = 1754;
		$lang = isset( $data['lang'] ) ? (string) $data['lang'] : 'de';
		$is_rtl = ( 'ar' === $lang );
		$tpl = isset( $data['pdf_template'] ) && is_array( $data['pdf_template'] ) ? $data['pdf_template'] : array();
		if ( empty( $tpl ) && method_exists( $this->plugin, 'get_pdf_template' ) ) {
			$tpl = $this->plugin->get_pdf_template( $lang );
		}

		$im = imagecreatetruecolor( $w, $h );
		imagealphablending( $im, true );
		imagesavealpha( $im, true );
		if ( function_exists( 'imageantialias' ) ) { @imageantialias( $im, true ); }

		$colors = array(
			'page'        => $this->gd_color( $im, '#E5DDD0' ),
			'pattern'     => $this->gd_color_alpha( $im, '#FFFFFF', 100 ),
			'brown'       => $this->gd_color( $im, '#705A42' ),
			'brown_soft'  => $this->gd_color_alpha( $im, '#705A42', 82 ),
			'brown_light' => $this->gd_color_alpha( $im, '#705A42', 78 ),
			'cream'       => $this->gd_color( $im, '#E5DDD0' ),
			'beige'       => $this->gd_color( $im, '#D8C4AB' ),
			'gold'        => $this->gd_color( $im, '#C9AF7F' ),
			'gold_alpha'  => $this->gd_color_alpha( $im, '#C9AF7F', 96 ),
			'white'       => $this->gd_color( $im, '#FFFFFF' ),
			'white_25'    => $this->gd_color_alpha( $im, '#FFFFFF', 62 ),
			'white_45'    => $this->gd_color_alpha( $im, '#FFFFFF', 88 ),
			'white_55'    => $this->gd_color_alpha( $im, '#FFFFFF', 90 ),
			'white_70'    => $this->gd_color_alpha( $im, '#FFFFFF', 94 ),
			'card'        => $this->gd_color_alpha( $im, '#FFFFFF', 92 ),
			'text'        => $this->gd_color( $im, '#5F4B38' ),
			'muted'       => $this->gd_color( $im, '#5F4B38' ),
			'green'       => $this->gd_color( $im, '#315D36' ),
			'green_bg'    => $this->gd_color_alpha( $im, '#EAF2E5', 84 ),
			'badge'       => $this->gd_color_alpha( $im, '#FFF4DB', 84 ),
			'shadow'      => $this->gd_color_alpha( $im, '#000000', 108 ),
			'line'        => $this->gd_color_alpha( $im, '#705A42', 16 ),
			'intro_text'  => $this->gd_color( $im, '#5F4B38' ),
			'frame'       => $this->gd_color( $im, '#705A42' ),
			'top_fill'    => $this->gd_color_alpha( $im, '#FFFFFF', 12 ),
			'bottom_fill' => $this->gd_color_alpha( $im, '#705A42', 92 ),
			'card_fill'   => $this->gd_color_alpha( $im, '#FFFFFF', 98 ),
			'bottom_white' => $this->gd_color_alpha( $im, '#FFFFFF', 32 ),
		);

		imagefilledrectangle( $im, 0, 0, $w, $h, $colors['page'] );

		$font_regular = $this->get_pdf_ttf_font( false, $lang );
		$font_bold    = $this->get_pdf_ttf_font( true, $lang );
		if ( ! $font_regular || ! $font_bold ) {
			return '';
		}
		$latin_regular = $this->get_pdf_ttf_font( false, 'de' );
		$latin_bold    = $this->get_pdf_ttf_font( true, 'de' );
		if ( ! $latin_regular ) { $latin_regular = $font_regular; }
		if ( ! $latin_bold ) { $latin_bold = $font_bold; }

		// Full-page template/background: ONLY the uploaded main frame image is used.
		// No bundled template, no drawn pattern, no fallback logo/header graphics.
		$has_uploaded_background = false;
		$frame = $this->gd_load_template_image( $tpl, 'frame_image' );
		if ( $frame ) {
			$has_uploaded_background = true;
			$this->gd_place_image_cover( $im, $frame, 0, 0, $w, $h );
			imagedestroy( $frame );
		}

		// Structural overlays and brown frames.
		// Area 1: translucent white, filled all the way to the side borders, with rounded TOP corners only.
		$this->gd_top_round_rect( $im, 36, 58, 1204, 1082, 30, $colors['top_fill'], $colors['frame'], 3 );
		// Area 2: translucent brown lower section, square corners as requested.
		imagefilledrectangle( $im, 36, 1082, 1204, 1652, $colors['bottom_fill'] );
		// Outer frame: rounded TOP corners, no rounded lower corners.
		$this->gd_stroke_top_round_rect( $im, 36, 58, 1204, 1652, 30, $colors['frame'], 3 );
		// Section separator / straight edges.
		imageline( $im, 36, 1082, 1204, 1082, $colors['frame'] );
		imageline( $im, 36, 1652, 1204, 1652, $colors['frame'] );
		imageline( $im, 36, 1082, 36, 1652, $colors['frame'] );
		imageline( $im, 1204, 1082, 1204, 1652, $colors['frame'] );

		// Header: only show the uploaded header/logo graphic. If empty, leave the area empty.
		$header = $this->gd_load_template_image( $tpl, 'header_image' );
		if ( $header ) {
			$this->gd_place_image_contain( $im, $header, 260, 90, 720, 150 );
			imagedestroy( $header );
		}
		imageline( $im, 36, 248, 1204, 248, $colors['frame'] );

		// Intro text field.
		$name = trim( (string) ( $data['name'] ?? '' ) );
		$intro = isset( $tpl['intro_text'] ) && '' !== trim( (string) $tpl['intro_text'] ) ? (string) $tpl['intro_text'] : '';
		if ( '' === trim( $intro ) ) {
			$intro = $is_rtl ? "مرحباً {name}،\nبناءً على إجاباتك اخترنا لك هذه العطور الثلاثة.\nعند زيارة فرعنا ستحصل على خصم 15٪ مع هذه النتيجة." : ( 'en' === $lang ? "Hello {name},\nBased on your answers, we selected these three fragrances for you.\nWhen visiting our store, you receive a 15% discount with this result." : "Hallo {name},\nbasierend auf deinen Antworten haben wir diese drei Düfte für dich ausgewählt.\nBeim Besuch unserer Filiale erhältst du mit diesem Ergebnis 15% Rabatt." );
		}
		// Use exactly the editable PDF intro text from the backend.
		// Placeholders stay fully controllable by the admin, e.g. Arabic: مرحباً {name}،
		$intro = str_replace( array( '{name}', '{email}' ), array( $name, (string) ( $data['email'] ?? '' ) ), $intro );
		$intro = trim( $this->sydb_html_to_text( $intro ) );
		$intro_x = $is_rtl ? 1136 : 68;
		$this->gd_multiline_text_block( $im, $intro, $intro_x, 322, 21, $colors['intro_text'], $font_bold, $is_rtl ? 'right' : 'left', $is_rtl, 1100, 30, 4, $latin_bold );

		// Result title field.
		$result_title = isset( $tpl['result_title'] ) && '' !== trim( (string) $tpl['result_title'] ) ? (string) $tpl['result_title'] : ( $is_rtl ? 'أفضل 3 توصيات للعطور' : ( 'en' === $lang ? 'Your Top 3 fragrance recommendations' : 'Deine Top 3 Duftempfehlungen' ) );
		$this->gd_multiline_text_block( $im, $result_title, $w / 2, 526, 36, $colors['brown'], $font_bold, 'center', $is_rtl, 820, 42, 1, $latin_bold );

		// Product cards: fixed coordinates, no cropping for 1:1 product pictures.
		$items = array_values( (array) ( $data['items'] ?? array() ) );
		$items = array_slice( $items, 0, 3 );
		while ( count( $items ) < 3 ) {
			$items[] = array( 'rank' => count( $items ) + 1, 'title' => '', 'description' => '', 'percentage' => 0, 'image' => '' );
		}
		$xs = array( 78, 458, 838 );
		if ( $is_rtl ) { $xs = array_reverse( $xs ); }
		$card_y = 578;
		$card_w = 325;
		$card_h = 455;
		$match_word = isset( $data['match_label'] ) && '' !== trim( (string) $data['match_label'] ) ? (string) $data['match_label'] : ( $is_rtl ? 'تطابق' : 'Match' );

		for ( $i = 0; $i < 3; $i++ ) {
			$item = is_array( $items[ $i ] ) ? $items[ $i ] : array();
			$x = $xs[ $i ];
			$rank = isset( $item['rank'] ) ? (int) $item['rank'] : ( $i + 1 );
			$percentage = isset( $item['percentage'] ) ? max( 0, min( 100, (int) $item['percentage'] ) ) : 0;

			$this->gd_round_rect( $im, $x, $card_y, $x + $card_w, $card_y + $card_h, 18, $colors['card_fill'], $colors['frame'], 2 );

			$img = $this->gd_load_remote_image( $item['image'] ?? '' );
			if ( $img ) {
				$this->gd_place_image_contain( $im, $img, $x + 18, $card_y + 18, $card_w - 36, 210 );
				imagedestroy( $img );
			}

			$this->gd_text( $im, 'Top ' . $rank, $x + 77, $card_y + 271, 14, $colors['text'], $latin_bold, 'center', false, 100 );
			$match_text = $is_rtl ? ( $percentage . '٪ ' . $match_word ) : ( $percentage . '% ' . $match_word );
			$this->gd_text( $im, $match_text, $x + $card_w - 82, $card_y + 271, 14, $colors['green'], $is_rtl ? $font_bold : $latin_bold, 'center', $is_rtl, 118 );

			$title = trim( (string) ( $item['title'] ?? '' ) );
			if ( $is_rtl && preg_match( '/(?:Duft\s*Nr\.?|Duft|Nr\.?)\s*(\d+)/iu', $title, $m ) ) {
				$title = 'عطر رقم ' . $m[1];
			}
			$title_x = $is_rtl ? ( $x + $card_w - 22 ) : ( $x + 22 );
			$this->gd_multiline_text_block( $im, $title, $title_x, $card_y + 328, 23, $colors['text'], $font_bold, $is_rtl ? 'right' : 'left', $is_rtl, $card_w - 44, 30, 2, $latin_bold );

			$description = trim( $this->sydb_html_to_text( (string) ( $item['description'] ?? '' ) ) );
			if ( '' !== $description ) {
				$this->gd_multiline_text_block( $im, $description, $title_x, $card_y + 382, 13, $colors['muted'], $font_regular, $is_rtl ? 'right' : 'left', $is_rtl, $card_w - 44, 18, 4, $latin_regular );
			}
		}

		// Lower images: only uploaded graphics are placed; empty fields stay empty.

		$coupon = $this->gd_load_template_image( $tpl, 'coupon_image' );
		if ( $coupon ) {
			$this->gd_place_image_contain( $im, $coupon, 78, 1082, 480, 191 );
			imagedestroy( $coupon );
		}

		$instruction = $this->gd_load_template_image( $tpl, 'instruction_image' );
		if ( $instruction ) {
			$this->gd_place_image_contain( $im, $instruction, 682, 1082, 483, 191 );
			imagedestroy( $instruction );
		}

		// Welcome ribbon: only uploaded image; empty field stays empty.
		$welcome_band = $this->gd_load_template_image( $tpl, 'welcome_band_image' );
		if ( $welcome_band ) {
			$this->gd_place_image_contain( $im, $welcome_band, 126, 1309, 989, 47 );
			imagedestroy( $welcome_band );
		}

		// Five branch cards: only uploaded images; empty fields stay empty.
		$branch_y = 1401;
		$branch_w = 212;
		$branch_h = 153;
		$branch_xs = array( 76, 293, 510, 727, 944 );
		for ( $i = 1; $i <= 5; $i++ ) {
			$x = $branch_xs[ $i - 1 ];
			$branch_img = $this->gd_load_template_image( $tpl, 'branch_' . $i . '_image' );
			if ( $branch_img ) {
				$this->gd_place_image_contain( $im, $branch_img, $x, $branch_y, $branch_w, $branch_h );
				imagedestroy( $branch_img );
			}
		}

		// Bottom contact area: white slightly transparent band like the upper section, without empty margins.
		imagefilledrectangle( $im, 36, 1588, 1204, 1652, $colors['bottom_white'] );
		imageline( $im, 36, 1588, 1204, 1588, $colors['frame'] );
		// Redraw the full outer frame around the bottom white band as well.
		imageline( $im, 36, 1588, 36, 1652, $colors['frame'] );
		imageline( $im, 1204, 1588, 1204, 1652, $colors['frame'] );
		imageline( $im, 36, 1652, 1204, 1652, $colors['frame'] );

		if ( $is_rtl ) {
			$contact_fields = array(
				array( 'key' => 'website', 'x' => 214, 'default' => 'www.alowidat-syria.com', 'width' => 320 ),
				array( 'key' => 'email',   'x' => 620, 'default' => 'Info@alowidat-syria.com', 'width' => 360 ),
				array( 'key' => 'phone',   'x' => 1018, 'default' => '+963 988 551 070', 'width' => 300 ),
			);
		} else {
			$contact_fields = array(
				array( 'key' => 'website', 'x' => 178, 'default' => 'www.alowidat.de', 'width' => 340 ),
				array( 'key' => 'email',   'x' => 620, 'default' => 'info@alowidat.de', 'width' => 340 ),
				array( 'key' => 'phone',   'x' => 1050, 'default' => '+49 177 7975434', 'width' => 340 ),
			);
		}
		foreach ( $contact_fields as $field ) {
			$value = isset( $tpl[ $field['key'] ] ) && '' !== trim( (string) $tpl[ $field['key'] ] ) ? (string) $tpl[ $field['key'] ] : $field['default'];
			$this->gd_multiline_text_block( $im, $value, $field['x'], 1626, 19, $colors['muted'], $latin_bold, 'center', false, (int) $field['width'], 24, 1, $latin_bold );
		}

		// Optional footer image centered below the main frame. It is contained, not stretched, so Arabic artwork remains readable.
		$footer_image = $this->gd_load_template_image( $tpl, 'footer_image' );
		if ( $footer_image ) {
			$this->gd_place_image_contain( $im, $footer_image, 320, 1658, 600, 88 );
			imagedestroy( $footer_image );
		}


		ob_start();
		imagejpeg( $im, null, 94 );
		$jpg = ob_get_clean();
		imagedestroy( $im );
		return $this->build_single_image_pdf( $jpg, $w, $h );
	}


	protected function build_single_image_pdf( $jpg, $img_w, $img_h ) {
		$objects = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>';
		$stream = "q 595 0 0 842 0 0 cm /Im1 Do Q\n";
		$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . "endstream";
		$objects[] = '<< /Type /XObject /Subtype /Image /Width ' . (int) $img_w . ' /Height ' . (int) $img_h . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen( $jpg ) . " >>\nstream\n" . $jpg . "\nendstream";
		$pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array( 0 );
		foreach ( $objects as $i => $obj ) {
			$offsets[] = strlen( $pdf );
			$pdf .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		for ( $i = 1; $i <= count( $objects ); $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] );
		}
		$pdf .= "trailer\n<< /Size " . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
		return $pdf;
	}

		protected function get_pdf_ttf_font( $bold = false, $lang = 'de' ) {
		$upload = function_exists( 'wp_upload_dir' ) ? wp_upload_dir() : array( 'basedir' => sys_get_temp_dir() );
		$dir = trailingslashit( $upload['basedir'] ) . 'sy-duftberater-fonts';
		if ( ! file_exists( $dir ) && function_exists( 'wp_mkdir_p' ) ) { wp_mkdir_p( $dir ); }

		$is_ar = ( 'ar' === $lang );

		// Wichtig für Arabisch-PDFs:
		// Wir brauchen einen Font, der sowohl geformte arabische Glyphen ALS AUCH lateinische Namen sicher enthält.
		// Daher bevorzugen wir bei Arabisch zuerst DejaVu Sans (falls lokal vorhanden), danach Noto Sans / Noto Sans Arabic.
		// So werden Namen wie 'Ghaith Kayali' in der arabischen Vorlage nicht als Kästchen angezeigt.
		$preferred_file = $is_ar ? ( $bold ? 'NotoNaskhArabic-Bold.ttf' : 'NotoNaskhArabic-Regular.ttf' ) : 'DMSerifDisplay-Regular.ttf';
		$file = $dir . '/' . $preferred_file;
		if ( $this->is_valid_ttf_font( $file ) ) { return $file; }

		$download_sets = array(

			'NotoNaskhArabic-Regular.ttf' => array(
				'https://raw.githubusercontent.com/googlefonts/noto-fonts/main/hinted/ttf/NotoNaskhArabic/NotoNaskhArabic-Regular.ttf',
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoNaskhArabic/NotoNaskhArabic-Regular.ttf',
			),
			'NotoNaskhArabic-Bold.ttf' => array(
				'https://raw.githubusercontent.com/googlefonts/noto-fonts/main/hinted/ttf/NotoNaskhArabic/NotoNaskhArabic-Bold.ttf',
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoNaskhArabic/NotoNaskhArabic-Bold.ttf',
			),
			'Cairo-Regular.ttf' => array(
				'https://github.com/google/fonts/raw/main/ofl/cairo/Cairo%5Bslnt,wght%5D.ttf',
				'https://github.com/googlefonts/cairo/raw/main/fonts/ttf/Cairo-Regular.ttf',
			),
			'Cairo-Bold.ttf' => array(
				'https://github.com/google/fonts/raw/main/ofl/cairo/Cairo%5Bslnt,wght%5D.ttf',
				'https://github.com/googlefonts/cairo/raw/main/fonts/ttf/Cairo-Bold.ttf',
			),
			'NotoSerif-Regular.ttf' => array(
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSerif/NotoSerif-Regular.ttf',
			),
			'NotoSerif-Bold.ttf' => array(
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSerif/NotoSerif-Bold.ttf',
			),
			'DMSerifDisplay-Regular.ttf' => array(
				'https://github.com/google/fonts/raw/main/ofl/dmserifdisplay/DMSerifDisplay-Regular.ttf',
				'https://raw.githubusercontent.com/google/fonts/main/ofl/dmserifdisplay/DMSerifDisplay-Regular.ttf',
			),
			'NotoSans-Regular.ttf' => array(
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSans/NotoSans-Regular.ttf',
			),
			'NotoSans-Bold.ttf' => array(
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSans/NotoSans-Bold.ttf',
			),
			'NotoSansArabic-Regular.ttf' => array(
				'https://raw.githubusercontent.com/googlefonts/noto-fonts/main/hinted/ttf/NotoSansArabic/NotoSansArabic-Regular.ttf',
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansArabic/NotoSansArabic-Regular.ttf',
			),
			'NotoSansArabic-Bold.ttf' => array(
				'https://raw.githubusercontent.com/googlefonts/noto-fonts/main/hinted/ttf/NotoSansArabic/NotoSansArabic-Bold.ttf',
				'https://github.com/googlefonts/noto-fonts/raw/main/hinted/ttf/NotoSansArabic/NotoSansArabic-Bold.ttf',
			),
		);

		if ( isset( $download_sets[ $preferred_file ] ) && function_exists( 'wp_remote_get' ) ) {
			foreach ( $download_sets[ $preferred_file ] as $url ) {
				$response = wp_remote_get( $url, array( 'timeout' => 20, 'redirection' => 5 ) );
				if ( is_wp_error( $response ) ) { continue; }
				$body = wp_remote_retrieve_body( $response );
				if ( strlen( $body ) > 10000 ) {
					file_put_contents( $file, $body );
					break;
				}
			}
		}
		if ( $this->is_valid_ttf_font( $file ) ) { return $file; }
		if ( file_exists( $file ) ) { @unlink( $file ); }

		$local_candidates = array();
		if ( $is_ar ) {
			$local_candidates = array(
				'/usr/share/fonts/opentype/fonts-hosny-amiri/Amiri' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
				'/usr/share/fonts/truetype/noto/NotoNaskhArabic' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
				'/usr/share/fonts/truetype/noto/NotoSansArabic' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans' . ( $bold ? '-Bold' : '' ) . '.ttf',
				'/usr/share/fonts/truetype/noto/NotoSans' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
			);
		} else {
			$local_candidates = array(
				'/usr/share/fonts/truetype/dm/DMSerifDisplay-Regular.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSerif' . ( $bold ? '-Bold' : '' ) . '.ttf',
				'/usr/share/fonts/truetype/liberation2/LiberationSerif-' . ( $bold ? 'Bold' : 'Regular' ) . '.ttf',
				'/usr/share/fonts/truetype/noto/NotoSerif' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
				'/usr/share/fonts/truetype/dejavu/DejaVuSans' . ( $bold ? '-Bold' : '' ) . '.ttf',
				'/usr/share/fonts/truetype/noto/NotoSans' . ( $bold ? '-Bold' : '-Regular' ) . '.ttf',
			);
		}
		foreach ( $local_candidates as $candidate ) { if ( $this->is_valid_ttf_font( $candidate ) ) { return $candidate; } }
		return '';
	}


	protected function is_valid_ttf_font( $file ) {
		$file = (string) $file;
		if ( '' === $file || ! file_exists( $file ) || ! is_readable( $file ) || filesize( $file ) < 10000 ) {
			return false;
		}
		if ( function_exists( 'imagettfbbox' ) ) {
			$bbox = @imagettfbbox( 12, 0, $file, 'ABC 123' );
			return is_array( $bbox );
		}
		return true;
	}


	protected function sydb_pdf_hex( $value, $fallback = '#FFFFFF' ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $value ) ) {
			return $value;
		}
		$fallback = trim( (string) $fallback );
		return preg_match( '/^#[0-9A-Fa-f]{6}$/', $fallback ) ? $fallback : '#FFFFFF';
	}

	protected function gd_fill_template_area( $im, $template, $color_key, $image_key, $x1, $y1, $x2, $y2, $fallback_color = '#FFFFFF' ) {
		$color = $this->gd_color( $im, $this->sydb_pdf_hex( $template[ $color_key ] ?? '', $fallback_color ) );
		imagefilledrectangle( $im, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $color );
		$url = isset( $template[ $image_key ] ) ? esc_url_raw( $template[ $image_key ] ) : '';
		if ( '' !== $url ) {
			$bg = $this->gd_load_remote_image( $url );
			if ( $bg ) {
				$this->gd_place_image_cover( $im, $bg, (int) $x1, (int) $y1, max( 1, (int) ( $x2 - $x1 ) ), max( 1, (int) ( $y2 - $y1 ) ) );
				imagedestroy( $bg );
			}
		}
	}

	protected function gd_draw_soft_pattern( $im, $x1, $y1, $x2, $y2, $hex = '#FFFFFF', $alpha = 105 ) {
		$color = $this->gd_color_alpha( $im, $this->sydb_pdf_hex( $hex, '#FFFFFF' ), $alpha );
		$step = 110;
		for ( $y = $y1 - $step; $y < $y2 + $step; $y += $step ) {
			for ( $x = $x1 - $step; $x < $x2 + $step; $x += $step ) {
				imagearc( $im, (int) $x, (int) $y, $step, $step, 0, 180, $color );
				imagearc( $im, (int) ( $x + $step / 2 ), (int) ( $y + $step / 2 ), $step, $step, 180, 360, $color );
			}
		}
	}

	protected function gd_color( $im, $hex ) {
		$hex = ltrim( $hex, '#' );
		return imagecolorallocate( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	protected function gd_color_alpha( $im, $hex, $alpha = 80 ) {
		$hex = ltrim( $hex, '#' );
		return imagecolorallocatealpha( $im, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), max( 0, min( 127, (int) $alpha ) ) );
	}

	protected function gd_pdf_body_text( $im, $text, $x, $y, $colors, $font_regular, $font_bold, $latin_regular, $latin_bold, $is_rtl, $max_width ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return;
		}
		$lines = preg_split( "/\r\n|\r|\n/u", $text );
		$clean = array();
		foreach ( $lines as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', (string) $line ) );
			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}
		if ( empty( $clean ) ) {
			return;
		}

		$align = $is_rtl ? 'right' : 'left';
		$cursor_y = $y;
		foreach ( array_slice( $clean, 0, 5 ) as $index => $line ) {
			$contains_discount = ( false !== strpos( $line, '15٪' ) || false !== strpos( $line, '15%' ) || false !== strpos( $line, 'خصم' ) || false !== stripos( $line, 'discount' ) || false !== stripos( $line, 'rabatt' ) );
			$size = 20;
			$font = $font_regular;
			$latin_font = $latin_regular;
			$color = $colors['text'];
			if ( 0 === $index ) {
				$size = 23;
				$font = $font_bold;
				$latin_font = $latin_bold;
				$color = $colors['dark'];
			} elseif ( 1 === $index ) {
				$size = 18;
				$color = $colors['muted'];
			}
			if ( $contains_discount ) {
				// Make discount content visibly different when the user includes a badge/span in the HTML manager.
				$chip_w = $is_rtl ? 180 : 190;
				$chip_x1 = $is_rtl ? ( $x - $chip_w ) : $x;
				$chip_x2 = $is_rtl ? $x : ( $x + $chip_w );
				$this->gd_round_rect( $im, $chip_x1, $cursor_y - 25, $chip_x2, $cursor_y + 20, 20, $colors['goldsoft'], null, 0 );
				$this->gd_text( $im, $is_rtl ? 'خصم 15٪' : '15% Rabatt', ( $chip_x1 + $chip_x2 ) / 2, $cursor_y + 7, 18, $colors['dark'], $font_bold, 'center', $is_rtl, $chip_w - 10 );
				$line = trim( str_replace( array( 'خصم 15٪', '15٪', '15%', '15% Rabatt', '15% Discount' ), '', $line ) );
				if ( '' !== $line ) {
					$text_x = $is_rtl ? ( $chip_x1 - 28 ) : ( $chip_x2 + 28 );
					$text_w = max( 320, $max_width - $chip_w - 40 );
					$this->gd_multiline_text_block( $im, $line, $text_x, $cursor_y, 18, $colors['text'], $font_regular, $align, $is_rtl, $text_w, 27, 2, $latin_regular );
				}
				$cursor_y += 58;
				continue;
			}
			$this->gd_multiline_text_block( $im, $line, $x, $cursor_y, $size, $color, $font, $align, $is_rtl, $max_width, $size + 8, 2, $latin_font );
			$cursor_y += ( 0 === $index ? 38 : 34 );
		}
	}

	protected function gd_top_round_rect( $im, $x1, $y1, $x2, $y2, $r, $fill, $stroke = null, $stroke_width = 1 ) {
		$r = max( 0, (int) $r );
		imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2, $fill );
		imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y1 + $r, $fill );
		imagefilledellipse( $im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $fill );
		imagefilledellipse( $im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $fill );
		if ( null !== $stroke ) {
			$this->gd_stroke_top_round_rect( $im, $x1, $y1, $x2, $y2, $r, $stroke, $stroke_width );
		}
	}

	protected function gd_stroke_top_round_rect( $im, $x1, $y1, $x2, $y2, $r, $color, $stroke_width = 1 ) {
		$r = max( 0, (int) $r );
		$stroke_width = max( 1, (int) $stroke_width );
		for ( $i = 0; $i < $stroke_width; $i++ ) {
			imageline( $im, $x1 + $r, $y1 + $i, $x2 - $r, $y1 + $i, $color );
			imageline( $im, $x1 + $i, $y1 + $r, $x1 + $i, $y2, $color );
			imageline( $im, $x2 - $i, $y1 + $r, $x2 - $i, $y2, $color );
			imageline( $im, $x1, $y2 - $i, $x2, $y2 - $i, $color );
			imagearc( $im, $x1 + $r, $y1 + $r, $r * 2 - $i * 2, $r * 2 - $i * 2, 180, 270, $color );
			imagearc( $im, $x2 - $r, $y1 + $r, $r * 2 - $i * 2, $r * 2 - $i * 2, 270, 360, $color );
		}
	}

	protected function gd_stroke_round_rect( $im, $x1, $y1, $x2, $y2, $r, $color, $stroke_width = 1 ) {
		$x1 = (int) $x1;
		$y1 = (int) $y1;
		$x2 = (int) $x2;
		$y2 = (int) $y2;
		$r  = max( 1, (int) $r );
		$stroke_width = max( 1, (int) $stroke_width );
		for ( $i = 0; $i < $stroke_width; $i++ ) {
			$xx1 = $x1 + $i;
			$yy1 = $y1 + $i;
			$xx2 = $x2 - $i;
			$yy2 = $y2 - $i;
			$rr  = max( 1, $r - $i );
			imageline( $im, $xx1 + $rr, $yy1, $xx2 - $rr, $yy1, $color );
			imageline( $im, $xx1 + $rr, $yy2, $xx2 - $rr, $yy2, $color );
			imageline( $im, $xx1, $yy1 + $rr, $xx1, $yy2 - $rr, $color );
			imageline( $im, $xx2, $yy1 + $rr, $xx2, $yy2 - $rr, $color );
			imagearc( $im, $xx1 + $rr, $yy1 + $rr, $rr * 2, $rr * 2, 180, 270, $color );
			imagearc( $im, $xx2 - $rr, $yy1 + $rr, $rr * 2, $rr * 2, 270, 360, $color );
			imagearc( $im, $xx2 - $rr, $yy2 - $rr, $rr * 2, $rr * 2, 0, 90, $color );
			imagearc( $im, $xx1 + $rr, $yy2 - $rr, $rr * 2, $rr * 2, 90, 180, $color );
		}
	}

	protected function gd_round_rect( $im, $x1, $y1, $x2, $y2, $r, $fill, $stroke = null, $stroke_width = 1 ) {
		$x1 = (int) $x1;
		$y1 = (int) $y1;
		$x2 = (int) $x2;
		$y2 = (int) $y2;
		$r  = max( 1, min( (int) $r, (int) floor( min( $x2 - $x1, $y2 - $y1 ) / 2 ) ) );
		imagefilledrectangle( $im, $x1 + $r, $y1, $x2 - $r, $y2, $fill );
		imagefilledrectangle( $im, $x1, $y1 + $r, $x2, $y2 - $r, $fill );
		imagefilledellipse( $im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $fill );
		imagefilledellipse( $im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $fill );
		imagefilledellipse( $im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $fill );
		imagefilledellipse( $im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $fill );
		if ( null !== $stroke ) {
			$this->gd_stroke_round_rect( $im, $x1, $y1, $x2, $y2, $r, $stroke, $stroke_width );
		}
	}

	protected function gd_text_width( $text, $size, $font, $rtl = false ) {
		$text = $this->prepare_gd_text( $text, $rtl );
		$bbox = imagettfbbox( $size, 0, $font, $text );
		return abs( $bbox[2] - $bbox[0] );
	}

	protected function gd_text( $im, $text, $x, $y, $size, $color, $font, $align = 'left', $rtl = false, $max_width = 900 ) {
		$text = $this->prepare_gd_text( $text, $rtl );
		$bbox = imagettfbbox( $size, 0, $font, $text );
		$tw = abs( $bbox[2] - $bbox[0] );
		if ( 'center' === $align ) { $x = $x - ( $tw / 2 ); }
		elseif ( 'right' === $align ) { $x = $x - $tw; }
		imagettftext( $im, $size, 0, (int) $x, (int) $y, $color, $font, $text );
	}

	protected function gd_multiline_text( $im, $text, $x, $y, $size, $color, $font, $align = 'left', $rtl = false, $max_width = 700, $line_height = 32, $max_lines = 5 ) {
		$lines = $this->gd_wrap_lines( $text, $font, $size, $max_width, $rtl );
		$lines = array_slice( $lines, 0, $max_lines );
		foreach ( $lines as $i => $line ) {
			$this->gd_text( $im, $line, $x, $y + ( $i * $line_height ), $size, $color, $font, $align, $rtl, $max_width );
		}
	}

	protected function gd_wrap_lines( $text, $font, $size, $max_width, $rtl = false ) {
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
		if ( '' === $text ) { return array( '' ); }
		$words = preg_split( '/\s+/u', $text );
		$lines = array(); $line = '';
		foreach ( $words as $word ) {
			$test = ( '' === $line ) ? $word : $line . ' ' . $word;
			$render = $this->prepare_gd_text( $test, $rtl );
			$bbox = imagettfbbox( $size, 0, $font, $render );
			$tw = abs( $bbox[2] - $bbox[0] );
			if ( $tw > $max_width && '' !== $line ) { $lines[] = $line; $line = $word; }
			else { $line = $test; }
		}
		if ( '' !== $line ) { $lines[] = $line; }
		return $lines;
	}

	protected function prepare_gd_text( $text, $rtl = false ) {
		$text = html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
		$text = $this->normalize_pdf_text_symbols( $text, $rtl );
		if ( $rtl ) { return $this->prepare_mixed_rtl_text( $text ); }
		return $text;
	}

	protected function normalize_pdf_text_symbols( $text, $rtl = false ) {
		$text = str_replace( array( '–', '—' ), '-', (string) $text );
		if ( $rtl ) {
			// Arabic percent sign renders more reliably in RTL than the Latin percent sign.
			$text = str_replace( '%', '٪', $text );
		}
		return $text;
	}

	protected function prepare_mixed_rtl_text( $text ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) { return ''; }

		// GD/Freetype zeichnet Text ohne vollständigen BiDi-Layout-Engine von links nach rechts.
		// Deshalb erzeugen wir für arabische PDFs eine visuelle Zeichenfolge:
		// - arabische Wörter werden korrekt geformt und als Glyphen-Cluster umgedreht,
		// - lateinische Namen, E-Mail-Adressen, Zahlen und Prozentangaben bleiben in ihrer Reihenfolge,
		// - Satzzeichen und arabische Vokalzeichen bleiben am richtigen Buchstaben.
		$tokens = preg_split( '/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$chunks = array();
		$current_ltr = '';

		foreach ( $tokens as $token ) {
			if ( preg_match( '/^\s+$/u', $token ) ) {
				if ( '' !== $current_ltr ) { $current_ltr .= ' '; }
				continue;
			}

			if ( $this->contains_arabic_letters( $token ) ) {
				if ( '' !== trim( $current_ltr ) ) {
					$chunks[] = trim( $current_ltr );
					$current_ltr = '';
				}
				$chunks[] = $this->arabic_reshape_reverse( $token );
			} else {
				$current_ltr .= $token;
			}
		}

		if ( '' !== trim( $current_ltr ) ) { $chunks[] = trim( $current_ltr ); }

		return implode( ' ', array_reverse( $chunks ) );
	}

	protected function contains_arabic_letters( $text ) {
		return (bool) preg_match( '/[\x{0620}-\x{064A}\x{066E}-\x{066F}\x{0671}-\x{06D3}\x{06FA}-\x{06FF}]/u', (string) $text );
	}

	protected function arabic_reshape_reverse( $text ) {
		$clusters = $this->arabic_clusters( $text );
		$forms = $this->arabic_forms_map();
		$out = array();
		$count = count( $clusters );

		for ( $i = 0; $i < $count; $i++ ) {
			$cluster = $clusters[ $i ];
			$base = $cluster['base'];
			$marks = $cluster['marks'];

			// Lam-Alef ligatures: fixes words such as "لا"/"الإلكتروني" when rendered by GD.
			if ( 'ل' === $base && isset( $clusters[ $i + 1 ] ) ) {
				$next_base = $clusters[ $i + 1 ]['base'];
				$ligature_forms = $this->arabic_lam_alef_forms( $next_base );
				if ( $ligature_forms ) {
					$prev = $this->arabic_cluster_prev_can_join( $clusters, $i, $forms );
					$out[] = ( $prev ? $ligature_forms[1] : $ligature_forms[0] ) . $marks . $clusters[ $i + 1 ]['marks'];
					$i++;
					continue;
				}
			}

			if ( ! isset( $forms[ $base ] ) ) {
				$out[] = $base . $marks;
				continue;
			}

			$prev = $this->arabic_cluster_prev_can_join( $clusters, $i, $forms );
			$next = $this->arabic_cluster_next_can_join( $clusters, $i, $forms );
			$form_idx = 0; // isolated
			if ( $prev && $next ) { $form_idx = 3; }       // medial
			elseif ( $prev ) { $form_idx = 1; }            // final
			elseif ( $next ) { $form_idx = 2; }            // initial

			$out[] = $forms[ $base ][ $form_idx ] . $marks;
		}

		return implode( '', array_reverse( $out ) );
	}

	protected function arabic_clusters( $text ) {
		$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
		$clusters = array();
		foreach ( $chars as $ch ) {
			if ( $this->is_arabic_mark( $ch ) && ! empty( $clusters ) ) {
				$last = count( $clusters ) - 1;
				$clusters[ $last ]['marks'] .= $ch;
			} else {
				$clusters[] = array( 'base' => $ch, 'marks' => '' );
			}
		}
		return $clusters;
	}

	protected function is_arabic_mark( $ch ) {
		return (bool) preg_match( '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', (string) $ch );
	}

	protected function arabic_lam_alef_forms( $next_base ) {
		$map = array(
			'ا' => array( 'ﻻ', 'ﻼ' ),
			'أ' => array( 'ﻷ', 'ﻸ' ),
			'إ' => array( 'ﻹ', 'ﻺ' ),
			'آ' => array( 'ﻵ', 'ﻶ' ),
		);
		return $map[ $next_base ] ?? null;
	}

	protected function arabic_cluster_prev_can_join( $clusters, $i, $forms ) {
		for ( $j = $i - 1; $j >= 0; $j-- ) {
			$base = $clusters[ $j ]['base'];
			if ( isset( $forms[ $base ] ) ) {
				$current = $clusters[ $i ]['base'];
				return isset( $forms[ $current ] ) && ! empty( $forms[ $base ][5] ) && ! empty( $forms[ $current ][4] );
			}
			if ( trim( $base ) !== '' || '' !== $clusters[ $j ]['marks'] ) { return false; }
		}
		return false;
	}

	protected function arabic_cluster_next_can_join( $clusters, $i, $forms ) {
		for ( $j = $i + 1; $j < count( $clusters ); $j++ ) {
			$base = $clusters[ $j ]['base'];
			if ( isset( $forms[ $base ] ) ) {
				$current = $clusters[ $i ]['base'];
				return isset( $forms[ $current ] ) && ! empty( $forms[ $current ][5] ) && ! empty( $forms[ $base ][4] );
			}
			if ( trim( $base ) !== '' || '' !== $clusters[ $j ]['marks'] ) { return false; }
		}
		return false;
	}

	protected function arabic_forms_map() {
		// [isolated, final, initial, medial, joins_prev, joins_next]
		return array(
			'ء'=>array('ﺀ','ﺀ','ﺀ','ﺀ',0,0),'آ'=>array('ﺁ','ﺂ','ﺁ','ﺂ',1,0),'أ'=>array('ﺃ','ﺄ','ﺃ','ﺄ',1,0),'ؤ'=>array('ﺅ','ﺆ','ﺅ','ﺆ',1,0),'إ'=>array('ﺇ','ﺈ','ﺇ','ﺈ',1,0),'ئ'=>array('ﺉ','ﺊ','ﺋ','ﺌ',1,1),'ا'=>array('ﺍ','ﺎ','ﺍ','ﺎ',1,0),'ب'=>array('ﺏ','ﺐ','ﺑ','ﺒ',1,1),'ة'=>array('ﺓ','ﺔ','ﺓ','ﺔ',1,0),'ت'=>array('ﺕ','ﺖ','ﺗ','ﺘ',1,1),'ث'=>array('ﺙ','ﺚ','ﺛ','ﺜ',1,1),'ج'=>array('ﺝ','ﺞ','ﺟ','ﺠ',1,1),'ح'=>array('ﺡ','ﺢ','ﺣ','ﺤ',1,1),'خ'=>array('ﺥ','ﺦ','ﺧ','ﺨ',1,1),'د'=>array('ﺩ','ﺪ','ﺩ','ﺪ',1,0),'ذ'=>array('ﺫ','ﺬ','ﺫ','ﺬ',1,0),'ر'=>array('ﺭ','ﺮ','ﺭ','ﺮ',1,0),'ز'=>array('ﺯ','ﺰ','ﺯ','ﺰ',1,0),'س'=>array('ﺱ','ﺲ','ﺳ','ﺴ',1,1),'ش'=>array('ﺵ','ﺶ','ﺷ','ﺸ',1,1),'ص'=>array('ﺹ','ﺺ','ﺻ','ﺼ',1,1),'ض'=>array('ﺽ','ﺾ','ﺿ','ﻀ',1,1),'ط'=>array('ﻁ','ﻂ','ﻃ','ﻄ',1,1),'ظ'=>array('ﻅ','ﻆ','ﻇ','ﻈ',1,1),'ع'=>array('ﻉ','ﻊ','ﻋ','ﻌ',1,1),'غ'=>array('ﻍ','ﻎ','ﻏ','ﻐ',1,1),'ف'=>array('ﻑ','ﻒ','ﻓ','ﻔ',1,1),'ق'=>array('ﻕ','ﻖ','ﻗ','ﻘ',1,1),'ك'=>array('ﻙ','ﻚ','ﻛ','ﻜ',1,1),'ل'=>array('ﻝ','ﻞ','ﻟ','ﻠ',1,1),'م'=>array('ﻡ','ﻢ','ﻣ','ﻤ',1,1),'ن'=>array('ﻥ','ﻦ','ﻧ','ﻨ',1,1),'ه'=>array('ﻩ','ﻪ','ﻫ','ﻬ',1,1),'و'=>array('ﻭ','ﻮ','ﻭ','ﻮ',1,0),'ى'=>array('ﻯ','ﻰ','ﻯ','ﻰ',1,0),'ي'=>array('ﻱ','ﻲ','ﻳ','ﻴ',1,1),'لا'=>array('ﻻ','ﻼ','ﻻ','ﻼ',1,0),
		);
	}

	protected function gd_load_template_image( $template, $key ) {
		$template = is_array( $template ) ? $template : array();
		$url = isset( $template[ $key . '_url' ] ) ? (string) $template[ $key . '_url' ] : '';
		$attachment_id = isset( $template[ $key . '_id' ] ) ? absint( $template[ $key . '_id' ] ) : 0;
		return $this->gd_load_remote_image( $url, $attachment_id );
	}

	protected function gd_load_remote_image( $url, $attachment_id = 0 ) {
		$url = trim( (string) $url );
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id && function_exists( 'get_attached_file' ) ) {
			$path = get_attached_file( $attachment_id );
			$img = $this->gd_load_image_from_path( $path );
			if ( $img ) {
				return $img;
			}
		}

		if ( '' !== $url && function_exists( 'attachment_url_to_postid' ) && function_exists( 'get_attached_file' ) ) {
			$resolved_id = attachment_url_to_postid( $url );
			if ( $resolved_id ) {
				$path = get_attached_file( $resolved_id );
				$img = $this->gd_load_image_from_path( $path );
				if ( $img ) {
					return $img;
				}
			}
		}

		$local_path = $this->gd_local_path_from_url( $url );
		if ( '' !== $local_path ) {
			$img = $this->gd_load_image_from_path( $local_path );
			if ( $img ) {
				return $img;
			}
		}

		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return null;
		}

		if ( function_exists( 'wp_remote_get' ) ) {
			$response = wp_remote_get( $url, array( 'timeout' => 10, 'redirection' => 5 ) );
			if ( ! is_wp_error( $response ) ) {
				$body = wp_remote_retrieve_body( $response );
				if ( ! empty( $body ) ) {
					$img = @imagecreatefromstring( $body );
					if ( $img ) {
						return $img;
					}
				}
			}
		}

		if ( ini_get( 'allow_url_fopen' ) ) {
			$body = @file_get_contents( $url );
			if ( ! empty( $body ) ) {
				$img = @imagecreatefromstring( $body );
				if ( $img ) {
					return $img;
				}
			}
		}

		return null;
	}

	protected function gd_load_image_from_path( $path ) {
		$path = is_string( $path ) ? rawurldecode( $path ) : '';
		if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
			return null;
		}
		$body = @file_get_contents( $path );
		if ( empty( $body ) ) {
			return null;
		}
		$img = @imagecreatefromstring( $body );
		return $img ? $img : null;
	}

	protected function gd_local_path_from_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		$url_no_query = preg_replace( '/[?#].*$/', '', $url );
		if ( file_exists( rawurldecode( $url_no_query ) ) ) {
			return rawurldecode( $url_no_query );
		}

		if ( function_exists( 'wp_upload_dir' ) ) {
			$upload = wp_upload_dir();
			$baseurl = isset( $upload['baseurl'] ) ? preg_replace( '/[?#].*$/', '', (string) $upload['baseurl'] ) : '';
			$basedir = isset( $upload['basedir'] ) ? (string) $upload['basedir'] : '';
			if ( '' !== $baseurl && '' !== $basedir && 0 === strpos( $url_no_query, $baseurl ) ) {
				$relative = ltrim( substr( $url_no_query, strlen( $baseurl ) ), '/' );
				return trailingslashit( $basedir ) . rawurldecode( $relative );
			}
		}

		if ( defined( 'ABSPATH' ) ) {
			$parts = parse_url( $url_no_query );
			$path = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '';
			$pos = false !== $path ? strpos( $path, '/wp-content/' ) : false;
			if ( false !== $pos ) {
				return trailingslashit( ABSPATH ) . ltrim( substr( $path, $pos ), '/' );
			}
		}

		return '';
	}


	protected function gd_trim_right_uniform_border( $img, $tolerance = 10, $max_ratio = 0.18 ) {
		$w = imagesx( $img );
		$h = imagesy( $img );
		if ( $w < 20 || $h < 20 ) {
			return $img;
		}
		$bg = imagecolorat( $img, $w - 1, 0 );
		$bg_r = ( $bg >> 16 ) & 0xFF;
		$bg_g = ( $bg >> 8 ) & 0xFF;
		$bg_b = $bg & 0xFF;
		$max_trim = max( 1, (int) floor( $w * $max_ratio ) );
		$trim = 0;
		$step = max( 1, (int) floor( $h / 24 ) );
		for ( $x = $w - 1; $x >= max( 0, $w - $max_trim ); $x-- ) {
			$uniform = true;
			for ( $y = 0; $y < $h; $y += $step ) {
				$c = imagecolorat( $img, $x, $y );
				$r = ( $c >> 16 ) & 0xFF;
				$g = ( $c >> 8 ) & 0xFF;
				$b = $c & 0xFF;
				if ( abs( $r - $bg_r ) > $tolerance || abs( $g - $bg_g ) > $tolerance || abs( $b - $bg_b ) > $tolerance ) {
					$uniform = false;
					break;
				}
			}
			if ( ! $uniform ) {
				break;
			}
			$trim++;
		}
		if ( $trim <= 0 ) {
			return $img;
		}
		$new_w = $w - $trim;
		if ( $new_w < (int) floor( $w * 0.7 ) ) {
			return $img;
		}
		$cropped = imagecreatetruecolor( $new_w, $h );
		imagealphablending( $cropped, true );
		imagesavealpha( $cropped, true );
		$transparent = imagecolorallocatealpha( $cropped, 255, 255, 255, 127 );
		imagefilledrectangle( $cropped, 0, 0, $new_w, $h, $transparent );
		imagecopy( $cropped, $img, 0, 0, 0, 0, $new_w, $h );
		imagedestroy( $img );
		return $cropped;
	}


	protected function gd_place_image_exact_crop_edges( $dst, $src, $x, $y, $w, $h, $crop_left_ratio = 0, $crop_top_ratio = 0, $crop_right_ratio = 0, $crop_bottom_ratio = 0 ) {
		$sw = imagesx( $src ); $sh = imagesy( $src );
		if ( ! $sw || ! $sh ) { return; }
		$crop_left   = max( 0, min( (int) round( $sw * (float) $crop_left_ratio ), (int) ( $sw * 0.25 ) ) );
		$crop_top    = max( 0, min( (int) round( $sh * (float) $crop_top_ratio ), (int) ( $sh * 0.25 ) ) );
		$crop_right  = max( 0, min( (int) round( $sw * (float) $crop_right_ratio ), (int) ( $sw * 0.25 ) ) );
		$crop_bottom = max( 0, min( (int) round( $sh * (float) $crop_bottom_ratio ), (int) ( $sh * 0.25 ) ) );
		$src_x = $crop_left;
		$src_y = $crop_top;
		$src_w = max( 1, $sw - $crop_left - $crop_right );
		$src_h = max( 1, $sh - $crop_top - $crop_bottom );
		imagecopyresampled( $dst, $src, (int) $x, (int) $y, $src_x, $src_y, (int) $w, (int) $h, $src_w, $src_h );
	}

	protected function gd_place_image_exact( $dst, $src, $x, $y, $w, $h ) {
		$sw = imagesx( $src ); $sh = imagesy( $src );
		if ( ! $sw || ! $sh ) { return; }
		imagecopyresampled( $dst, $src, (int) $x, (int) $y, 0, 0, (int) $w, (int) $h, $sw, $sh );
	}

	protected function gd_place_image_contain( $dst, $src, $x, $y, $w, $h ) {
		$sw = imagesx( $src ); $sh = imagesy( $src );
		if ( ! $sw || ! $sh ) { return; }
		$scale = min( $w / $sw, $h / $sh );
		$nw = (int) ( $sw * $scale ); $nh = (int) ( $sh * $scale );
		imagecopyresampled( $dst, $src, (int) ( $x + ( $w - $nw ) / 2 ), (int) ( $y + ( $h - $nh ) / 2 ), 0, 0, $nw, $nh, $sw, $sh );
	}

	protected function gd_place_image_cover( $dst, $src, $x, $y, $w, $h ) {
		$sw = imagesx( $src ); $sh = imagesy( $src );
		if ( ! $sw || ! $sh ) { return; }
		$scale = max( $w / $sw, $h / $sh );
		$cw = (int) round( $w / $scale ); $ch = (int) round( $h / $scale );
		$sx = max( 0, (int) ( ( $sw - $cw ) / 2 ) ); $sy = max( 0, (int) ( ( $sh - $ch ) / 2 ) );
		imagecopyresampled( $dst, $src, $x, $y, $sx, $sy, $w, $h, $cw, $ch );
	}

}
