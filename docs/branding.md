# Pyesio visual identity

The supplied `Pyesio-assets.zip` is the source for the product identity. Original PNGs are retained without redrawing or recoloring in `resources/images/brand/`:

- `pyesio-logo-transparent.png`: full logo and tagline on the authentication screens, presented on a light surface for legibility.
- `pyesio-icon-transparent.png`: app navigation mark, browser favicon, and touch icon.
- `pyesio-logo-white-background.png`: retained original reference for future uses requiring the white artwork.

The display name and tagline live in `config/brand.php`: **Pyesio — Hardware business, made simple.** Display branding is separate from the operational `APP_NAME`, so this visual release does not change existing session-cookie names or production environment values.

## Interface palette

| Role | Value |
| --- | --- |
| Primary navy | `#0B2447` |
| Action orange | `#FF861A` |
| Main text | `#192D48` |
| Secondary text | `#596B81` |
| Light canvas | `#F4F6FA` |
| Surface | `#FFFFFF` |
| Border | `#DCE3EC` |

Use navy text on orange actions and white text on navy navigation. Dark mode has separate text, surface, and border tokens; supplied navy artwork stays on a light backing. Error colors retain their distinct red meaning. Existing Instrument Sans fonts remain locally built. Shared styles in `resources/css/app.css` cover administration, store operations, account settings, and authentication; print styles remain neutral and readable.

Vite emits hashed image URLs alongside CSS/JS under `/build/assets/`. Blade resolves logos and icons through the manifest. The source PNGs do not need another public directory or broader `.htaccess` permissions. The old Laravel favicon, SVG, and touch-icon files are removed. Rebuild assets for every release and install the complete matching `build/` directory.

The supplied archive notes that its transparent variants may differ slightly from the original and includes no vector or dark-background artwork. These are source-asset notes, not additional product requirements.
