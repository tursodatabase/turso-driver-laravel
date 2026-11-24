# Turso Driver Laravel Docs

This directory contains the GitHub Pages site for **Turso Driver Laravel**: a landing page and a user manual.

The manual in `docs/user-manual/` is the **source of truth** for package documentation. The GitHub wiki is kept as a legacy reference and should point readers here.

## Local preview

From the repository root:

```bash
cd docs
bundle install
bundle exec jekyll serve --baseurl /turso-driver-laravel
```

Then open `http://127.0.0.1:4000/turso-driver-laravel/`.

## Structure

- `index.html` — landing page
- `user-manual/` — manual pages (Markdown, `manual` layout)
- `_data/nav.yml` — manual sidebar navigation
- `assets/css/` — landing and manual styles
- `images/` — logo and static assets

The palette is sampled from `turso-php-ecosystem-logos/turso-driver-laravel.png`: lime `#94CF1A`, black ink, white paper.
