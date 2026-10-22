# ElRoi Site Editor

WordPress plugin by ElRoi Marketing that lets clients change the words and pictures of their website in simple wp-admin forms, while the design stays locked.

- The theme describes the editable fields of each page template; the plugin builds the forms.
- Every save is a revision that can be compared and restored.
- Site Settings: phones, addresses, email and other facts, changed once for the whole site.
- Clients use the Editor role; designed pages cannot be deleted, unpublished or moved.
- A "How to edit" guide and a dashboard widget for the client.
- Updates come from this public GitHub repository through the normal WordPress Update button.

## Starting a new client site

1. Install and activate the plugin (`elroi-site-editor.zip` from the latest release). Updates then arrive on their own.
2. Build the theme with the agent: it reads `AGENTS.md` (or the `elroi-site-editor` skill) and registers every editable field.
3. Create the client's user with the **Editor** role.

See `AGENTS.md` for the field reference, and `examples/demo-theme/` for a working theme.
