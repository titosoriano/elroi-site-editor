# ElRoi Site Editor: guide for agents

ElRoi Site Editor is [ElRoi Marketing](https://elroi.marketing/)'s WordPress plugin for client editing. A custom theme describes which fields each page template has; the plugin builds the wp-admin forms, saves the content as JSON page meta with revisions, adds a Site Settings screen, gives the Editor role safe access, locks the structure of designed pages, and adds a "How to edit" guide. Clients change words and pictures; the design stays in the theme.

Use it on every new client site ElRoi builds. Install and activate the plugin first, then build the theme around it.

## Rules when building a theme with it

- **Never hard-code client copy, photos or contact facts in templates.** Every heading, paragraph, list, button text that may change, photo, phone, address and email comes from a registered field or a Site Setting. Layout markup, class names and fixed UI words ("Read more", "Menu") stay in the template.
- Register fields in the theme's `functions.php` on `after_setup_theme`, guarded with `function_exists( 'elroi_register_template' )`.
- Give every field a `default` holding the copy the design was built with, so a fresh page renders complete and an emptied field goes back to it.
- Print everything through the escaping helpers: `elroi_text()`, `elroi_heading()`, `elroi_paragraphs()`, `elroi_link()`, `elroi_image()`. Never echo a raw value.
- Labels are plain English for a non-technical client ("Main heading (H1)", "Short description"). Add `help` where the client could get it wrong.
- Contact facts used in many places (phones, addresses, email, years in business, review count, social links) go in `elroi_register_settings()`, not in page fields. Text fields can show them with `{key}`.
- Before handing a site over, check that saving every page's form unchanged changes nothing (see Testing).

## Registering

```php
add_action( 'after_setup_theme', function () {
	if ( ! function_exists( 'elroi_register_template' ) ) {
		return;
	}
	elroi_register_settings( array(
		'menu_title' => 'Acme',              // wp-admin menu name (default: site title)
		'support'    => 'ElRoi Marketing',   // who the client contacts, shown in the guide
		'sections'   => array(
			'contact' => array( 'label' => 'Contact', 'fields' => array(
				'phone' => array( 'label' => 'Phone', 'default' => '(555) 010-0000', 'half' => true ),
				'email' => array( 'type' => 'email', 'label' => 'Email', 'default' => 'hi@acme.com', 'half' => true ),
			) ),
			'social' => array( 'label' => 'Social profiles', 'fields' => array(
				'instagram' => array( 'type' => 'url', 'label' => 'Instagram', 'clearable' => true ),
			) ),
		),
	) );

	elroi_register_template( 'front-page', array(          // the page set as home page
		'label'     => 'Home page',
		'box_title' => 'Home page content',
		'fields'    => array(
			'hero' => array( 'type' => 'group', 'label' => 'Top of the page', 'fields' => array(
				'h1'    => array( 'type' => 'heading', 'label' => 'Main heading (H1)', 'default' => 'We build *better homes.*' ),
				'lede'  => array( 'type' => 'textarea', 'label' => 'Introduction', 'default' => '{years} years of experience.' ),
				'photo' => array( 'type' => 'image', 'label' => 'Background photo' ),
			) ),
		),
	) );

	elroi_register_template( 'templates/service.php', array( 'label' => 'Service page', 'fields' => array( /* ... */ ) ) );
} );
```

`elroi_register_template( $template, $args )`: `$template` is the value stored in `_wp_page_template` (e.g. `templates/service.php`) or `'front-page'`. Args: `label` (shown in the guide), `fields`, `meta_key` (default `_elroi_content`; set it to keep an existing meta key when moving a site onto the plugin), `box_title` (default "Page content"), `intro`, `block_editor` (default false: the block editor and content field are removed on these pages), `lock` (default true), `placeholders` (default true; set false when the template does not print through `elroi_text()`, so the form does not promise `{setting}` placeholders).

## Field types

Every field: `type` (default `text`), `label`, `help`, `default`, `half` (true: sits side by side with the next half field), `show_if` (top-level only: `fn( $post ) => bool`; a hidden field keeps its stored value).

| Type | Stored as | Notes |
|---|---|---|
| `text` | string | One line. |
| `heading` | string | Like text; `*words*` print as `<em>` through `elroi_heading()`. |
| `textarea` | string | One paragraph (line breaks become spaces). `rows`. |
| `paragraphs` | list of strings | Blank line between paragraphs. Print with `elroi_paragraphs()`. |
| `lines` | list of strings | One per line (checklists, town lists, categories). |
| `link` | string | Site path (`/services/`) or full URL; addresses of this site are stored as paths. Print with `elroi_link()`. |
| `url`, `email` | string | Validated. |
| `number` | int or float | |
| `numbers` | list of numbers | Fixed list with `labels` (e.g. Across, Down, Zoom) and a `default` list; blanks take the default. |
| `select` | string | `options` list. |
| `checkboxes` | list of strings | `options` list, or `options_from` => key of a top-level `lines` field (e.g. photo categories). Saving keeps only listed options, in list order. |
| `image` | attachment ID | Picker plus required alt text, saved on the attachment. Print with `elroi_image( $id, $size, $attr )`. |
| `gallery` | list of IDs | Multi-select picker. |
| `group` | object | `fields`. A top-level group is a titled part of the form. |
| `repeater` | list of objects | `fields`, `item` (singular name), `title` (field shown in the item bar, default the first), `layout` `list` (collapsible) or `grid` (cards, good for photos), `add_label`, `multi_image` => image field key (adds an "Add several photos" button). Repeaters nest. |

Storage: only non-empty values that differ from their default are saved, in field order. Keys the schema does not know are kept, including unknown keys inside repeater items (carried through the form in a hidden `__extra` input), so moving existing data onto the plugin never loses anything.

Reading: `elroi_content()` returns every field for the current page with defaults filled in (an empty value or empty list shows the default; repeater items have every key, empty when missing). `elroi_get( 'hero.h1' )` reads one path. `elroi_saved()` returns only what is stored.

## Template functions

- `elroi_content( $post = null )`, `elroi_get( $path, $post = null, $default = '' )`, `elroi_saved( $post = null )`
- `elroi_update( $post, $changes )`, `elroi_set( $path, $value, $post )`: change some fields and keep the rest (see below).
- `elroi_text( $s )`: escaped, with `{key}` placeholders from Site Settings filled in (add more with the `elroi_placeholders` filter).
- `elroi_heading( $s, $tag = 'em' )`: like `elroi_text()`, `*words*` wrapped in `<em>`.
- `elroi_paragraphs( $list, $attrs = '', $rich = false )`: `<p>` per paragraph.
- `elroi_rich( $text )`: like `elroi_text()`, `[words](/path)` becomes a link. `elroi_plain( $text )`: plain words for structured data.
- `elroi_link( $path_or_url )`: escaped URL; paths go through `home_url()`, and addresses of this site (also earlier domains) print with the current domain.
- `elroi_image( $id, $size = 'large', $attr = array() )`: `wp_get_attachment_image()`.
- `elroi_setting( $key = null )`: one setting or all; empty gives the default; a cleared `clearable` field gives `''`.
- `elroi_tel( $label )`: `+15550100000` from "(555) 010-0000".

## Changing content from code, scripts or AI tools

Never write the page meta directly with a few fields: that replaces the whole page. Use:

- `elroi_update( $post, $changes )`: changes only what `$changes` names. Named keys (groups) merge key by key, lists (repeaters, paragraphs, galleries) are replaced whole, `null` removes a value so it shows its default. Keeps a revision before and after.
- `elroi_set( 'hero.h1', 'New *title*', $post )`: one value by dot path (named keys only; change a repeater item by passing the whole list to `elroi_update()`).
- Abilities (WordPress 6.9+, for MCP and AI agents): `elroi/get-page-content` (field outline, stored changes, full content) and `elroi/update-page-content` (`page_id`, `changes`, same rules as `elroi_update()`). Both need the right to edit that page; filter `elroi_abilities` (false) turns them off.
- A guard on the meta: `update_post_meta()` with an array is merged into the stored fields instead of replacing them, and a string that is not valid JSON is refused. A complete JSON string still replaces the content on purpose (migrations).

## Links and domain changes

Sites are built on a temporary address and moved later. Addresses of the site itself typed in a `link` field or a `[words](url)` link are stored as paths (`/about/`), so they follow the domain. The plugin remembers earlier addresses of the site (option `elroi_previous_hosts`, filled when the home URL changes; filter `elroi_site_hosts`), and `elroi_link()` / `elroi_rich()` print links stored with an old address with the current one, in the same tab. Blog posts and other block-editor content are not covered: run a search-replace on them after the move.

## Search engines (Rank Math)

Designed pages keep their words in fields, so the page body WordPress stores is empty. The plugin makes SEO tools see the real page:

- **Rank Math content analysis reads the page as visitors see it.** On the edit screen of a page built from fields (a registered template, or the posts page), the form's current values are posted to the page itself as a preview (nothing is saved, never cached, only for someone who can edit the page) and what is inside `<main>` goes to Rank Math through its `rank_math_content` filter. It runs when the screen opens and a moment after every change, so the score follows what the client types; Update saves the score. Filters: `elroi_seo_selector` (default `main`), `elroi_seo_page_from_theme` (which pages), `elroi_seo_analysis` (false turns it off). Markup with `aria-hidden="true"`, `hidden` or `data-elroi-seo="skip"` (decorative copies such as split words or marquee repeats), scripts, styles and SVG icons are left out.
- **Content AI:** Rank Math's "Content AI" check only passes with a paid Content AI plan and says nothing about the page, so it is left out of the score. Settings > Reading > Rank Math score turns it back on (filter `elroi_seo_skip_content_ai`).
- **Sitemap photos:** the photos of every image and gallery field go into the page's entry in Rank Math's (or Yoast's) XML sitemap, with their alt text.
- **Site search** finds pages by the words in their fields, and those pages get an excerpt from their fields when they have none (search results, and Rank Math's `%excerpt%`). Filter `elroi_search_fields` (false turns it off).
- **Alt text:** `elroi_image()` uses the photo's alt text from the Media Library; when it is empty, the caption, then the title if it reads like words (not `IMG_0423`). Pass `'alt' => ''` for a decorative image. Gallery fields warn about photos without alt text.
- **Link words:** give the text field that labels a button or link `'link_text' => true`; the form warns when it says nothing about where the link goes ("Learn more", "Click here"; list in the `elroi_vague_link_words` filter).
- **Inline links:** give a `textarea` or `paragraphs` field `'rich' => true` (the form explains the syntax) and print it with `elroi_rich()` or `elroi_paragraphs( $list, '', true )`: `[our kitchens](/kitchens/)` becomes a link. Internal links help Rank Math's score and visitors.

Theme rules that go with it: one `<h1>` per page and headings in order, the template's real content inside `<main>`, decorative duplicates marked `aria-hidden="true"`.

## Client access

- Clients get the WordPress **Editor** role. They can use Site Settings (`elroi_client_cap()`, default `edit_others_pages`, filter `elroi_client_cap`) and every form.
- Locked pages (`elroi_is_locked_page()`: registered templates with `lock`, the home page, the posts page; filter `elroi_is_locked_page`): only administrators can change the template, slug, parent, status or order, or delete them.
- wp-admin shows the "How to edit" guide (generated from the registered templates and settings) and a dashboard widget.

## Testing

`tests/` runs on a local WordPress Playground with the demo theme in `examples/demo-theme/`:

```bash
mise exec node@22 -- npx -y @wp-playground/cli@3.1.56 server --port=9400 --login --blueprint=tests/blueprint.json \
  --mount="$PWD:/wordpress/wp-content/plugins/elroi-site-editor" \
  --mount="$PWD/examples/demo-theme:/wordpress/wp-content/themes/elroi-demo" \
  --mount="$PWD/tests:/wordpress/elroi-tests"
curl -s http://127.0.0.1:9400/elroi-tests/run.php          # JSON: passed / failed
open http://127.0.0.1:9400/elroi-tests/login.php            # signs in as admin (local only)
```

Playground needs Node 22 (it fails on Node 26). Every change must keep all checks passing. On a client site, the same round-trip check applies: render each page's form, post it back unchanged, and confirm the stored content and the rendered page are identical.

## Releases and updates

- Bump `Version:` and `ELROI_SE_VERSION` in `elroi-site-editor.php`, commit, then `bin/release.sh --publish` (builds `dist/elroi-site-editor.zip` without tests/examples and publishes a GitHub release `vX.Y.Z` with it).
- Sites update from the public repo `titosoriano/elroi-site-editor` through the normal WordPress Update button, with nothing to set up. The check is cached for 6 hours (GitHub allows 60 anonymous API requests per hour per server). Optional: a read-only token in the site's `wp-config.php` (`define( 'ELROI_SE_GITHUB_TOKEN', '...' );`) lifts that limit and is required only if the repo is made private again. Never commit a token.
- The repository is public: never commit client data, passwords, tokens or site URLs that should stay private.
- Data format changes must stay backward compatible: existing pages must render the same after an update.
