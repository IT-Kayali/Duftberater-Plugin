<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$theme        = ! empty( $settings['frontend_theme'] ) ? sanitize_html_class( $settings['frontend_theme'] ) : 'dark';
$accent_color = ! empty( $settings['accent_color'] ) ? $settings['accent_color'] : '#d4af37';
$languages    = $this->plugin->get_supported_languages();
$default_lang = ! empty( $settings['default_language'] ) ? $this->plugin->normalize_lang( $settings['default_language'] ) : 'de';
$default_dir  = isset( $languages[ $default_lang ]['dir'] ) ? $languages[ $default_lang ]['dir'] : 'ltr';
$questions    = is_array( $questions ) ? $questions : array();
$total_steps   = count( $questions );

$render_i18n_attrs = function( $values ) {
	$attrs = '';
	foreach ( $this->plugin->get_supported_languages() as $lang => $data ) {
		$attrs .= ' data-' . esc_attr( $lang ) . '="' . esc_attr( $this->plugin->get_i18n_text( $values, $lang ) ) . '"';
	}
	return $attrs;
};

$ui_attrs = function( $key ) {
	$attrs = '';
	foreach ( $this->plugin->get_supported_languages() as $lang => $data ) {
		$attrs .= ' data-' . esc_attr( $lang ) . '="' . esc_attr( $this->plugin->get_ui_text( $key, $lang ) ) . '"';
	}
	return $attrs;
};

$render_option_card = function( $question_key, $value, $is_multi = false, $subtitle_key = '', $answer_display = 'image_only' ) use ( $render_i18n_attrs ) {
	$value = sanitize_key( $value );
	$icon  = $this->plugin->get_option_icon( $question_key, $value );
	$answer_display = in_array( $answer_display, array( 'image_only', 'image_text', 'text' ), true ) ? $answer_display : 'image_only';
	$bg_style = '';
	if ( 'image_only' === $answer_display && $icon ) {
		$bg_style = 'background-image:url(' . esc_url( $icon ) . ');background-size:cover;background-position:center;background-repeat:no-repeat;';
	}
	$labels = array();
	foreach ( $this->plugin->get_supported_languages() as $lang => $language ) {
		$labels[ $lang ] = $this->plugin->get_option_label_i18n( $question_key, $value, $lang );
	}
	$subtitle = $subtitle_key ? $this->plugin->get_default_i18n_texts()[ $subtitle_key ] ?? array() : array();
	?>
	<button type="button" class="sy-duftberater-option-card sydb-answer-card sy-answer-mode-<?php echo esc_attr( $answer_display ); ?>" data-value="<?php echo esc_attr( $value ); ?>" data-label="<?php echo esc_attr( $labels['de'] ); ?>" data-label-de="<?php echo esc_attr( $labels['de'] ); ?>" data-label-en="<?php echo esc_attr( $labels['en'] ); ?>" data-label-ar="<?php echo esc_attr( $labels['ar'] ); ?>" aria-pressed="false" style="<?php echo esc_attr( $bg_style ); ?>">
		<span class="sy-duftberater-check">✓</span>
		<?php if ( 'text' !== $answer_display ) : ?>
			<span class="sy-duftberater-option-icon-wrap sydb-answer-image-wrap">
				<?php if ( $icon ) : ?>
					<img class="sy-duftberater-option-icon sydb-answer-image" src="<?php echo esc_url( $icon ); ?>" alt="<?php echo esc_attr( $labels['de'] ); ?>" loading="lazy" />
				<?php else : ?>
					<span class="sy-duftberater-option-icon-placeholder"><?php echo esc_html( substr( $labels['de'], 0, 1 ) ); ?></span>
				<?php endif; ?>
			</span>
		<?php endif; ?>
		<span class="sy-duftberater-option-copy">
			<span class="sy-duftberater-option-title" <?php echo $render_i18n_attrs( $labels ); ?>><?php echo esc_html( $labels['de'] ); ?></span>
			<span class="sy-duftberater-option-subtitle" <?php echo $render_i18n_attrs( $subtitle ); ?>><?php echo esc_html( $this->plugin->get_i18n_text( $subtitle, 'de' ) ); ?></span>
		</span>
	</button>
	<?php
};

$render_option_pages = function( $question_key, $values, $is_multi = false, $subtitle_key = '', $answer_display = 'image_only', $extra_class = '' ) use ( $render_option_card ) {
	$values = array_values( array_filter( array_map( 'sanitize_key', (array) $values ) ) );
	$pages  = array_chunk( $values, 4 );
	$total  = count( $pages );
	if ( $total < 1 ) { return; }
	?>
	<div class="sy-duftberater-answer-pages <?php echo esc_attr( $extra_class ); ?>" data-answer-pages data-current-page="0" data-total-pages="<?php echo esc_attr( (string) $total ); ?>">
		<?php foreach ( $pages as $page_index => $page_values ) : ?>
			<div class="sy-duftberater-option-grid sy-duftberater-answer-page sydb-answer-count-<?php echo esc_attr( (string) count( $page_values ) ); ?> <?php echo 0 === $page_index ? 'is-active' : ''; ?>" data-answer-page="<?php echo esc_attr( (string) $page_index ); ?>">
				<?php foreach ( $page_values as $value ) : ?>
					<?php $render_option_card( $question_key, $value, $is_multi, $subtitle_key, $answer_display ); ?>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<?php if ( $total > 1 ) : ?>
			<div class="sy-duftberater-answer-pager">
				<button type="button" class="sy-duftberater-answer-page-nav" data-answer-page-prev aria-label="Vorherige Antworten">‹</button>
				<span class="sy-duftberater-answer-page-count"><span data-answer-page-current>1</span>/<span><?php echo esc_html( (string) $total ); ?></span></span>
				<button type="button" class="sy-duftberater-answer-page-nav" data-answer-page-next aria-label="Weitere Antworten">›</button>
			</div>
		<?php endif; ?>
	</div>
	<?php
};

?>
<?php
$layout_classes = array();
if ( ! empty( $settings['frontend_fullwidth'] ) ) { $layout_classes[] = 'sydb-fullwidth'; }
if ( ! empty( $settings['hide_plugin_header'] ) ) { $layout_classes[] = 'sydb-hide-header'; }
if ( ! empty( $settings['hide_plugin_footer'] ) ) { $layout_classes[] = 'sydb-hide-footer'; }
$sydb_frontend_logo_url  = ! empty( $settings['pdf_logo_url'] ) ? esc_url( $settings['pdf_logo_url'] ) : 'https://alowidat.de/wp-content/uploads/2025/06/Alowidat-Final-Logo.ai-600-x-298-px.png';
$sydb_frontend_logo_link = ! empty( $settings['frontend_logo_link'] ) ? esc_url( $settings['frontend_logo_link'] ) : '';
?>
<div class="sy-duftberater-wrap sy-duftberater-theme-<?php echo esc_attr( $theme ); ?> <?php echo esc_attr( implode( ' ', $layout_classes ) ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>" style="--sy-accent: <?php echo esc_attr( $accent_color ); ?>; --sydb-accent: <?php echo esc_attr( $accent_color ); ?>;">
	<div class="sy-duftberater-app is-start-view<?php echo 'ar' === $default_lang ? ' is-rtl' : ''; ?>" data-lang="<?php echo esc_attr( $default_lang ); ?>" dir="<?php echo esc_attr( $default_dir ); ?>">
		<div class="sydb-top-logo-bar" aria-label="Alowidat Logo">
			<?php if ( $sydb_frontend_logo_link ) : ?>
				<a class="sydb-top-logo-link" href="<?php echo esc_url( $sydb_frontend_logo_link ); ?>">
					<img class="sydb-top-logo" src="<?php echo esc_url( $sydb_frontend_logo_url ); ?>" alt="Alowidat" loading="eager" decoding="async" />
				</a>
			<?php else : ?>
				<img class="sydb-top-logo" src="<?php echo esc_url( $sydb_frontend_logo_url ); ?>" alt="Alowidat" loading="eager" decoding="async" />
			<?php endif; ?>
		</div>

		<?php if ( empty( $settings['hide_plugin_header'] ) ) : ?>
		<div class="sy-duftberater-header sydb-logo-only-header"><div class="sy-duftberater-brand">
			<img class="sy-duftberater-logo" src="https://alowidat.de/wp-content/uploads/2025/06/Alowidat-Final-Logo.ai-600-x-298-px.png" alt="Alowidat" loading="lazy" />
			</div>
		</div>
		<?php endif; ?>

		<div class="sy-duftberater-progress-wrap">
			<div class="sy-duftberater-progress-meta"><span><span data-progress-step-label <?php echo $ui_attrs( 'progress_step' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'progress_step', 'de' ) ); ?></span> <span data-progress-current>1</span></span><span><span data-progress-current-secondary>1</span> <span data-progress-of-label <?php echo $ui_attrs( 'progress_of' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'progress_of', 'de' ) ); ?></span> <span data-progress-total><?php echo esc_html( (string) $total_steps ); ?></span></span></div>
			<div class="sy-duftberater-progress-track"><span class="sy-duftberater-progress-fill"></span></div>
		</div>

		<div class="sy-duftberater-content"><div class="sy-duftberater-steps">
			<div class="sy-duftberater-step sy-duftberater-language-step is-active" data-type="language">
				<div class="sy-duftberater-step-head">
					<div class="sy-duftberater-step-kicker" <?php echo $ui_attrs( 'language_kicker' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'language_kicker', $default_lang ) ); ?></div>
					<h3 class="sy-duftberater-step-title" <?php echo $ui_attrs( 'language_title' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'language_title', $default_lang ) ); ?></h3>
					<p class="sy-duftberater-step-text" <?php echo $ui_attrs( 'language_text' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'language_text', $default_lang ) ); ?></p>
				</div>
				<div class="sy-duftberater-language-grid">
					<?php foreach ( $languages as $lang => $language ) : ?>
						<?php $flag_url = $this->plugin->get_language_image( $lang ); ?>
						<button type="button" class="sy-duftberater-language-card sydb-language-bg-card" data-lang-select="<?php echo esc_attr( $lang ); ?>">
							<?php if ( $flag_url ) : ?>
								<img class="sydb-language-bg-img" src="<?php echo esc_url( $flag_url ); ?>" alt="" loading="eager" decoding="async" />
							<?php endif; ?>
							<span class="sy-duftberater-language-text">
								<span class="sy-duftberater-language-name"><?php echo esc_html( $language['native'] ); ?></span>
							</span>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php $welcome_audio = isset( $settings['welcome_audio_i18n'] ) && is_array( $settings['welcome_audio_i18n'] ) ? $settings['welcome_audio_i18n'] : array(); ?>
			<div class="sy-duftberater-step sy-duftberater-welcome-step" data-type="welcome" data-audio-de="<?php echo esc_url( $welcome_audio['de'] ?? '' ); ?>" data-audio-en="<?php echo esc_url( $welcome_audio['en'] ?? '' ); ?>" data-audio-ar="<?php echo esc_url( $welcome_audio['ar'] ?? '' ); ?>">
				<div class="sy-duftberater-welcome-card">
					<div class="sy-duftberater-step-kicker" <?php echo $ui_attrs( 'welcome_kicker' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_kicker', 'de' ) ); ?></div>
					<h3 class="sy-duftberater-step-title" <?php echo $ui_attrs( 'welcome_title' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_title', 'de' ) ); ?></h3>
					<p class="sy-duftberater-step-text" <?php echo $ui_attrs( 'welcome_text' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_text', 'de' ) ); ?></p>
					<div class="sy-duftberater-welcome-badges">
						<span <?php echo $ui_attrs( 'welcome_badge_1' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_badge_1', 'de' ) ); ?></span>
						<span <?php echo $ui_attrs( 'welcome_badge_2' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_badge_2', 'de' ) ); ?></span>
						<span <?php echo $ui_attrs( 'welcome_badge_3' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'welcome_badge_3', 'de' ) ); ?></span>
					</div>
					<button type="button" class="sy-duftberater-btn sy-duftberater-btn-primary" data-action="start" <?php echo $ui_attrs( 'start_button' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'start_button', 'de' ) ); ?></button>
				</div>
			</div>

			<?php foreach ( $questions as $question ) : ?>
				<?php
				$key         = sanitize_key( $question['key'] ?? '' );
				$is_multi    = ! empty( $question['multiple'] );
				$is_required = ! empty( $question['required'] );
				$answer_display = isset( $question['answer_display'] ) ? sanitize_key( $question['answer_display'] ) : 'image_only';
				if ( ! in_array( $answer_display, array( 'image_only', 'image_text', 'text' ), true ) ) { $answer_display = 'image_only'; }
				$options     = (array) ( $question['options'] ?? array() );
				$groups      = $this->plugin->get_question_groups( $key );
				?>
				<?php
				$audio_i18n = isset( $question['audio_i18n'] ) && is_array( $question['audio_i18n'] ) ? $question['audio_i18n'] : array();
				$usage_stage_audio_i18n = isset( $question['usage_stage_audio_i18n'] ) && is_array( $question['usage_stage_audio_i18n'] ) ? $question['usage_stage_audio_i18n'] : array();
				$usage_audio_attrs = '';
				if ( 'verwendungsbereich' === $key ) {
					foreach ( array( 'alltag', 'arbeit', 'formell' ) as $stage_key ) {
						foreach ( array( 'de', 'en', 'ar' ) as $audio_lang ) {
							$usage_audio_attrs .= ' data-usage-audio-' . esc_attr( $stage_key ) . '-' . esc_attr( $audio_lang ) . '="' . esc_url( $usage_stage_audio_i18n[ $stage_key ][ $audio_lang ] ?? '' ) . '"';
						}
					}
				}
				?>
				<div class="sy-duftberater-step" data-type="question" data-key="<?php echo esc_attr( $key ); ?>" data-multiple="<?php echo $is_multi ? '1' : '0'; ?>" data-required="<?php echo $is_required ? '1' : '0'; ?>" data-audio-url="<?php echo esc_url( $question['audio_url'] ?? '' ); ?>" data-audio-de="<?php echo esc_url( $audio_i18n['de'] ?? ( $question['audio_url'] ?? '' ) ); ?>" data-audio-en="<?php echo esc_url( $audio_i18n['en'] ?? '' ); ?>" data-audio-ar="<?php echo esc_url( $audio_i18n['ar'] ?? '' ); ?>"<?php echo $usage_audio_attrs; ?>>
					<div class="sy-duftberater-step-head">
						<div class="sy-duftberater-step-kicker" data-step-kicker data-step-order="<?php echo esc_attr( (string) ( $question['order'] ?? '' ) ); ?>">Schritt <?php echo esc_html( (string) ( $question['order'] ?? '' ) ); ?></div>
						<h3 class="sy-duftberater-step-title" data-step-title <?php echo $render_i18n_attrs( $question['label_i18n'] ?? array( 'de' => $question['label'] ?? '' ) ); ?>><?php echo esc_html( $this->plugin->get_question_text( $question, 'label', 'de' ) ); ?><?php if ( $is_required ) : ?><span class="sy-required-star">*</span><?php endif; ?></h3>
						<?php $desc_de = $this->plugin->get_question_text( $question, 'description', 'de' ); ?>
						<p class="sy-duftberater-step-text" data-step-text <?php echo $render_i18n_attrs( $question['description_i18n'] ?? array( 'de' => $question['description'] ?? '' ) ); ?>><?php echo esc_html( $desc_de ); ?></p>
						<div class="sy-duftberater-hint" data-default-hint=""></div>
					</div>

					<div class="sy-duftberater-selection-panel" aria-live="polite"><div class="sy-duftberater-selection-values sy-selected-answers-target"></div></div>

					<div class="sy-duftberater-options">
						<?php if ( 'verwendungsbereich' === $key ) : ?>
							<div class="sy-duftberater-usage-flow" data-usage-flow>
								<div class="sy-duftberater-usage-crumbs" data-usage-crumbs></div>
								<section class="sy-duftberater-usage-stage is-active" data-usage-stage="main">
									<div class="sy-duftberater-option-grid sy-duftberater-option-grid-usage sydb-answer-count-2">
										<?php foreach ( array( 'alltag' => array( 'de' => 'Alltag', 'en' => 'Everyday', 'ar' => 'الحياة اليومية' ), 'formell' => array( 'de' => 'Formell / Wichtige Anlässe', 'en' => 'Formal / important occasions', 'ar' => 'مناسبات رسمية / مهمة' ) ) as $value => $labels ) : ?>
											<?php $subtitle_i18n = $this->plugin->get_default_i18n_texts()[ 'alltag' === $value ? 'usage_main_alltag_subtitle' : 'usage_main_formell_subtitle' ] ?? array(); ?>
											<?php $usage_icon = $this->plugin->get_usage_group_icon( $value ); ?>
											<button type="button" class="sy-duftberater-option-card sydb-answer-card sy-answer-mode-<?php echo esc_attr( $answer_display ); ?>" data-usage-role="main" data-value="<?php echo esc_attr( $value ); ?>" data-label="<?php echo esc_attr( $labels['de'] ); ?>" data-label-de="<?php echo esc_attr( $labels['de'] ); ?>" data-label-en="<?php echo esc_attr( $labels['en'] ); ?>" data-label-ar="<?php echo esc_attr( $labels['ar'] ); ?>" aria-pressed="false">
												<span class="sy-duftberater-check">✓</span>
												<?php if ( 'text' !== $answer_display ) : ?><span class="sy-duftberater-option-icon-wrap sydb-answer-image-wrap"><img class="sy-duftberater-option-icon sydb-answer-image" src="<?php echo esc_url( $usage_icon ); ?>" alt="" loading="lazy" /></span><?php endif; ?>
												<?php if ( 'image_only' !== $answer_display ) : ?><span class="sy-duftberater-option-copy"><span class="sy-duftberater-option-title" <?php echo $render_i18n_attrs( $labels ); ?>><?php echo esc_html( $labels['de'] ); ?></span><span class="sy-duftberater-option-subtitle" <?php echo $render_i18n_attrs( $subtitle_i18n ); ?>><?php echo esc_html( $this->plugin->get_i18n_text( $subtitle_i18n, 'de' ) ); ?></span></span><?php endif; ?>
											</button>
										<?php endforeach; ?>
									</div>
								</section>
								<section class="sy-duftberater-usage-stage" data-usage-stage="alltag"><div class="sy-duftberater-option-grid sy-duftberater-option-grid-usage sydb-answer-count-3"><?php foreach ( array( 'ausgehen', 'arbeit', 'zuhause' ) as $value ) { if ( isset( $options[ $value ] ) ) { $render_option_card( $key, $value, false, 'single_subtitle', $answer_display ); } } ?></div></section>
								<section class="sy-duftberater-usage-stage" data-usage-stage="arbeit"><div class="sy-duftberater-option-grid sy-duftberater-option-grid-usage sydb-answer-count-2"><?php foreach ( array( 'buero', 'grosse_raeume' ) as $value ) { if ( isset( $options[ $value ] ) ) { $render_option_card( $key, $value, false, 'single_subtitle', $answer_display ); } } ?></div></section>
								<section class="sy-duftberater-usage-stage" data-usage-stage="formell"><div class="sy-duftberater-option-grid sy-duftberater-option-grid-usage sydb-answer-count-3"><?php foreach ( array( 'date', 'meeting', 'private_anlaesse' ) as $value ) { if ( isset( $options[ $value ] ) ) { $render_option_card( $key, $value, false, 'single_subtitle', $answer_display ); } } ?></div></section>
							</div>
						<?php elseif ( 'duftnoten' === $key ) : ?>
							<?php $note_values = array(); foreach ( $this->plugin->get_note_catalog() as $group_key => $group_data ) { if ( ! empty( $options[ $group_key ] ) ) { $note_values[] = $group_key; } } $render_option_pages( $key, $note_values, true, 'multi_subtitle', $answer_display, 'sy-duftberater-notes-grid' ); ?>
						<?php elseif ( 'alter' === $key ) : ?>
							<?php $age_values = array(); foreach ( array( '10_20', '20_30', '30_40', 'ueber_40' ) as $value ) { if ( isset( $options[ $value ] ) ) { $age_values[] = $value; } } $render_option_pages( $key, $age_values, false, 'single_subtitle', $answer_display ); ?>
						<?php else : ?>
							<?php $render_option_pages( $key, array_keys( $options ), $is_multi, $is_multi ? 'multi_subtitle' : 'single_subtitle', $answer_display ); ?>
						<?php endif; ?>
					</div>

					<div class="sy-duftberater-actions sydb-question-actions">
						<button type="button" class="sy-duftberater-btn" data-action="prev" <?php echo $ui_attrs( 'back_button' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'back_button', 'de' ) ); ?></button>
						<button type="button" class="sy-duftberater-btn sy-duftberater-btn-primary" data-action="next" <?php echo $ui_attrs( 'next_button' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'next_button', 'de' ) ); ?></button>
						<?php if ( $is_multi || ! $is_required ) : ?>
							<p class="sy-duftberater-step-note" <?php echo $ui_attrs( $is_multi ? 'multi_hint' : 'optional_hint' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( $is_multi ? 'multi_hint' : 'optional_hint', 'de' ) ); ?></p>
						<?php else : ?>
							<p class="sy-duftberater-step-note" <?php echo $ui_attrs( 'click_next' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'click_next', 'de' ) ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>

			<?php $result_audio = isset( $settings['result_audio_i18n'] ) && is_array( $settings['result_audio_i18n'] ) ? $settings['result_audio_i18n'] : array(); ?>
			<div class="sy-duftberater-step" data-type="result" data-audio-de="<?php echo esc_url( $result_audio['de'] ?? '' ); ?>" data-audio-en="<?php echo esc_url( $result_audio['en'] ?? '' ); ?>" data-audio-ar="<?php echo esc_url( $result_audio['ar'] ?? '' ); ?>">
				<div class="sy-duftberater-step-head">
					<div class="sy-duftberater-step-kicker" <?php echo $ui_attrs( 'result_kicker' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'result_kicker', 'de' ) ); ?></div>
					<h3 class="sy-duftberater-step-title" <?php echo $ui_attrs( 'result_title' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'result_title', 'de' ) ); ?></h3>
					<p class="sy-duftberater-step-text" <?php echo $ui_attrs( 'result_text' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'result_text', 'de' ) ); ?></p>
				</div>
				<div class="sy-duftberater-selection-panel sy-duftberater-selection-panel-result" aria-live="polite"><div class="sy-duftberater-selection-values sy-selected-answers-target" data-selected-target="results"></div></div>
				<div class="sy-duftberater-results" data-results-container><div class="sy-duftberater-loading" <?php echo $ui_attrs( 'loading_text' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'loading_text', 'de' ) ); ?></div></div>
				<div class="sy-duftberater-lead-box" data-lead-box>
					<div class="sy-duftberater-step-kicker" <?php echo $ui_attrs( 'lead_form_title' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'lead_form_title', 'de' ) ); ?></div>
					<p class="sy-duftberater-step-text" <?php echo $ui_attrs( 'lead_form_text' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'lead_form_text', 'de' ) ); ?></p>
					<form class="sy-duftberater-lead-form" data-lead-form autocomplete="off">
						<label><span <?php echo $ui_attrs( 'lead_name_label' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'lead_name_label', 'de' ) ); ?></span><input type="text" name="lead_name" autocomplete="off" value="" required></label>
						<label><span <?php echo $ui_attrs( 'lead_email_label' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'lead_email_label', 'de' ) ); ?></span><input type="email" name="lead_email" autocomplete="off" value="" required></label>
						<button type="submit" class="sy-duftberater-btn sy-duftberater-btn-primary" <?php echo $ui_attrs( 'lead_submit' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'lead_submit', 'de' ) ); ?></button>
					</form>
					<div class="sy-duftberater-lead-message" data-lead-message></div>
				</div>
				<div class="sy-duftberater-actions"><button type="button" class="sy-duftberater-btn" data-action="prev" <?php echo $ui_attrs( 'back_button' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'back_button', 'de' ) ); ?></button><button type="button" class="sy-duftberater-btn sy-duftberater-btn-primary" data-action="restart" <?php echo $ui_attrs( 'restart_button' ); ?>><?php echo esc_html( $this->plugin->get_ui_text( 'restart_button', 'de' ) ); ?></button></div>
			</div>
		</div>
		<?php if ( empty( $settings['hide_plugin_footer'] ) ) : ?>
		<div class="sy-duftberater-footer" aria-hidden="true"></div>
		<?php endif; ?>
	</div>
</div>
