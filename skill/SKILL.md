---
name: elroi-site-editor
description: Build client WordPress sites that clients can edit themselves with the ElRoi Site Editor plugin (ElRoi Marketing). Use when creating or changing a custom WordPress theme, landing page or page template for an ElRoi client, when a site has the elroi-site-editor plugin, or when asked to make a WordPress page's text and images editable in wp-admin without a page builder.
---

# ElRoi Site Editor

ElRoi Marketing builds custom WordPress themes, and every client must be able to change the words and pictures without touching the design. The **ElRoi Site Editor** plugin does that: the theme registers the fields of each page template and the plugin builds the wp-admin forms, saves with revisions, adds Site Settings, Editor access, design locks and a "How to edit" guide.

## When building or changing a client theme

1. Make sure the plugin is installed and active (repo `titosoriano/elroi-site-editor`, private; latest release zip). Check with `wp plugin list` or the site's MCP.
2. Read `reference.md` next to this file (the full field reference and rules). It is a copy of the plugin's `AGENTS.md`; if the plugin checkout is available, its `AGENTS.md` is the newest version.
3. Design the page first, then list every piece of copy and every photo on it, and register them with `elroi_register_template()` in the theme's `functions.php` (defaults = the designed copy). Contact facts used site-wide go in `elroi_register_settings()`.
4. Print every value with `elroi_text()`, `elroi_heading()`, `elroi_paragraphs()`, `elroi_link()` or `elroi_image()`. Never hard-code client copy or photos in templates, never echo raw values.
5. Verify: each page renders complete with an empty database (defaults), and saving each form unchanged leaves the stored content and the rendered HTML identical. Then create nothing else: the agency creates the client's Editor account.

## Do not

- Do not install ACF, a page builder, or another field plugin for client editing.
- Do not put tokens (`ELROI_SE_GITHUB_TOKEN`) in a repository; they live in the site's `wp-config.php`.
- Do not change a field's key on a live site without moving its stored data: the key is where the content is saved.
