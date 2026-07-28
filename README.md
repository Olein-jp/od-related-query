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

## MVP limitations

- Matching uses an `OR` relationship across all viewable taxonomies.
- Results are ordered by date; relevance scoring is not included.
- A template editor has no concrete source post, so its canvas shows the
  no-results state. The frontend resolves the currently displayed post.
- Editing an individual post supplies its post ID to the REST API for an
  accurate editor preview.
