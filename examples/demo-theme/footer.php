<footer class="wrap">
	<p><?php echo esc_html( elroi_setting( 'company' ) ); ?> · <a href="tel:<?php echo esc_attr( elroi_tel( elroi_setting( 'phone' ) ) ); ?>"><?php echo esc_html( elroi_setting( 'phone' ) ); ?></a> · <a href="mailto:<?php echo esc_attr( elroi_setting( 'email' ) ); ?>"><?php echo esc_html( elroi_setting( 'email' ) ); ?></a></p>
	<?php if ( elroi_setting( 'instagram' ) ) : ?><p><a href="<?php echo esc_url( elroi_setting( 'instagram' ) ); ?>">Instagram</a></p><?php endif; ?>
</footer>
<?php wp_footer(); ?>
</body>
</html>
