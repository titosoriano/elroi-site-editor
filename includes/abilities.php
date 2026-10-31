<?php
/**
 * Abilities (WordPress 6.9+) so AI agents and MCP tools read and change page fields the safe way:
 *
 * - `elroi/get-page-content`: a page's template, its fields (key, type, label) and its content.
 * - `elroi/update-page-content`: changes only the fields given and keeps everything else
 *   (elroi_update()), with a revision before and after.
 *
 * Both need the right to edit that page. Turn them off with the `elroi_abilities` filter (false).
 *
 * @package ElRoiSiteEditor
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field outline for agents: key => type and label, nested for groups and repeaters.
 *
 * @param array $fields Normalized fields.
 */
function elroi_fields_outline( $fields ) {
	$out = array();
	foreach ( $fields as $k => $f ) {
		$o = array(
			'type'  => $f['type'],
			'label' => $f['label'],
		);
		if ( ! empty( $f['fields'] ) ) {
			$o['fields'] = elroi_fields_outline( $f['fields'] );
		}
		$out[ $k ] = $o;
	}
	return $out;
}

/**
 * Permission for both abilities: the user can edit the page.
 *
 * @param mixed $input Ability input.
 */
function elroi_ability_can_edit( $input ) {
	$id = absint( is_array( $input ) ? ( $input['page_id'] ?? 0 ) : 0 );
	return $id && current_user_can( 'edit_page', $id );
}

add_action(
	'wp_abilities_api_categories_init',
	function () {
		if ( function_exists( 'wp_register_ability_category' ) && apply_filters( 'elroi_abilities', true ) ) {
			wp_register_ability_category(
				'elroi-site-editor',
				array(
					'label'       => 'ElRoi Site Editor',
					'description' => 'Read and change the words and pictures of pages built from fields.',
				)
			);
		}
	}
);

add_action(
	'wp_abilities_api_init',
	function () {
		if ( ! function_exists( 'wp_register_ability' ) || ! apply_filters( 'elroi_abilities', true ) ) {
			return;
		}
		$page_id = array(
			'type'        => 'integer',
			'description' => 'ID of the page.',
			'minimum'     => 1,
		);
		wp_register_ability(
			'elroi/get-page-content',
			array(
				'label'               => 'Get page fields',
				'description'         => 'Returns a designed page\'s template, its field outline (key, type, label), the stored changes and the full content with defaults filled in. Read this before changing a page.',
				'category'            => 'elroi-site-editor',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array( 'page_id' => $page_id ),
					'required'             => array( 'page_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => 'elroi_ability_can_edit',
				'execute_callback'    => function ( $input ) {
					$post = get_post( absint( $input['page_id'] ) );
					$cfg  = $post ? elroi_template_for( $post ) : null;
					if ( ! $cfg ) {
						return new WP_Error( 'elroi_no_template', 'This page is not built from fields.' );
					}
					return array(
						'page_id'  => $post->ID,
						'title'    => get_the_title( $post ),
						'template' => $cfg['template'],
						'label'    => $cfg['label'],
						'fields'   => elroi_fields_outline( $cfg['fields'] ),
						'saved'    => (object) elroi_stored( $post, $cfg ),
						'content'  => elroi_content( $post ),
					);
				},
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
		wp_register_ability(
			'elroi/update-page-content',
			array(
				'label'               => 'Change page fields',
				'description'         => 'Changes only the fields given and keeps every other field. Named keys (groups) merge key by key; lists (repeaters, paragraphs, galleries) replace the whole list; null removes a value so it shows its default. Paragraph fields are lists of strings, images are attachment IDs. A revision is kept before and after, so the change can be undone.',
				'category'            => 'elroi-site-editor',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'page_id' => $page_id,
						'changes' => array(
							'type'                 => 'object',
							'description'          => 'Values to change, shaped like the page content, e.g. {"hero": {"h1": "New *title*"}}.',
							'additionalProperties' => true,
						),
					),
					'required'             => array( 'page_id', 'changes' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array( 'type' => 'object' ),
				'permission_callback' => 'elroi_ability_can_edit',
				'execute_callback'    => function ( $input ) {
					$saved = elroi_update( absint( $input['page_id'] ), (array) $input['changes'] );
					return is_wp_error( $saved ) ? $saved : array(
						'page_id' => absint( $input['page_id'] ),
						'saved'   => (object) $saved,
					);
				},
				'meta'                => array(
					'public'      => true,
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
);
