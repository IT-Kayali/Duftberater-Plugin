<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$image           = ! empty( $parfum['bild'] ) ? esc_url( $parfum['bild'] ) : '';
$link            = ! empty( $parfum['produktlink'] ) ? esc_url( $parfum['produktlink'] ) : '';
$rank            = isset( $rank ) ? (int) $rank : 0;
$lang            = isset( $lang ) ? $this->plugin->normalize_lang( $lang ) : 'de';
$product_label   = $this->plugin->get_ui_text( 'product_button', $lang );
$match_label     = $this->plugin->get_ui_text( 'match_label', $lang );
$product_title   = $this->plugin->get_i18n_text( $parfum['name_i18n'] ?? array(), $lang, $parfum['name'] ?? '' );
$product_desc    = $this->plugin->get_i18n_text( $parfum['beschreibung_i18n'] ?? array(), $lang, $parfum['beschreibung'] ?? '' );
$placeholder     = 'ar' === $lang ? 'لا توجد صورة' : ( 'en' === $lang ? 'No image available' : 'Kein Bild vorhanden' );
?>
<div class="sy-duftberater-result-card rank-<?php echo esc_attr( (string) $rank ); ?>" data-parfum-id="<?php echo esc_attr( $parfum['id'] ?? '' ); ?>" data-rank="<?php echo esc_attr( (string) $rank ); ?>" data-percentage="<?php echo esc_attr( (string) $percentage ); ?>">
	<div class="sy-duftberater-result-image">
		<?php if ( $image ) : ?>
			<img src="<?php echo $image; ?>" alt="<?php echo esc_attr( $product_title ); ?>" loading="lazy" />
		<?php else : ?>
			<div class="sy-duftberater-image-placeholder"><?php echo esc_html( $placeholder ); ?></div>
		<?php endif; ?>
	</div>
	<div class="sy-duftberater-result-body">
		<div class="sy-duftberater-result-topline">
			<div class="sy-duftberater-result-rank">Top <?php echo esc_html( (string) $rank ); ?></div>
			<div class="sy-duftberater-score"><?php echo esc_html( (string) $percentage ); ?>% <?php echo esc_html( $match_label ); ?></div>
		</div>
		<div class="sy-duftberater-match-bar"><span style="width: <?php echo esc_attr( (string) $percentage ); ?>%"></span></div>
		<h4 class="sy-duftberater-result-title"><?php echo esc_html( $product_title ); ?></h4>
		<p class="sy-duftberater-result-text"><?php echo esc_html( $product_desc ); ?></p>
		<?php if ( $link ) : ?>
			<div class="sy-duftberater-result-actions">
				<a class="sy-duftberater-btn sy-duftberater-btn-primary" href="<?php echo $link; ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $product_label ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</div>
