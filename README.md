# OD Related Query

OD Related Query adds a “Related Content” variation to the WordPress Query Loop
block.

Add the variation once to a single-post template. On the front end, it finds
content that:

- has the same post type as the displayed post;
- shares at least one term in a viewable taxonomy, including categories, tags,
  and public custom taxonomies; and
- is not the currently displayed post.

The variation displays three newest matches by default. Its Post Template is
made entirely from Core blocks, so themes can control its layout and styling.

Use the Query Loop sidebar to change the target post type, items per page, sort
order, and the taxonomies used for relationship matching. When editing an
individual post, the taxonomy choices follow that post's type. When editing a
single-post template, they follow the Query Loop's selected target post type and
update when that selection changes. Newly available public taxonomies are
selected automatically unless explicitly excluded. Leaving every relationship
taxonomy unchecked returns no related content.

In the template editor, the newest published post for the selected post type is
chosen automatically as the preview source. A different published post can be
selected in the Related Content panel. This setting affects only the editor's
REST preview; on the frontend, the currently displayed post is always used.

## Requirements

- WordPress 6.6 or later (tested up to WordPress 7.0)
- PHP 7.4 or later
- The WordPress block editor and the Core Query Loop block
- A block theme when placing Related Content in a Site Editor template
- Outbound HTTPS access to GitHub for automatic update checks

A block theme is not required when using the variation in the post editor. The
release ZIP includes all required PHP dependencies, so Composer and Node.js are
not required on the WordPress server. If GitHub cannot be reached, update checks
fail safely and the related-content functionality continues to work.

## Development

```sh
composer install
npm install
npm run build
npm run env:start
npm run env:seed
```

`npm run env:start` automatically selects available ports and prints the local
WordPress URLs after startup.

`npm run env:seed` creates or updates 20 sample posts, four categories, eight
tags, and generated featured images. It also adds the Related Content variation
once to the active block theme's single-post template. Existing customized
single templates are preserved.

Quality checks:

```sh
composer lint
composer analyse
npm run lint:js
npm run test:unit:js -- --runInBand
```

WordPress integration tests use the isolated wp-env configuration:

```sh
npm run env:test:start
npm run env:test:composer:install
npm run test:php
npm run env:test:stop
```

## Release

Create and verify an installable release ZIP:

```sh
npm run release:build
npm run release:test
```

The build fails when the version in `package.json`, the plugin header, the
plugin version constant, and `RELEASE_VERSION` do not match. Pushing a matching
`v*` tag builds and tests the ZIP before attaching it to a GitHub Release.

## Current limitations

- Matching uses an `OR` relationship across the selected taxonomies.
- Relevance ordering counts shared terms equally and does not apply a separate
  weight to each taxonomy.
- Editing an individual post supplies its post ID to the REST API for an
  accurate editor preview.
