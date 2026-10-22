<?php
/**
 * Home page: every word and picture comes from the Home page content form.
 *
 * @package ElRoiDemo
 */

$c = function_exists( 'elroi_content' ) ? elroi_content() : array();
get_header();
?>
<header class="hero"><div class="wrap">
	<p><?php echo elroi_text( $c['hero']['eyebrow'] ); // phpcs:ignore ?></p>
	<h1><?php echo elroi_heading( $c['hero']['h1'] ); // phpcs:ignore ?></h1>
	<p><?php echo elroi_text( $c['hero']['lede'] ); // phpcs:ignore ?></p>
	<?php echo elroi_image( $c['hero']['photo'], 'large' ); // phpcs:ignore ?>
</div></header>
<main class="wrap">
	<div class="cards">
		<?php foreach ( $c['services'] as $s ) : ?>
			<a class="card" href="<?php echo elroi_link( $s['link'] ); // phpcs:ignore ?>"><strong><?php echo elroi_text( $s['name'] ); // phpcs:ignore ?></strong><br><?php echo elroi_text( $s['text'] ); // phpcs:ignore ?></a>
		<?php endforeach; ?>
	</div>
	<ul><li><?php echo elroi_text( $c['facts']['a'] ); // phpcs:ignore ?></li><li><?php echo elroi_text( $c['facts']['b'] ); // phpcs:ignore ?></li></ul>
</main>
<?php
get_footer();
