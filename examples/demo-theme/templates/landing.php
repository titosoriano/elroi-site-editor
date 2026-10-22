<?php
/**
 * Template Name: Landing page
 *
 * @package ElRoiDemo
 */

$c = elroi_content();
get_header();
?>
<main class="wrap">
	<h1><?php echo elroi_heading( $c['h1'] ? $c['h1'] : get_the_title() ); // phpcs:ignore ?></h1>
	<?php echo elroi_paragraphs( $c['intro'] ); // phpcs:ignore ?>
	<?php foreach ( $c['sections'] as $s ) : ?>
		<section>
			<h2><?php echo elroi_heading( $s['heading'] ); // phpcs:ignore ?></h2>
			<?php echo elroi_paragraphs( $s['paragraphs'] ); // phpcs:ignore ?>
			<?php if ( $s['bullets'] ) : ?><ul><?php foreach ( $s['bullets'] as $b ) : ?><li><?php echo elroi_text( $b ); // phpcs:ignore ?></li><?php endforeach; ?></ul><?php endif; ?>
			<?php echo elroi_image( $s['image_id'] ); // phpcs:ignore ?>
			<div class="cards"><?php foreach ( $s['cards'] as $card ) : ?><div class="card"><h3><?php echo elroi_text( $card['heading'] ); // phpcs:ignore ?></h3><p><?php echo elroi_text( $card['text'] ); // phpcs:ignore ?></p></div><?php endforeach; ?></div>
		</section>
	<?php endforeach; ?>
	<div class="cards"><?php foreach ( $c['photos'] as $p ) : ?><figure data-cats="<?php echo esc_attr( implode( ' ', $p['cats'] ) ); ?>"><?php echo elroi_image( $p['id'], 'medium' ); // phpcs:ignore ?></figure><?php endforeach; ?></div>
</main>
<?php
get_footer();
