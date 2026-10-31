<?php
/**
 * Search engines and site search on pages built from fields.
 *
 * Designed pages keep their words in fields, so the page body WordPress stores is empty and
 * SEO tools would judge an empty page. This file:
 *
 * - Feeds Rank Math's content analysis the page as visitors see it, including changes not saved
 *   yet: the edit screen posts the form to the page itself (preview, nothing is stored) and hands
 *   what is inside <main> to Rank Math's `rank_math_content` filter.
 * - Leaves Rank Math's "Content AI" check (only a paid Content AI plan passes it) out of the score,
 *   unless Settings > Reading says otherwise.
 * - Adds the photos of image and gallery fields to the XML sitemap (Rank Math and Yoast).
 * - Makes site search find pages by their field text, and gives those results an excerpt.
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a page's visible content comes from the theme (registered template or posts page)
 * rather than from the block editor. Filter: `elroi_seo_page_from_theme`.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_seo_page_from_theme( $post ) {
	$post = get_post( $post );
	if ( ! $post || 'page' !== $post->post_type ) {
		return false;
	}
	$yes = (bool) elroi_template_for( $post ) || (int) get_option( 'page_for_posts' ) === (int) $post->ID;
	return (bool) apply_filters( 'elroi_seo_page_from_theme', $yes, $post );
}

/**
 * Text as plain words: {placeholders} filled in, *highlight* marks and [link](/path) syntax
 * removed. Not escaped: for structured data, excerpts and search, not for HTML.
 *
 * @param mixed $text Text.
 */
function elroi_plain( $text ) {
	$text = strtr( is_scalar( $text ) ? (string) $text : '', elroi_placeholders() );
	$text = preg_replace( '/\[([^\]]+)\]\(([^)\s]+)\)/u', '$1', $text );
	return trim( preg_replace( '/\*(.+?)\*/u', '$1', $text ) );
}

/**
 * Like elroi_text(), and `[words](/path)` becomes a link (site paths go through home_url();
 * links to other sites open in a new tab). Use it for paragraphs and answers where an inline
 * link helps readers and search engines.
 *
 * @param mixed $text  Text.
 * @param array $attrs Extra attributes for every link, e.g. array( 'class' => 'link' ).
 */
function elroi_rich( $text, $attrs = array() ) {
	$home = elroi_site_hosts();
	return preg_replace_callback(
		'/\[([^\]]+)\]\(([^)\s]+)\)/u',
		function ( $m ) use ( $attrs, $home ) {
			$url = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
			if ( preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) && ! preg_match( '#^(https?|mailto|tel):#i', $url ) ) {
				return $m[1];
			}
			$href = elroi_link( $url );
			if ( '' === $href ) {
				return $m[1];
			}
			$extra = '';
			if ( preg_match( '#^https?://#i', $url ) && ! in_array( strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ), $home, true ) ) {
				$attrs += array(
					'target' => '_blank',
					'rel'    => 'noopener',
				);
			}
			foreach ( $attrs as $k => $v ) {
				$extra .= ' ' . sanitize_key( $k ) . '="' . esc_attr( $v ) . '"';
			}
			return '<a href="' . $href . '"' . $extra . '>' . $m[1] . '</a>';
		},
		elroi_text( $text )
	);
}

/**
 * Every word of a page's fields as one plain string (defaults filled in).
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_plain_text( $post = null ) {
	$post = get_post( $post );
	$cfg  = $post ? elroi_template_for( $post ) : null;
	if ( ! $cfg ) {
		return '';
	}
	$parts = array();
	elroi_collect_text( $cfg['fields'], elroi_content( $post ), $parts );
	return trim( preg_replace( '/\s+/u', ' ', implode( ' ', $parts ) ) );
}

/**
 * Walk fields and values, collecting words.
 *
 * @param array $fields Normalized fields.
 * @param array $values Values.
 * @param array $parts  Collected text (by reference).
 */
function elroi_collect_text( $fields, $values, &$parts ) {
	foreach ( $fields as $k => $f ) {
		$v = $values[ $k ] ?? null;
		switch ( $f['type'] ) {
			case 'group':
				elroi_collect_text( $f['fields'], (array) $v, $parts );
				break;
			case 'repeater':
				foreach ( (array) $v as $item ) {
					elroi_collect_text( $f['fields'], (array) $item, $parts );
				}
				break;
			case 'text':
			case 'heading':
			case 'textarea':
				$parts[] = elroi_plain( $v );
				break;
			case 'paragraphs':
			case 'lines':
				foreach ( (array) $v as $p ) {
					$parts[] = elroi_plain( $p );
				}
				break;
		}
	}
}

/**
 * Attachment IDs of every image and gallery field of a page.
 *
 * @param int|WP_Post|null $post Page.
 */
function elroi_image_ids( $post = null ) {
	$post = get_post( $post );
	$cfg  = $post ? elroi_template_for( $post ) : null;
	$ids  = array();
	if ( $cfg ) {
		elroi_collect_images( $cfg['fields'], elroi_content( $post ), $ids );
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Walk fields and values, collecting attachment IDs.
 *
 * @param array $fields Normalized fields.
 * @param array $values Values.
 * @param array $ids    Collected IDs (by reference).
 */
function elroi_collect_images( $fields, $values, &$ids ) {
	foreach ( $fields as $k => $f ) {
		$v = $values[ $k ] ?? null;
		if ( 'image' === $f['type'] ) {
			$ids[] = absint( $v );
		} elseif ( 'gallery' === $f['type'] ) {
			$ids = array_merge( $ids, array_map( 'absint', (array) $v ) );
		} elseif ( 'group' === $f['type'] ) {
			elroi_collect_images( $f['fields'], (array) $v, $ids );
		} elseif ( 'repeater' === $f['type'] ) {
			foreach ( (array) $v as $item ) {
				elroi_collect_images( $f['fields'], (array) $item, $ids );
			}
		}
	}
}

/*
 * ---------------------------------------------------------------------------------------------
 * Rank Math content analysis.
 * ---------------------------------------------------------------------------------------------
 */

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( 'post.php' !== $hook || ! defined( 'RANK_MATH_VERSION' ) || ! apply_filters( 'elroi_seo_analysis', true ) ) {
			return;
		}
		$post = get_post();
		if ( ! elroi_seo_page_from_theme( $post ) || 'trash' === $post->post_status ) {
			return;
		}
		wp_enqueue_script( 'elroi-seo', ELROI_SE_URL . 'assets/seo.js', array( 'wp-hooks' ), ELROI_SE_VERSION, true );
		wp_localize_script(
			'elroi-seo',
			'elroiSeo',
			array(
				'url'      => (int) get_option( 'page_on_front' ) === (int) $post->ID ? home_url( '/' ) : get_permalink( $post ),
				'post'     => (int) $post->ID,
				'nonce'    => wp_create_nonce( 'elroi_seo_' . $post->ID ),
				'selector' => (string) apply_filters( 'elroi_seo_selector', 'main', $post ),
			)
		);
	}
);

/*
 * The analysis request: the page posted with the form's current values. Only for someone who can
 * edit the page, never cached, and nothing is saved.
 */
add_action(
	'wp',
	function () {
		if ( empty( $_POST['elroi_seo'] ) || empty( $_POST['elroi_seo_post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id = absint( $_POST['elroi_seo_post'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! wp_verify_nonce( sanitize_key( $_POST['elroi_seo'] ), 'elroi_seo_' . $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex' );
		do_action( 'litespeed_control_set_nocache', 'ElRoi SEO preview' );
		add_filter( 'show_admin_bar', '__return_false' );
		$post = get_post( $id );
		$cfg  = elroi_template_for( $post );
		if ( $cfg && isset( $_POST['elroi'] ) ) {
			$in = wp_unslash( (array) $_POST['elroi'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned field by field in elroi_build_fields()
			$GLOBALS['elroi_preview'][ $id ] = elroi_build_page( $cfg, $post, $in, elroi_saved( $post ) );
		}
	}
);

/**
 * Whether Rank Math's Content AI check is left out of the score (Settings > Reading).
 */
function elroi_seo_skip_content_ai() {
	return (bool) apply_filters( 'elroi_seo_skip_content_ai', (bool) get_option( 'elroi_seo_skip_content_ai', '1' ) );
}

add_filter(
	'rank_math/researches/tests',
	function ( $tests ) {
		if ( elroi_seo_skip_content_ai() ) {
			unset( $tests['hasContentAI'] );
		}
		return $tests;
	},
	20
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'reading',
			'elroi_seo_skip_content_ai',
			array(
				'type'              => 'string',
				'default'           => '1',
				'sanitize_callback' => fn( $v ) => $v ? '1' : '0',
			)
		);
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			return;
		}
		add_settings_field(
			'elroi_seo_skip_content_ai',
			'Rank Math score',
			function () {
				printf(
					'<input type="hidden" name="elroi_seo_skip_content_ai" value="0"><label><input type="checkbox" name="elroi_seo_skip_content_ai" value="1"%s> Leave the "Content AI" check out of the SEO score</label><p class="description">Only a paid Rank Math Content AI plan can pass that check. It says nothing about the page itself.</p>',
					checked( elroi_seo_skip_content_ai(), true, false )
				);
			},
			'reading'
		);
	}
);

/*
 * ---------------------------------------------------------------------------------------------
 * Sitemap photos.
 * ---------------------------------------------------------------------------------------------
 */

/**
 * Add a page's field photos to a sitemap image list (Rank Math and Yoast use the same shape).
 *
 * @param array $images  Image items (src, title, alt).
 * @param int   $post_id Post ID.
 */
function elroi_sitemap_images( $images, $post_id ) {
	$images = is_array( $images ) ? $images : array();
	$seen   = array_flip( array_filter( array_column( $images, 'src' ) ) );
	foreach ( elroi_image_ids( $post_id ) as $id ) {
		$src = wp_get_attachment_url( $id );
		if ( ! $src || isset( $seen[ $src ] ) || ! wp_attachment_is_image( $id ) ) {
			continue;
		}
		$seen[ $src ] = true;
		$images[]     = array(
			'src'   => $src,
			'title' => get_the_title( $id ),
			'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}
	return $images;
}
add_filter( 'rank_math/sitemap/urlimages', 'elroi_sitemap_images', 10, 2 );
add_filter( 'wpseo_sitemap_urlimages', 'elroi_sitemap_images', 10, 2 );

/*
 * ---------------------------------------------------------------------------------------------
 * Site search.
 * ---------------------------------------------------------------------------------------------
 */

/**
 * Published pages built from fields whose words contain every search term.
 *
 * @param array $terms Search terms.
 */
function elroi_search_page_ids( $terms ) {
	$terms = array_filter( array_map( 'trim', (array) $terms ), fn( $t ) => '' !== $t && '-' !== $t[0] );
	if ( ! $terms || ! elroi_templates() ) {
		return array();
	}
	$ids   = array();
	$pages = get_posts(
		array(
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'has_password'     => false,
			'posts_per_page'   => 300,
			'suppress_filters' => true,
		)
	);
	foreach ( $pages as $p ) {
		if ( ! elroi_template_for( $p ) ) {
			continue;
		}
		$text = elroi_plain_text( $p );
		$all  = true;
		foreach ( $terms as $t ) {
			if ( false === mb_stripos( $text, $t ) ) {
				$all = false;
				break;
			}
		}
		if ( $all ) {
			$ids[] = (int) $p->ID;
		}
	}
	return $ids;
}

add_filter(
	'posts_search',
	function ( $search, $query ) {
		if ( is_admin() || '' === trim( $search ) || ! $query->is_main_query() || ! $query->is_search() || ! apply_filters( 'elroi_search_fields', true ) ) {
			return $search;
		}
		$ids = elroi_search_page_ids( (array) $query->get( 'search_terms' ) );
		if ( ! $ids ) {
			return $search;
		}
		global $wpdb;
		return " AND ( {$wpdb->posts}.ID IN (" . implode( ',', array_map( 'intval', $ids ) ) . ") OR ( 1=1 {$search} ) ) ";
	},
	10,
	2
);

add_filter(
	'get_the_excerpt',
	function ( $excerpt, $post = null ) {
		if ( '' !== trim( (string) $excerpt ) || ! $post || 'page' !== $post->post_type || ! elroi_template_for( $post ) ) {
			return $excerpt;
		}
		return wp_trim_words( elroi_plain_text( $post ), (int) apply_filters( 'excerpt_length', 55 ), '…' );
	},
	10,
	2
);
