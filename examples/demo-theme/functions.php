<?php
/**
 * ElRoi Demo theme: the fields of each template and the Site Settings, registered with
 * ElRoi Site Editor. Copy this pattern into a client theme.
 *
 * @package ElRoiDemo
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', fn() => wp_enqueue_style( 'elroi-demo', get_stylesheet_uri(), array(), '1.0.0' ) );

add_action(
	'after_setup_theme',
	function () {
		if ( ! function_exists( 'elroi_register_template' ) ) {
			return; // The plugin is not active: templates fall back to their defaults below.
		}

		elroi_register_settings(
			array(
				'menu_title' => 'Demo Co.',
				'support'    => 'ElRoi Marketing',
				'sections'   => array(
					'contact' => array(
						'label'  => 'Contact',
						'fields' => array(
							'company' => array( 'label' => 'Business name', 'default' => 'Demo Co.' ),
							'phone'   => array( 'label' => 'Phone', 'default' => '(555) 010-0000', 'half' => true ),
							'email'   => array( 'type' => 'email', 'label' => 'Email', 'default' => 'hello@example.com', 'half' => true ),
						),
					),
					'numbers' => array(
						'label'       => 'Numbers shown on the site',
						'description' => 'Type {years} or {reviews} in any text to show these.',
						'fields'      => array(
							'years'   => array( 'label' => 'Years in business', 'default' => '10+', 'half' => true ),
							'reviews' => array( 'label' => 'Google reviews', 'default' => '50', 'half' => true ),
						),
					),
					'social'  => array(
						'label'  => 'Social profiles',
						'fields' => array(
							'instagram' => array( 'type' => 'url', 'label' => 'Instagram', 'default' => 'https://www.instagram.com/example', 'clearable' => true, 'help' => 'Leave empty to hide the link.' ),
						),
					),
				),
			)
		);

		elroi_register_template(
			'front-page',
			array(
				'label'     => 'Home page',
				'box_title' => 'Home page content',
				'fields'    => array(
					'hero'     => array(
						'type'   => 'group',
						'label'  => 'Top of the page',
						'fields' => array(
							'eyebrow' => array( 'label' => 'Small label above the heading', 'default' => 'Demo Co.' ),
							'h1'      => array( 'type' => 'heading', 'label' => 'Main heading (H1)', 'default' => 'We build *better homes.*' ),
							'lede'    => array( 'type' => 'textarea', 'label' => 'Introduction', 'default' => 'Licensed and insured, with {years} years of experience.' ),
							'photo'   => array( 'type' => 'image', 'label' => 'Background photo' ),
						),
					),
					'services' => array(
						'type'    => 'repeater',
						'label'   => 'Services',
						'item'    => 'Service',
						'fields'  => array(
							'name' => array( 'label' => 'Name' ),
							'link' => array( 'type' => 'link', 'label' => 'Link', 'half' => true ),
							'text' => array( 'type' => 'textarea', 'label' => 'Short description' ),
						),
						'default' => array(
							array( 'name' => 'Kitchens', 'link' => '/kitchens/', 'text' => 'Layouts, cabinets and counters.' ),
							array( 'name' => 'Roofing', 'link' => '/roofing/', 'text' => 'Repairs and replacements.' ),
						),
					),
					'facts'    => array(
						'type'   => 'group',
						'label'  => 'Facts',
						'fields' => array(
							'a' => array( 'label' => 'Fact 1', 'default' => '{years} years', 'half' => true ),
							'b' => array( 'label' => 'Fact 2', 'default' => '{reviews} reviews', 'half' => true ),
						),
					),
				),
			)
		);

		elroi_register_template(
			'templates/landing.php',
			array(
				'label'  => 'Landing page',
				'fields' => array(
					'h1'         => array( 'type' => 'heading', 'label' => 'Main heading (H1)' ),
					'intro'      => array( 'type' => 'paragraphs', 'label' => 'Introduction' ),
					'sections'   => array(
						'type'   => 'repeater',
						'label'  => 'Sections',
						'item'   => 'Section',
						'title'  => 'heading',
						'fields' => array(
							'heading'    => array( 'label' => 'Heading' ),
							'paragraphs' => array( 'type' => 'paragraphs', 'label' => 'Text' ),
							'bullets'    => array( 'type' => 'lines', 'label' => 'Checklist' ),
							'image_id'   => array( 'type' => 'image', 'label' => 'Image (optional)' ),
							'cards'      => array(
								'type'   => 'repeater',
								'label'  => 'Cards',
								'item'   => 'Card',
								'fields' => array(
									'heading' => array( 'label' => 'Card heading' ),
									'text'    => array( 'type' => 'textarea', 'label' => 'Text' ),
								),
							),
						),
					),
					'categories' => array( 'type' => 'lines', 'label' => 'Photo categories', 'help' => 'Click Update to see a new category on the photos.' ),
					'photos'     => array(
						'type'        => 'repeater',
						'label'       => 'Project photos',
						'item'        => 'Photo',
						'layout'      => 'grid',
						'multi_image' => 'id',
						'fields'      => array(
							'id'   => array( 'type' => 'image', 'label' => 'Photo' ),
							'cats' => array( 'type' => 'checkboxes', 'label' => 'Categories', 'options_from' => 'categories' ),
						),
					),
					'gallery'    => array( 'type' => 'gallery', 'label' => 'Small gallery' ),
					'framing'    => array(
						'type'    => 'numbers',
						'label'   => 'Photo framing',
						'labels'  => array( 'Across', 'Down', 'Zoom' ),
						'default' => array( 0.5, 0.33, 1.3 ),
					),
					'style'      => array( 'type' => 'select', 'label' => 'Color style', 'options' => array( 'Light', 'Dark' ), 'half' => true ),
					'cta'        => array( 'type' => 'url', 'label' => 'Button link', 'half' => true ),
					'team_note'  => array(
						'label'   => 'Only on the About page',
						'show_if' => fn( $post ) => $post && 'about' === $post->post_name,
					),
				),
			)
		);
	}
);
