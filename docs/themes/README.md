# Custom storefront themes — developer guide

Stores can upload their own storefront design built with [Twig](https://twig.symfony.com/doc/3.x/) templates, plain CSS and plain JavaScript (decision #121). The theme controls how the home, listing, category, search, product, cart, account, content and error pages look. The platform keeps running the catalogue, prices, cart, checkout and payments.

The fastest way to start is the **Starter theme** (`backend/resources/theme-starter/`, downloadable from Vendor panel › Appearance › Custom theme). It is complete, passes validation, is responsive and supports Arabic (RTL) and English (LTR).

Contents:

1. [Getting started](#getting-started)
2. [Package structure](#package-structure)
3. [theme.json](#themejson)
4. [Theme settings](#theme-settings)
5. [Templates](#templates)
6. [Functions and filters](#functions-and-filters)
7. [Assets and the content security policy](#assets-and-the-content-security-policy)
8. [Languages and right-to-left](#languages-and-right-to-left)
9. [Runtime: cart, wishlist and coupons](#runtime-cart-wishlist-and-coupons)
10. [Upload, preview and publishing](#upload-preview-and-publishing)
11. [Limits](#limits)
12. [Security rules](#security-rules)
13. [Allowed tags, filters, functions and tests](#allowed-tags-filters-functions-and-tests)
14. [Variable reference](#variable-reference)
15. [FAQ](#faq)

---

## Getting started

1. Download the Starter theme ZIP and unpack it.
2. Change `name` and `version` in `theme.json`.
3. Edit `templates/*.twig`, `assets/css/theme.css` and the texts in `locales/en.json` / `locales/ar.json`.
4. ZIP the folder — `theme.json` must be at the top of the ZIP or inside one top folder — and upload it.
5. Read the validation report, open the preview link, check every page in both languages and on a phone, then submit the version for review.

No build tool is needed. To create the ZIP from a terminal:

```bash
cd my-theme && zip -r ../my-theme.zip . -x '.*' -x '*/.*'
```

## Package structure

```text
my-theme/
├── theme.json                 required: name, version, engine, author, supports, settings
├── templates/                 Twig templates (sub-folders allowed)
│   ├── layout.twig            shared shell (required)
│   ├── home.twig  listing.twig  categories.twig  category.twig
│   ├── product.twig  search.twig  cart.twig  account.twig
│   ├── page.twig  404.twig  500.twig
│   └── partials/
│       ├── header.twig  footer.twig  preferences.twig
│       ├── product-card.twig  product-grid.twig  product-rail.twig
│       └── macros.twig
├── locales/
│   ├── en.json                required
│   └── ar.json                required
└── assets/
    ├── css/theme.css
    ├── js/theme.js
    ├── img/*.svg|png|jpg|webp|gif|avif|ico
    └── fonts/*.woff2|woff|ttf|otf
```

Rules enforced by the validator (`ThemePackageValidator`):

- Only `theme.json`, `templates/`, `locales/`, `assets/` and the optional root files `README.md`, `LICENSE`, `LICENSE.md`, `LICENSE.txt`, `CHANGELOG.md`, `screenshot.png|jpg|webp` (stored, never served).
- `templates/` holds only `.twig` files; `locales/` holds exactly `en.json` and `ar.json` (JSON objects whose values are texts; nested objects allowed, keys must not be plain numbers such as `"404"`).
- `assets/` allows `css, js, png, jpg, jpeg, webp, gif, avif, svg, ico, woff, woff2, ttf, otf`; images and fonts must really be what their extension says (magic bytes are checked).
- File names may only use letters, digits, `.`, `_`, `-`, `/`. Hidden files (`.htaccess`, `.git/…`), symbolic links, absolute paths, `..`, backslashes and server files (`.php`, `.sh`, `.ini`, …) are rejected. PHP tags are rejected in every text file. `__MACOSX/`, `.DS_Store` and `Thumbs.db` are ignored.

## theme.json

```json
{
    "name": "Starter",
    "version": "1.0.0",
    "engine": "nogrgr-twig/1",
    "author": { "name": "Nogrgr", "email": "themes@example.com", "url": "https://example.com" },
    "description": "A clean, responsive starter theme with right-to-left support.",
    "supports": ["home", "listing", "categories", "category", "product", "search", "cart", "account", "page", "404", "500"],
    "rtl": true,
    "settings": []
}
```

| Field | Required | Rule |
|---|---|---|
| `name` | yes | 1–60 characters |
| `version` | yes | semantic version (`1.0.0`, `2.1.0-beta.1`); raise it for every upload |
| `engine` | yes | always `nogrgr-twig/1` |
| `author.name` | yes | 1–80 characters; `author.email` (valid e-mail) and `author.url` (`https://` only) are optional |
| `description` | no | up to 500 characters |
| `supports` | yes | every page template: `home, listing, categories, category, product, search, cart, account, page, 404, 500` |
| `rtl` | no | default `true`; `false` gives a warning because Arabic shoppers would get a left-to-right page |
| `settings` | no | list of vendor settings (next chapter) |

## Theme settings

Each entry of `settings` becomes a field in the theme settings form of the vendor panel.

| Key | Rule |
|---|---|
| `id` | `lower_snake_case`, starts with a letter, up to 40 characters, unique |
| `type` | `color`, `text`, `textarea`, `number`, `select`, `toggle`, `font` |
| `label` | `{"en": "…", "ar": "…"}`, up to 80 characters each |
| `help` | optional, `{"en": "…", "ar": "…"}`, up to 200 characters each |
| `default` | required, must be valid for the type |

| Type | Value | Extra keys |
|---|---|---|
| `color` | `#RRGGBB` | — |
| `text` | string | `max_length` 1–500 (default 500) |
| `textarea` | string | `max_length` 1–2000 (default 2000) |
| `number` | whole number | `min`, `max` (default 0 / 100000) |
| `select` | one of the option values | `options`: 1–30 × `{"value": "…", "label": {"en": "…", "ar": "…"}}` |
| `toggle` | `true` / `false` | — |
| `font` | `outfit`, `inter`, `manrope`, `noto-sans-arabic`, `ibm-plex-sans-arabic`, `tajawal` | — |

Read a value with `setting('id')`. Font settings return a CSS `font-family` value (`'Tajawal', system-ui, sans-serif`) and the platform loads the chosen font. Saved values that are no longer valid fall back to the default.

```json
{"id": "accent", "type": "color", "label": {"en": "Accent colour", "ar": "اللون المميز"}, "default": "#0F766E"},
{"id": "show_hero", "type": "toggle", "label": {"en": "Show the home page banner", "ar": "إظهار البانر في الصفحة الرئيسية"}, "default": true},
{"id": "products_per_row", "type": "select", "label": {"en": "Products per row", "ar": "عدد المنتجات في الصف"},
 "options": [{"value": "3", "label": {"en": "3 products", "ar": "3 منتجات"}}, {"value": "4", "label": {"en": "4 products", "ar": "4 منتجات"}}],
 "default": "4"},
{"id": "body_font", "type": "font", "label": {"en": "Body font", "ar": "خط النص"}, "default": "tajawal"}
```

Turn settings into CSS custom properties with a `style` attribute (inline style attributes are allowed by the CSP):

```twig
<html lang="{{ store.locale }}" dir="{{ store.dir }}"
      style="--accent: {{ setting('accent') }}; --font-body: {{ setting('body_font') }};">
```

## Templates

Templates are loaded by name relative to `templates/` (`"layout.twig"`, `"partials/product-card.twig"`). Output is HTML-escaped automatically.

| Template | Page | Main variables |
|---|---|---|
| `layout.twig` | shared shell of every page | globals |
| `home.twig` | home page | `home.hero`, `home.promo`, `home.categories`, `home.flash_sale`, `home.flash_deals`, `home.best_sellers`, `home.new_in`, `home.top_rated`, `home.clearance` |
| `listing.twig` | best sellers, new in, top rated, offers | `listing.*` |
| `category.twig` | one category | `listing.*`, `category`, `subcategories` |
| `search.twig` | search results | `listing.*`, `search.term`, `search.popular` |
| `categories.twig` | all categories | `categories` |
| `product.twig` | product page | `product.*` (media, variants, attributes, reviews, delivery, related) |
| `cart.twig` | cart | `cart_page.*` |
| `account.twig` | customer orders list and wishlist | `account.section` (`orders` / `wishlist`), `account.orders`, `account.wishlist`, `account.links` |
| `page.twig` | content pages (About, Privacy, Contact …) | `cms_page.*` |
| `404.twig`, `500.twig` | error pages | `error.code`, `error.title`, `error.message` |

Globals in every template: `store`, `menus`, `customer`, `cart`, `request`, `theme`, `settings`, `seo`, `template`. Templates receive plain data only (lists and key/value objects read with dots), never PHP objects or methods. The full list with types and examples is in the [Variable reference](#variable-reference).

**Always built-in pages.** Checkout, payment, sign-in, registration, password reset, order detail / tracking / invoice, returns, profile and addresses always render with the store's built-in theme. Link to them with `url('checkout')`, `url('login')`, `url('register')`, `url('returns')`, `url('profile')`, `url('addresses')`, and `account.orders[].url` / `tracking_url`.

### layout.twig

```twig
<!DOCTYPE html>
<html lang="{{ store.locale }}" dir="{{ store.dir }}" style="--accent: {{ setting('accent') }};">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{ head() }}
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body class="tpl-{{ template }}">
    {% include "partials/header.twig" %}
    <main id="main">{% block content %}{% endblock %}</main>
    {% include "partials/footer.twig" %}
    <div data-nt-status role="status" aria-live="polite"></div>
    {{ scripts() }}
    {{ script('js/theme.js') }}
</body>
</html>
```

`head()` must be printed inside `<head>` on every page (SEO tags, security policy, CSRF meta tag and the `#sf-context` store data island) — the validator warns about pages without it. `scripts()` prints the platform runtime and belongs right before `</body>`.

### A page template

```twig
{% extends "layout.twig" %}
{% import "partials/macros.twig" as ui %}

{% block content %}
    {{ ui.breadcrumbs(listing.breadcrumbs, store.home_url) }}
    <h1>{{ listing.title }}</h1>

    {% if listing.products is not empty %}
        <ul class="product-grid">
            {% for item in listing.products %}
                <li>{% include "partials/product-card.twig" with {card: item} only %}</li>
            {% endfor %}
        </ul>
        {{ ui.pagination(listing.pagination) }}
    {% else %}
        {{ ui.empty(t('listing.empty_title'), t('listing.empty_text'), url('categories'), t('common.browse_categories')) }}
    {% endif %}
{% endblock %}
```

### A product card partial

```twig
{# partials/product-card.twig — include with {card: item} only #}
<article class="card">
    <a class="card__media" href="{{ card.url }}">
        <img src="{{ card.image ?: asset('img/placeholder.svg') }}" alt="{{ card.name }}" loading="lazy" width="480" height="480">
        {% if card.discount_percent %}<span class="badge">{{ t('product.discount', {percent: card.discount_percent}) }}</span>{% endif %}
    </a>
    <button type="button" data-nt-favorite data-product-id="{{ card.id }}"
            aria-pressed="{{ card.favorited ? 'true' : 'false' }}" aria-label="{{ t('product.favorite', {name: card.name}) }}">♥</button>
    <h3><a href="{{ card.url }}">{{ card.name }}</a></h3>
    <p><strong>{{ card.price }}</strong>{% if card.compare_price %} <s>{{ card.compare_price }}</s>{% endif %}</p>
    {% if not card.in_stock %}
        <span>{{ t('product.out_of_stock') }}</span>
    {% elseif card.has_variants %}
        <a href="{{ card.url }}">{{ t('product.choose_options') }}</a>
    {% else %}
        <button type="button" data-nt-add-to-cart data-product-id="{{ card.id }}" data-quantity="1">{{ t('product.add_to_cart') }}</button>
    {% endif %}
</article>
```

### Store preferences (language, currency, delivery country)

```twig
<form action="{{ url('preferences') }}" method="post">
    {{ csrf_field() }}
    <input type="hidden" name="redirect" value="{{ request.path }}">
    <select name="locale">
        {% for locale in store.locales %}
            <option value="{{ locale.code }}"{% if locale.current %} selected{% endif %}>{{ locale.name }}</option>
        {% endfor %}
    </select>
    <select name="currency">
        {% for currency in store.currencies %}
            <option value="{{ currency.code }}"{% if currency.code == store.currency %} selected{% endif %}>{{ currency.code }}</option>
        {% endfor %}
    </select>
    <select name="country_id">
        {% for country in store.countries %}
            <option value="{{ country.id }}"{% if store.country and country.id == store.country.id %} selected{% endif %}>{{ country.name }}</option>
        {% endfor %}
    </select>
    <button type="submit">{{ t('preferences.apply') }}</button>
</form>
```

### Safe HTML

`product.description_html` and `cms_page.body_html` are sanitised by the platform and are already marked safe: print them with `{{ product.description_html }}`. There is no `raw` filter.

## Functions and filters

| Function / filter | Returns | Example |
|---|---|---|
| `url(name, params)` | store URL (`#` + warning for unknown names) | `{{ url('product', {id: product.id, slug: product.slug}) }}` |
| `asset(path)` | URL of a file in `assets/` (empty + warning when missing) | `{{ asset('css/theme.css') }}` |
| `script(path)` | `<script src nonce defer>` tag for a `.js` file in `assets/` | `{{ script('js/theme.js') }}` |
| `money(minor)` / `\|money` | amount in minor units formatted in the display currency | `{{ money(1999) }}`, `{{ 1999\|money }}` |
| `t(key, params)` / `\|t` | theme text from `locales/<locale>.json` (English fallback, else the key + warning) | `{{ t('cart.items', {count: cart.count}) }}`, `{{ 'cart.title'\|t }}` |
| `st(key, params)` | platform storefront text | `{{ st('cart.title') }}` |
| `setting(id)` | theme setting value | `{% if setting('show_hero') %}` |
| `head()` | head HTML (SEO, CSP meta, CSRF meta, store data island) | `{{ head() }}` |
| `scripts()` | platform runtime script tag | `{{ scripts() }}` |
| `csrf_field()` | `<input type="hidden" name="_token" …>` | `<form method="post">{{ csrf_field() }}…</form>` |
| `csrf_token()` | the CSRF token | `data-token="{{ csrf_token() }}"` |

`url()` names: `home`, `search` (`{q}`), `categories`, `category` (`{slug}`), `product` (`{id, slug}`), `page` (`{slug}`), `best_sellers`, `new_in`, `top_rated`, `offers`, `cart`, `checkout`, `account`, `orders`, `wishlist`, `returns`, `profile`, `addresses`, `support`, `login`, `register`, `logout` (POST form), `preferences` (POST form), `newsletter` (POST form). Parameters that are not route parameters become the query string (`url('offers', {page: 2, sort: 'price_asc'})`).

```twig
<a href="{{ url('search', {q: 'shoes'}) }}">{{ t('search.title') }}</a>
<form method="post" action="{{ url('logout') }}">{{ csrf_field() }}<button>{{ t('account.sign_out') }}</button></form>
{{ product.reviews.average|number_format(1) }}
{{ order.placed_at|date('Y-m-d') }}
{{ product.attributes|column('name')|join(' / ') }}
{% set images = product.media|filter(m => m.type == 'image') %}
```

## Assets and the content security policy

Store pages are served with a strict CSP:

- **No inline JavaScript.** `<script>…</script>` blocks, `onclick="…"`-style attributes and `javascript:` URLs are blocked. Put code in `assets/js/` and load it with `{{ script('js/theme.js') }}` (the tag carries the page nonce). JSON data islands (`<script type="application/json">`) are allowed.
- **No remote files.** Scripts, styles, fonts and images from other hosts are blocked; bundle them in `assets/` and use relative `url()` paths in CSS. CSS may not contain remote `@import` / `url()`, `expression(`, `behavior:` or `-moz-binding` (note: the check matches `behavior:` literally, so avoid the `scroll-behavior` property; use `scrollIntoView({behavior})` from JavaScript instead).
- **Inline styles are allowed** (`style="…"` attributes and `<style>` elements), which is how settings become CSS custom properties.
- **SVG** files may not contain `<script>`, `on…=` attributes, `javascript:`, `<foreignObject>`, `<iframe>`, `<embed>`, `<object>`, `<use>`, `DOCTYPE` / `ENTITY` declarations, `href` / `src` links (except `#fragment`) or external style imports.
- Use `object-fit: contain` for product images so products are never cropped.

```css
/* assets/css/theme.css */
@font-face { font-family: "Brand"; src: url("../fonts/brand.woff2") format("woff2"); font-display: swap; }
.card__img { inline-size: 100%; block-size: 100%; object-fit: contain; }
.badge { position: absolute; inset-inline-start: 10px; }  /* logical property: works in RTL and LTR */
```

## Languages and right-to-left

Every UI text goes through `t()`. Keys are dot paths into `locales/en.json` and `locales/ar.json`; missing Arabic keys fall back to English, missing keys in both print the key and produce a validation warning. Placeholders start with `:`.

```json
// locales/en.json
{ "header": { "hello": "Hi, :name", "cart": "Cart" }, "cart": { "items": ":count items" } }
// locales/ar.json
{ "header": { "hello": "مرحبًا، :name", "cart": "السلة" }, "cart": { "items": ":count منتج" } }
```

```twig
{{ t('header.hello', {name: customer.name}) }}
```

Store content (product, category, banner and page texts) already arrives in the shopper's language.

`store.locale` is `en` or `ar`; `store.dir` is `ltr` or `rtl`. Put both on `<html>` and write the CSS with logical properties (`margin-inline-start`, `padding-inline-end`, `inset-inline-start`, `border-inline-end`, `text-align: start`) so one stylesheet works in both directions. Use `[dir="rtl"]` only for what has to be mirrored (arrow icons, gradients).

## Runtime: cart, wishlist and coupons

`{{ scripts() }}` loads the platform runtime (`resources/js/storefront/custom-theme.js`). Themes contain no store logic: they mark elements with `data-nt-*` attributes and the runtime connects them to the Store API.

| Markup | Behaviour |
|---|---|
| `[data-nt-add-to-cart]` + `data-product-id`, optional `data-variant-id`, `data-quantity` | adds to the cart, updates the counters, writes a status message |
| `form[data-nt-product-form]` with a submit button `[data-nt-add-to-cart][data-product-id]` | same, reading the fields `name="variant_id"` and `name="quantity"` |
| `[data-nt-cart-count]` | text set to the new item count after every cart change (`hidden` when 0) |
| `input[data-nt-cart-qty][data-line-id]` | on change: sets the line quantity, then reloads the page |
| `[data-nt-cart-remove][data-line-id]` | removes the line, then reloads |
| `form[data-nt-coupon-form]` (field `name="code"`) | applies the coupon, then reloads (errors go to the status line) |
| `[data-nt-coupon-remove]` | removes the coupon, then reloads |
| `[data-nt-favorite][data-product-id]` | toggles the wishlist and sets `aria-pressed` on every heart of that product; guests go to the sign-in page |
| `select[data-nt-variant-select]` in the product form + `<script type="application/json" data-nt-variants>` | on change: updates `[data-nt-price]` and `[data-nt-compare]` (hidden without a compare price), disables add-to-cart for sold-out variants, sets the quantity `max`; fires `nt:variant-changed` on `document` |
| `form[data-nt-newsletter-form]` (field `name="email"`, `action="{{ url('newsletter') }}"`) | subscribes without leaving the page |
| `[data-nt-status]` (use `role="status" aria-live="polite"`) | receives success / error messages (class `is-visible`, plus `is-error` for errors). Optional texts used when the server sends none: `data-msg-added`, `data-msg-error`, `data-msg-favorited`, `data-msg-unfavorited`, `data-msg-subscribed` |

When the page has a `[data-nt-status]` element, the platform's own toast region is hidden so messages only appear in your status line.

```twig
<form data-nt-product-form>
    <script type="application/json" data-nt-variants>{{ product.variants|json }}</script>
    <select name="variant_id" data-nt-variant-select>
        {% for variant in product.variants %}
            <option value="{{ variant.id }}"{% if not variant.in_stock %} disabled{% endif %}>{{ variant.label }}</option>
        {% endfor %}
    </select>
    <input type="number" name="quantity" value="1" min="1">
    <button type="submit" data-nt-add-to-cart data-product-id="{{ product.id }}">{{ t('product.add_to_cart') }}</button>
</form>
<span data-nt-price>{{ product.price }}</span> <s data-nt-compare>{{ product.compare_price }}</s>

<input type="number" value="{{ line.quantity }}" min="1" data-nt-cart-qty data-line-id="{{ line.id }}">
<button type="button" data-nt-cart-remove data-line-id="{{ line.id }}">{{ t('cart.remove') }}</button>

<form data-nt-coupon-form><input name="code" required><button type="submit">{{ t('cart.coupon_apply') }}</button></form>
<button type="button" data-nt-coupon-remove>{{ t('cart.coupon_remove') }}</button>

<div data-nt-status role="status" aria-live="polite" data-msg-added="{{ t('status.added') }}"></div>
```

Your own `assets/js/theme.js` can add interface behaviour (menus, galleries, sliders). The Starter theme's script shows a mobile menu toggle, gallery thumbnails, swipeable rails (touch, trackpad and mouse drag) and a scroll reveal that respects `prefers-reduced-motion`.

## Upload, preview and publishing

Every upload creates a new version; the live store does not change until a version is approved and published.

1. **Upload** the ZIP. The platform checks the archive and files, validates `theme.json`, compiles and security-checks every template in the sandbox and renders every page with sample data.
2. **Validation report.** Errors block the version (with file and line). Warnings flag missing texts (`t()`), missing assets (`asset()`), unknown `url()` names, pages without `head()`, inline scripts / handlers and remote files — fix them too.
3. **Preview link.** Only you see the preview; shoppers keep the current design. Check every page in Arabic and English and on a phone.
4. **Submit for review.**
5. **Platform approval.** The platform team approves the version or returns it with notes.
6. **Publish.** Shoppers see the new design on their next page load.
7. **Rollback.** Switch back to any earlier published version at any time.

To check the Starter theme locally:

```bash
cd backend
mkdir -p /tmp/theme && rm -f /tmp/theme/starter.zip
(cd resources/theme-starter && zip -qr /tmp/theme/starter.zip .)
php artisan tinker --execute '$r = app(App\Modules\Storefront\Support\CustomThemes\ThemePackageValidator::class)->validate("/tmp/theme/starter.zip"); dump($r->errors, $r->warnings);'
```

## Limits

From `CustomThemeLimits::documented()`:

| Limit | Value |
|---|---|
| ZIP size (`zip_max_mb`) | 10 MB |
| Unpacked size (`unpacked_max_mb`) | 25 MB |
| Files in a package (`max_files`) | 300 |
| One asset file (`asset_max_mb`) | 2 MB |
| One template (`template_max_kb`) | 100 KB |
| All templates together (`templates_max_kb`) | 1024 KB |
| theme.json and each locale file (`json_max_kb`) | 200 KB |
| Render time per page (`render_max_ms`) | 1500 ms |
| Loop iterations + template / block / macro calls per render (`render_max_ticks`) | 25000 |
| Nested template / block / macro calls (`render_max_depth`) | 40 |
| Largest literal range `1..n` (`max_range`) | 500 |
| Settings in theme.json (`max_settings`) | 60 |
| Versions kept per store (`max_versions`) | 20 |

Also: path length ≤ 180 characters; an entry may not be compressed more than 100:1 (when larger than 64 KB); ranges must use literal whole numbers (`{% for i in 1..5 %}`). A render that exceeds the time or tick budget stops and the error page is shown.

## Security rules

- Templates run in the Twig sandbox, globally. No PHP runs: PHP tags are rejected; templates receive plain arrays and scalars (plus pre-sanitised HTML), never objects — no method or property calls are allowed.
- Only the tags, filters, functions and tests below are allowed; everything else fails at upload with an error.
- Autoescaping is always on (`html`) and cannot be switched off.
- Checkout, payment, sign-in / registration / password, order detail / tracking / invoice, returns, profile and addresses always use the platform's built-in pages.
- No remote files, no inline scripts, no event-handler attributes (CSP).
- Templates are loaded only from the package itself: no file system, other themes or application views.

## Allowed tags, filters, functions and tests

From `ThemeSecurityPolicy`:

| Kind | Allowed | Not allowed (examples) |
|---|---|---|
| Tags | `apply`, `block`, `embed`, `extends`, `for`, `from`, `if`, `import`, `include`, `macro`, `set`, `verbatim`, `with` | `autoescape`, `sandbox`, `do`, `flush`, `deprecated`, `use`, `guard`, `types`, `cache` |
| Filters | `abs`, `batch`, `capitalize`, `column`, `date`, `default`, `e`, `escape`, `filter`, `first`, `join`, `json_encode`, `keys`, `last`, `length`, `lower`, `map`, `merge`, `nl2br`, `number_format`, `replace`, `reverse`, `round`, `slice`, `sort`, `split`, `striptags`, `title`, `trim`, `upper`, `url_encode`, `money`, `t`, `json` | `raw`, `convert_encoding`, `format`, `spaceless` |
| Functions | `block`, `parent`, `include`, `cycle`, `max`, `min`, `date`, `url`, `asset`, `script`, `money`, `t`, `st`, `head`, `scripts`, `csrf_field`, `csrf_token`, `setting` (`range` is enabled only for the literal `1..5` operator) | `attribute`, `constant`, `source`, `range()` calls, `random`, `dump`, `template_from_string` |
| Tests | `defined`, `divisible by`, `empty`, `even`, `iterable`, `none`, `null`, `odd`, `same as`, `mapping`, `sequence` | `constant` |
| Methods / properties | none | every object method or property |

## Variable reference

Generated from `ThemeFixtures` (sample data run through the real presenters), so it always matches what templates receive. Regenerate with `php artisan storefront:theme-docs`.

<!-- variables:begin (generated by php artisan storefront:theme-docs) -->

### Globals (every template)

| Variable | Type | Example |
|---|---|---|
| `store` | object |  |
| `store.name` | string | `Nogrgr Demo Store` |
| `store.logo_url` | null |  |
| `store.home_url` | string | `https://your-store.com` |
| `store.locale` | string | `en` |
| `store.dir` | string | `ltr` |
| `store.locales` | list | `2 item(s)` |
| `store.locales[]` | object |  |
| `store.locales[].code` | string | `en` |
| `store.locales[].name` | string | `English` |
| `store.locales[].current` | bool | `true` |
| `store.currency` | string | `USD` |
| `store.currencies` | list | `2 item(s)` |
| `store.currencies[]` | object |  |
| `store.currencies[].code` | string | `USD` |
| `store.currencies[].name` | string | `US Dollar` |
| `store.country` | object |  |
| `store.country.id` | int | `191` |
| `store.country.iso2` | string | `sa` |
| `store.country.name` | string | `Saudi Arabia` |
| `store.countries` | list | `2 item(s)` |
| `store.countries[]` | object |  |
| `store.countries[].id` | int | `191` |
| `store.countries[].iso2` | string | `sa` |
| `store.countries[].name` | string | `Saudi Arabia` |
| `store.about` | string | `Everyday essentials, delivered fast.` |
| `store.copyright` | null |  |
| `store.year` | int | `2026` |
| `menus` | object |  |
| `menus.categories` | list | `1 item(s)` |
| `menus.categories[]` | object |  |
| `menus.categories[].id` | int | `1` |
| `menus.categories[].name` | string | `Fashion` |
| `menus.categories[].slug` | string | `fashion` |
| `menus.categories[].url` | string | `https://your-store.com/category/fashion` |
| `menus.categories[].image` | null |  |
| `menus.categories[].children` | list | `1 item(s)` |
| `menus.categories[].children[]` | object |  |
| `menus.categories[].children[].id` | int | `2` |
| `menus.categories[].children[].name` | string | `Tees` |
| `menus.categories[].children[].slug` | string | `tees` |
| `menus.categories[].children[].url` | string | `https://your-store.com/category/tees` |
| `menus.categories[].children[].image` | null |  |
| `menus.header` | list | `1 item(s)` |
| `menus.header[]` | object |  |
| `menus.header[].title` | string | `Offers` |
| `menus.header[].url` | string | `https://your-store.com/offers` |
| `menus.header[].external` | bool | `false` |
| `menus.footer` | object |  |
| `menus.footer.company` | list | `1 item(s)` |
| `menus.footer.company[]` | object |  |
| `menus.footer.company[].title` | string | `About us` |
| `menus.footer.company[].url` | string | `https://your-store.com/pages/about` |
| `menus.footer.company[].external` | bool | `false` |
| `menus.footer.help` | list | `1 item(s)` |
| `menus.footer.help[]` | object |  |
| `menus.footer.help[].title` | string | `Contact us` |
| `menus.footer.help[].url` | string | `https://your-store.com/pages/contact` |
| `menus.footer.help[].external` | bool | `false` |
| `menus.legal` | list | `1 item(s)` |
| `menus.legal[]` | object |  |
| `menus.legal[].title` | string | `Privacy policy` |
| `menus.legal[].url` | string | `https://your-store.com/pages/privacy` |
| `menus.legal[].external` | bool | `false` |
| `menus.social` | list | `1 item(s)` |
| `menus.social[]` | object |  |
| `menus.social[].network` | string | `instagram` |
| `menus.social[].url` | string | `https://instagram.com/nogrgr` |
| `menus.social[].label` | string | `Instagram` |
| `customer` | object |  |
| `customer.signed_in` | bool | `false` |
| `customer.name` | null |  |
| `cart` | object |  |
| `cart.count` | int | `2` |
| `cart.url` | string | `https://your-store.com/cart` |
| `request` | object |  |
| `request.path` | string | `/` |
| `request.url` | string | `https://your-store.com` |
| `request.query` | null |  |
| `theme` | object |  |
| `theme.name` | string | `Starter` |
| `theme.version` | string | `1.0.0` |
| `settings` | list |  |

### `templates/home.twig` (pages.home)

| Variable | Type | Example |
|---|---|---|
| `home` | object |  |
| `home.hero` | list | `1 item(s)` |
| `home.hero[]` | object |  |
| `home.hero[].title` | string | `New season` |
| `home.hero[].subtitle` | string | `Fresh picks for every day` |
| `home.hero[].image` | string | `/storage/banners/hero.webp` |
| `home.hero[].mobile_image` | null |  |
| `home.hero[].url` | string | `https://your-store.com/new-in` |
| `home.hero[].cta_label` | string | `Shop now` |
| `home.hero[].alt` | string | `New season` |
| `home.hero[].width` | int | `1600` |
| `home.hero[].height` | int | `600` |
| `home.promo` | list |  |
| `home.categories` | list | `1 item(s)` |
| `home.categories[]` | object |  |
| `home.categories[].id` | int | `1` |
| `home.categories[].name` | string | `Fashion` |
| `home.categories[].slug` | string | `fashion` |
| `home.categories[].url` | string | `https://your-store.com/category/fashion` |
| `home.categories[].image` | null |  |
| `home.flash_deals` | list | `2 item(s)` |
| `home.flash_deals[]` | object |  |
| `home.flash_deals[].id` | int | `101` |
| `home.flash_deals[].name` | string | `Cotton tee` |
| `home.flash_deals[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `home.flash_deals[].image` | string | `/storage/products/tee.webp` |
| `home.flash_deals[].image_hover` | null |  |
| `home.flash_deals[].price` | string | `$19.99` |
| `home.flash_deals[].price_minor` | int | `1999` |
| `home.flash_deals[].compare_price` | string | `$24.99` |
| `home.flash_deals[].discount_percent` | int | `20` |
| `home.flash_deals[].flash_ends_at` | null |  |
| `home.flash_deals[].rating` | float | `4.6` |
| `home.flash_deals[].review_count` | int | `18` |
| `home.flash_deals[].star_seller` | bool | `true` |
| `home.flash_deals[].in_stock` | bool | `true` |
| `home.flash_deals[].has_variants` | bool | `false` |
| `home.flash_deals[].favorited` | bool | `false` |
| `home.flash_deals[].sold_count` | int | `240` |
| `home.flash_deals[].low_stock` | int | `6` |
| `home.flash_deals[].badge` | string | `Best seller` |
| `home.clearance` | list |  |
| `home.flash_sale` | null |  |
| `home.best_sellers` | list | `2 item(s)` |
| `home.best_sellers[]` | object |  |
| `home.best_sellers[].id` | int | `101` |
| `home.best_sellers[].name` | string | `Cotton tee` |
| `home.best_sellers[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `home.best_sellers[].image` | string | `/storage/products/tee.webp` |
| `home.best_sellers[].image_hover` | null |  |
| `home.best_sellers[].price` | string | `$19.99` |
| `home.best_sellers[].price_minor` | int | `1999` |
| `home.best_sellers[].compare_price` | string | `$24.99` |
| `home.best_sellers[].discount_percent` | int | `20` |
| `home.best_sellers[].flash_ends_at` | null |  |
| `home.best_sellers[].rating` | float | `4.6` |
| `home.best_sellers[].review_count` | int | `18` |
| `home.best_sellers[].star_seller` | bool | `true` |
| `home.best_sellers[].in_stock` | bool | `true` |
| `home.best_sellers[].has_variants` | bool | `false` |
| `home.best_sellers[].favorited` | bool | `false` |
| `home.best_sellers[].sold_count` | int | `240` |
| `home.best_sellers[].low_stock` | int | `6` |
| `home.best_sellers[].badge` | string | `Best seller` |
| `home.top_rated` | list | `2 item(s)` |
| `home.top_rated[]` | object |  |
| `home.top_rated[].id` | int | `101` |
| `home.top_rated[].name` | string | `Cotton tee` |
| `home.top_rated[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `home.top_rated[].image` | string | `/storage/products/tee.webp` |
| `home.top_rated[].image_hover` | null |  |
| `home.top_rated[].price` | string | `$19.99` |
| `home.top_rated[].price_minor` | int | `1999` |
| `home.top_rated[].compare_price` | string | `$24.99` |
| `home.top_rated[].discount_percent` | int | `20` |
| `home.top_rated[].flash_ends_at` | null |  |
| `home.top_rated[].rating` | float | `4.6` |
| `home.top_rated[].review_count` | int | `18` |
| `home.top_rated[].star_seller` | bool | `true` |
| `home.top_rated[].in_stock` | bool | `true` |
| `home.top_rated[].has_variants` | bool | `false` |
| `home.top_rated[].favorited` | bool | `false` |
| `home.top_rated[].sold_count` | int | `240` |
| `home.top_rated[].low_stock` | int | `6` |
| `home.top_rated[].badge` | string | `Best seller` |
| `home.new_in` | list | `2 item(s)` |
| `home.new_in[]` | object |  |
| `home.new_in[].id` | int | `101` |
| `home.new_in[].name` | string | `Cotton tee` |
| `home.new_in[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `home.new_in[].image` | string | `/storage/products/tee.webp` |
| `home.new_in[].image_hover` | null |  |
| `home.new_in[].price` | string | `$19.99` |
| `home.new_in[].price_minor` | int | `1999` |
| `home.new_in[].compare_price` | string | `$24.99` |
| `home.new_in[].discount_percent` | int | `20` |
| `home.new_in[].flash_ends_at` | null |  |
| `home.new_in[].rating` | float | `4.6` |
| `home.new_in[].review_count` | int | `18` |
| `home.new_in[].star_seller` | bool | `true` |
| `home.new_in[].in_stock` | bool | `true` |
| `home.new_in[].has_variants` | bool | `false` |
| `home.new_in[].favorited` | bool | `false` |
| `home.new_in[].sold_count` | int | `240` |
| `home.new_in[].low_stock` | int | `6` |
| `home.new_in[].badge` | string | `Best seller` |
| `home.layout` | list |  |
| `home.sections` | list |  |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `home` |

### `templates/listing.twig` (pages.listing)

| Variable | Type | Example |
|---|---|---|
| `listing` | object |  |
| `listing.kind` | string | `best_selling` |
| `listing.title` | string | `Best sellers` |
| `listing.breadcrumbs` | list | `1 item(s)` |
| `listing.breadcrumbs[]` | object |  |
| `listing.breadcrumbs[].name` | string | `Home` |
| `listing.breadcrumbs[].url` | string | `https://your-store.com` |
| `listing.products` | list | `2 item(s)` |
| `listing.products[]` | object |  |
| `listing.products[].id` | int | `101` |
| `listing.products[].name` | string | `Cotton tee` |
| `listing.products[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `listing.products[].image` | string | `/storage/products/tee.webp` |
| `listing.products[].image_hover` | null |  |
| `listing.products[].price` | string | `$19.99` |
| `listing.products[].price_minor` | int | `1999` |
| `listing.products[].compare_price` | string | `$24.99` |
| `listing.products[].discount_percent` | int | `20` |
| `listing.products[].flash_ends_at` | null |  |
| `listing.products[].rating` | float | `4.6` |
| `listing.products[].review_count` | int | `18` |
| `listing.products[].star_seller` | bool | `true` |
| `listing.products[].in_stock` | bool | `true` |
| `listing.products[].has_variants` | bool | `false` |
| `listing.products[].favorited` | bool | `false` |
| `listing.products[].sold_count` | int | `240` |
| `listing.products[].low_stock` | int | `6` |
| `listing.products[].badge` | string | `Best seller` |
| `listing.total` | int | `42` |
| `listing.pagination` | object |  |
| `listing.pagination.page` | int | `1` |
| `listing.pagination.last_page` | int | `3` |
| `listing.pagination.total` | int | `42` |
| `listing.pagination.per_page` | int | `20` |
| `listing.pagination.prev_url` | null |  |
| `listing.pagination.next_url` | string | `https://your-store.com/?page=2` |
| `listing.pagination.pages` | list | `3 item(s)` |
| `listing.pagination.pages[]` | object |  |
| `listing.pagination.pages[].number` | int | `1` |
| `listing.pagination.pages[].url` | string | `https://your-store.com/?` |
| `listing.pagination.pages[].current` | bool | `true` |
| `listing.sort` | object |  |
| `listing.sort.current` | string | `best_selling` |
| `listing.sort.options` | list | `5 item(s)` |
| `listing.sort.options[]` | object |  |
| `listing.sort.options[].value` | string | `best_selling` |
| `listing.sort.options[].label` | string | `Best Selling` |
| `listing.sort.options[].url` | string | `https://your-store.com/?sort=best_selling` |
| `listing.sort.options[].current` | bool | `true` |
| `listing.filter_categories` | list | `1 item(s)` |
| `listing.filter_categories[]` | object |  |
| `listing.filter_categories[].id` | int | `1` |
| `listing.filter_categories[].name` | string | `Fashion` |
| `seo` | object |  |
| `seo.title` | string | `Best sellers` |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `listing` |

### `templates/categories.twig` (pages.categories)

| Variable | Type | Example |
|---|---|---|
| `categories` | list | `1 item(s)` |
| `categories[]` | object |  |
| `categories[].id` | int | `1` |
| `categories[].name` | string | `Fashion` |
| `categories[].slug` | string | `fashion` |
| `categories[].url` | string | `https://your-store.com/category/fashion` |
| `categories[].image` | null |  |
| `categories[].children` | list | `1 item(s)` |
| `categories[].children[]` | object |  |
| `categories[].children[].id` | int | `2` |
| `categories[].children[].name` | string | `Tees` |
| `categories[].children[].slug` | string | `tees` |
| `categories[].children[].url` | string | `https://your-store.com/category/tees` |
| `categories[].children[].image` | null |  |
| `seo` | object |  |
| `seo.title` | string | `Categories` |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `categories` |

### `templates/category.twig` (pages.listing)

| Variable | Type | Example |
|---|---|---|
| `listing` | object |  |
| `listing.kind` | string | `category` |
| `listing.title` | string | `Fashion` |
| `listing.breadcrumbs` | list | `1 item(s)` |
| `listing.breadcrumbs[]` | object |  |
| `listing.breadcrumbs[].name` | string | `Home` |
| `listing.breadcrumbs[].url` | string | `https://your-store.com` |
| `listing.products` | list | `2 item(s)` |
| `listing.products[]` | object |  |
| `listing.products[].id` | int | `101` |
| `listing.products[].name` | string | `Cotton tee` |
| `listing.products[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `listing.products[].image` | string | `/storage/products/tee.webp` |
| `listing.products[].image_hover` | null |  |
| `listing.products[].price` | string | `$19.99` |
| `listing.products[].price_minor` | int | `1999` |
| `listing.products[].compare_price` | string | `$24.99` |
| `listing.products[].discount_percent` | int | `20` |
| `listing.products[].flash_ends_at` | null |  |
| `listing.products[].rating` | float | `4.6` |
| `listing.products[].review_count` | int | `18` |
| `listing.products[].star_seller` | bool | `true` |
| `listing.products[].in_stock` | bool | `true` |
| `listing.products[].has_variants` | bool | `false` |
| `listing.products[].favorited` | bool | `false` |
| `listing.products[].sold_count` | int | `240` |
| `listing.products[].low_stock` | int | `6` |
| `listing.products[].badge` | string | `Best seller` |
| `listing.total` | int | `42` |
| `listing.pagination` | object |  |
| `listing.pagination.page` | int | `1` |
| `listing.pagination.last_page` | int | `3` |
| `listing.pagination.total` | int | `42` |
| `listing.pagination.per_page` | int | `20` |
| `listing.pagination.prev_url` | null |  |
| `listing.pagination.next_url` | string | `https://your-store.com/?page=2` |
| `listing.pagination.pages` | list | `3 item(s)` |
| `listing.pagination.pages[]` | object |  |
| `listing.pagination.pages[].number` | int | `1` |
| `listing.pagination.pages[].url` | string | `https://your-store.com/?` |
| `listing.pagination.pages[].current` | bool | `true` |
| `listing.sort` | object |  |
| `listing.sort.current` | string | `recommended` |
| `listing.sort.options` | list | `6 item(s)` |
| `listing.sort.options[]` | object |  |
| `listing.sort.options[].value` | string | `recommended` |
| `listing.sort.options[].label` | string | `Recommended` |
| `listing.sort.options[].url` | string | `https://your-store.com/?sort=recommended` |
| `listing.sort.options[].current` | bool | `true` |
| `listing.filter_categories` | list |  |
| `category` | object |  |
| `category.id` | int | `1` |
| `category.name` | string | `Fashion` |
| `category.slug` | string | `fashion` |
| `category.url` | string | `https://your-store.com/category/fashion` |
| `category.image` | null |  |
| `category.description` | string | `Clothes for every day.` |
| `subcategories` | list | `1 item(s)` |
| `subcategories[]` | object |  |
| `subcategories[].id` | int | `2` |
| `subcategories[].name` | string | `Tees` |
| `subcategories[].slug` | string | `tees` |
| `subcategories[].url` | string | `https://your-store.com/category/tees` |
| `subcategories[].image` | null |  |
| `seo` | object |  |
| `seo.title` | string | `Fashion` |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `category` |

### `templates/product.twig` (pages.product)

| Variable | Type | Example |
|---|---|---|
| `product` | object |  |
| `product.id` | int | `101` |
| `product.name` | string | `Cotton tee` |
| `product.slug` | string | `cotton-tee` |
| `product.url` | string | `https://your-store.com/products/101/cotton-tee` |
| `product.summary` | string | `Soft everyday tee.` |
| `product.description_html` | html (safe) | `<p>100% cotton, <strong>pre-shrunk</strong>.</p>` |
| `product.images` | list | `1 item(s)` |
| `product.images[]` | string | `/storage/products/tee.webp` |
| `product.media` | list | `1 item(s)` |
| `product.media[]` | object |  |
| `product.media[].type` | string | `image` |
| `product.media[].url` | string | `/storage/products/tee.webp` |
| `product.media[].poster` | null |  |
| `product.price` | string | `$19.99` |
| `product.price_minor` | int | `1999` |
| `product.compare_price` | string | `$24.99` |
| `product.discount_percent` | int | `20` |
| `product.flash_sale` | null |  |
| `product.in_stock` | bool | `true` |
| `product.stock` | int | `12` |
| `product.attributes` | list | `1 item(s)` |
| `product.attributes[]` | object |  |
| `product.attributes[].id` | int | `1` |
| `product.attributes[].name` | string | `Size` |
| `product.attributes[].swatch` | bool | `false` |
| `product.attributes[].options` | list | `2 item(s)` |
| `product.attributes[].options[]` | object |  |
| `product.attributes[].options[].id` | int | `11` |
| `product.attributes[].options[].name` | string | `M` |
| `product.attributes[].options[].swatch` | null |  |
| `product.variants` | list | `1 item(s)` |
| `product.variants[]` | object |  |
| `product.variants[].id` | int | `501` |
| `product.variants[].label` | string | `M` |
| `product.variants[].options` | list | `1 item(s)` |
| `product.variants[].options[]` | int | `11` |
| `product.variants[].price` | string | `$19.99` |
| `product.variants[].price_minor` | int | `1999` |
| `product.variants[].compare_price` | string | `$24.99` |
| `product.variants[].discount_percent` | int | `20` |
| `product.variants[].in_stock` | bool | `true` |
| `product.variants[].stock` | int | `5` |
| `product.variants[].image` | null |  |
| `product.selected_variant_id` | null |  |
| `product.breadcrumbs` | list | `1 item(s)` |
| `product.breadcrumbs[]` | object |  |
| `product.breadcrumbs[].name` | string | `Fashion` |
| `product.breadcrumbs[].url` | string | `https://your-store.com/category/fashion` |
| `product.category` | object |  |
| `product.category.id` | int | `1` |
| `product.category.name` | string | `Fashion` |
| `product.category.slug` | string | `fashion` |
| `product.category.url` | string | `https://your-store.com/category/fashion` |
| `product.category.image` | null |  |
| `product.best_selling` | bool | `true` |
| `product.sold_count` | int | `240` |
| `product.delivery` | object |  |
| `product.delivery.label` | string | `Delivery in 2–4 days` |
| `product.delivery.min_date` | string | `2026-12-01` |
| `product.delivery.max_date` | string | `2026-12-03` |
| `product.delivery.company` | string | `Aramex` |
| `product.reviews` | object |  |
| `product.reviews.average` | float | `4.6` |
| `product.reviews.count` | int | `18` |
| `product.reviews.distribution` | object |  |
| `product.reviews.distribution.5` | int | `12` |
| `product.reviews.distribution.4` | int | `4` |
| `product.reviews.distribution.3` | int | `2` |
| `product.reviews.distribution.2` | int | `0` |
| `product.reviews.distribution.1` | int | `0` |
| `product.favorited` | bool | `false` |
| `product.related` | list | `1 item(s)` |
| `product.related[]` | object |  |
| `product.related[].id` | int | `102` |
| `product.related[].name` | string | `Linen shirt` |
| `product.related[].url` | string | `https://your-store.com/products/102/linen-shirt` |
| `product.related[].image` | string | `/storage/products/shirt.webp` |
| `product.related[].image_hover` | string | `/storage/products/shirt-2.webp` |
| `product.related[].price` | string | `$39.00` |
| `product.related[].price_minor` | int | `3900` |
| `product.related[].compare_price` | null |  |
| `product.related[].discount_percent` | null |  |
| `product.related[].flash_ends_at` | null |  |
| `product.related[].rating` | float | `4.2` |
| `product.related[].review_count` | int | `7` |
| `product.related[].star_seller` | bool | `false` |
| `product.related[].in_stock` | bool | `true` |
| `product.related[].has_variants` | bool | `true` |
| `product.related[].favorited` | bool | `true` |
| `product.related[].sold_count` | int | `58` |
| `product.related[].low_stock` | null |  |
| `product.related[].badge` | null |  |
| `seo` | object |  |
| `seo.title` | string | `Cotton tee` |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `product` |

### `templates/search.twig` (pages.listing)

| Variable | Type | Example |
|---|---|---|
| `listing` | object |  |
| `listing.kind` | string | `search` |
| `listing.title` | string | `Results for “tee”` |
| `listing.breadcrumbs` | list | `1 item(s)` |
| `listing.breadcrumbs[]` | object |  |
| `listing.breadcrumbs[].name` | string | `Home` |
| `listing.breadcrumbs[].url` | string | `https://your-store.com` |
| `listing.products` | list | `2 item(s)` |
| `listing.products[]` | object |  |
| `listing.products[].id` | int | `101` |
| `listing.products[].name` | string | `Cotton tee` |
| `listing.products[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `listing.products[].image` | string | `/storage/products/tee.webp` |
| `listing.products[].image_hover` | null |  |
| `listing.products[].price` | string | `$19.99` |
| `listing.products[].price_minor` | int | `1999` |
| `listing.products[].compare_price` | string | `$24.99` |
| `listing.products[].discount_percent` | int | `20` |
| `listing.products[].flash_ends_at` | null |  |
| `listing.products[].rating` | float | `4.6` |
| `listing.products[].review_count` | int | `18` |
| `listing.products[].star_seller` | bool | `true` |
| `listing.products[].in_stock` | bool | `true` |
| `listing.products[].has_variants` | bool | `false` |
| `listing.products[].favorited` | bool | `false` |
| `listing.products[].sold_count` | int | `240` |
| `listing.products[].low_stock` | int | `6` |
| `listing.products[].badge` | string | `Best seller` |
| `listing.total` | int | `42` |
| `listing.pagination` | object |  |
| `listing.pagination.page` | int | `1` |
| `listing.pagination.last_page` | int | `3` |
| `listing.pagination.total` | int | `42` |
| `listing.pagination.per_page` | int | `20` |
| `listing.pagination.prev_url` | null |  |
| `listing.pagination.next_url` | string | `https://your-store.com/?page=2` |
| `listing.pagination.pages` | list | `3 item(s)` |
| `listing.pagination.pages[]` | object |  |
| `listing.pagination.pages[].number` | int | `1` |
| `listing.pagination.pages[].url` | string | `https://your-store.com/?` |
| `listing.pagination.pages[].current` | bool | `true` |
| `listing.sort` | object |  |
| `listing.sort.current` | string | `relevance` |
| `listing.sort.options` | list | `6 item(s)` |
| `listing.sort.options[]` | object |  |
| `listing.sort.options[].value` | string | `relevance` |
| `listing.sort.options[].label` | string | `Relevance` |
| `listing.sort.options[].url` | string | `https://your-store.com/?sort=relevance` |
| `listing.sort.options[].current` | bool | `true` |
| `listing.filter_categories` | list |  |
| `search` | object |  |
| `search.term` | string | `tee` |
| `search.popular` | list |  |
| `seo` | object |  |
| `seo.title` | string | `Results for “tee”` |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `search` |

### `templates/cart.twig` (pages.cart)

| Variable | Type | Example |
|---|---|---|
| `cart_page` | object |  |
| `cart_page.items_count` | int | `2` |
| `cart_page.lines` | list | `1 item(s)` |
| `cart_page.lines[]` | object |  |
| `cart_page.lines[].id` | int | `9` |
| `cart_page.lines[].store_product_id` | int | `101` |
| `cart_page.lines[].name` | string | `Cotton tee` |
| `cart_page.lines[].variant` | string | `M` |
| `cart_page.lines[].image` | string | `/storage/products/tee.webp` |
| `cart_page.lines[].url` | null |  |
| `cart_page.lines[].quantity` | int | `2` |
| `cart_page.lines[].max_quantity` | null |  |
| `cart_page.lines[].unit_price` | string | `$19.99` |
| `cart_page.lines[].total` | string | `$39.98` |
| `cart_page.lines[].available` | bool | `true` |
| `cart_page.lines[].issue` | null |  |
| `cart_page.totals` | object |  |
| `cart_page.totals.subtotal` | string | `$39.98` |
| `cart_page.totals.flash_discount` | string | `$0.00` |
| `cart_page.totals.coupon_discount` | string | `$0.00` |
| `cart_page.totals.shipping` | string | `$5.00` |
| `cart_page.totals.grand_total` | string | `$44.98` |
| `cart_page.coupon` | null |  |
| `cart_page.shipping` | object |  |
| `cart_page.shipping.available` | bool | `true` |
| `cart_page.shipping.free` | bool | `false` |
| `cart_page.shipping.message` | null |  |
| `cart_page.can_checkout` | bool | `true` |
| `cart_page.checkout_url` | string | `https://your-store.com/checkout` |
| `cart_page.recommended` | list | `2 item(s)` |
| `cart_page.recommended[]` | object |  |
| `cart_page.recommended[].id` | int | `101` |
| `cart_page.recommended[].name` | string | `Cotton tee` |
| `cart_page.recommended[].url` | string | `https://your-store.com/products/101/cotton-tee` |
| `cart_page.recommended[].image` | string | `/storage/products/tee.webp` |
| `cart_page.recommended[].image_hover` | null |  |
| `cart_page.recommended[].price` | string | `$19.99` |
| `cart_page.recommended[].price_minor` | int | `1999` |
| `cart_page.recommended[].compare_price` | string | `$24.99` |
| `cart_page.recommended[].discount_percent` | int | `20` |
| `cart_page.recommended[].flash_ends_at` | null |  |
| `cart_page.recommended[].rating` | float | `4.6` |
| `cart_page.recommended[].review_count` | int | `18` |
| `cart_page.recommended[].star_seller` | bool | `true` |
| `cart_page.recommended[].in_stock` | bool | `true` |
| `cart_page.recommended[].has_variants` | bool | `false` |
| `cart_page.recommended[].favorited` | bool | `false` |
| `cart_page.recommended[].sold_count` | int | `240` |
| `cart_page.recommended[].low_stock` | int | `6` |
| `cart_page.recommended[].badge` | string | `Best seller` |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `cart` |

### `templates/account.twig` (pages.orders.index, pages.favorites.index)

| Variable | Type | Example |
|---|---|---|
| `account` | object |  |
| `account.section` | string | `orders` |
| `account.search` | null |  |
| `account.orders` | list | `1 item(s)` |
| `account.orders[]` | object |  |
| `account.orders[].number` | string | `ORD-1001` |
| `account.orders[].status` | string | `shipped` |
| `account.orders[].status_label` | string | `Shipped` |
| `account.orders[].tone` | string | `info` |
| `account.orders[].placed_at` | string | `2026-11-20T10:00:00+00:00` |
| `account.orders[].items_count` | int | `2` |
| `account.orders[].total` | string | `$44.98` |
| `account.orders[].items` | list | `1 item(s)` |
| `account.orders[].items[]` | object |  |
| `account.orders[].items[].name` | string | `Cotton tee` |
| `account.orders[].items[].variant` | string | `M` |
| `account.orders[].items[].image` | string | `/storage/products/tee.webp` |
| `account.orders[].items[].quantity` | int | `2` |
| `account.orders[].items[].total` | string | `$39.98` |
| `account.orders[].url` | string | `https://your-store.com/orders/9f1c2d3e-4b5a-4c6d-8e7f-00112…` |
| `account.orders[].tracking_url` | string | `https://your-store.com/orders/9f1c2d3e-4b5a-4c6d-8e7f-00112…` |
| `account.order_tab` | string | `all` |
| `account.order_tabs` | list | `7 item(s)` |
| `account.order_tabs[]` | object |  |
| `account.order_tabs[].key` | string | `all` |
| `account.order_tabs[].url` | string | `https://your-store.com/account/orders` |
| `account.pagination` | object |  |
| `account.pagination.page` | int | `1` |
| `account.pagination.last_page` | int | `1` |
| `account.pagination.total` | int | `1` |
| `account.pagination.per_page` | int | `10` |
| `account.pagination.prev_url` | null |  |
| `account.pagination.next_url` | null |  |
| `account.pagination.pages` | list | `1 item(s)` |
| `account.pagination.pages[]` | object |  |
| `account.pagination.pages[].number` | int | `1` |
| `account.pagination.pages[].url` | string | `https://your-store.com/account/orders?page=1` |
| `account.pagination.pages[].current` | bool | `true` |
| `account.wishlist` | list |  |
| `account.wishlist_total` | int | `0` |
| `account.links` | object |  |
| `account.links.orders` | string | `https://your-store.com/account/orders` |
| `account.links.wishlist` | string | `https://your-store.com/account/wishlist` |
| `account.links.returns` | string | `https://your-store.com/account/returns` |
| `account.links.profile` | string | `https://your-store.com/account/profile` |
| `account.links.addresses` | string | `https://your-store.com/account/addresses` |
| `account.links.support` | string | `https://your-store.com/account/support` |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `account` |

### `templates/page.twig` (pages.page)

| Variable | Type | Example |
|---|---|---|
| `cms_page` | object |  |
| `cms_page.title` | string | `About us` |
| `cms_page.slug` | string | `about` |
| `cms_page.body_html` | html (safe) | `<p>We are a small team that loves good products.</p>` |
| `cms_page.updated_at` | string | `2026-11-01T00:00:00+00:00` |
| `cms_page.is_contact` | bool | `false` |
| `cms_page.support_url` | string | `https://your-store.com/account/support` |
| `cms_page.menu` | list | `1 item(s)` |
| `cms_page.menu[]` | object |  |
| `cms_page.menu[].title` | string | `About us` |
| `cms_page.menu[].url` | string | `https://your-store.com/pages/about` |
| `cms_page.menu[].current` | bool | `true` |
| `cms_page.sections` | list |  |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `page` |

### `templates/404.twig` (pages.errors.404)

| Variable | Type | Example |
|---|---|---|
| `error` | object |  |
| `error.code` | int | `404` |
| `error.title` | string | `This page doesn’t exist` |
| `error.message` | string | `The link may be broken or the page may have moved. Try sear…` |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `404` |

### `templates/500.twig` (pages.errors.500)

| Variable | Type | Example |
|---|---|---|
| `error` | object |  |
| `error.code` | int | `500` |
| `error.title` | string | `Something went wrong` |
| `error.message` | string | `We are fixing it. Please try again in a moment.` |
| `seo` | object |  |
| `seo.title` | string |  |
| `seo.description` | null |  |
| `seo.canonical` | null |  |
| `template` | string | `500` |

<!-- variables:end -->

## FAQ

**My JavaScript does not run.** Inline `<script>` blocks and `onclick="…"` are blocked by the CSP. Move the code to `assets/js/theme.js` and load it with `{{ script('js/theme.js') }}` before `</body>`.

**Can I change the checkout page?** No. Checkout, payment and sign-in always use the platform's secure pages with the store's built-in theme.

**How do I show prices in another currency?** You don't need to: `product.price`, `cart_page.totals.*` etc. are already formatted in the shopper's display currency. For minor-unit amounts use `money(amount)`.

**A text shows its key (`cart.title`).** The key is missing in `locales/en.json`. Add it (and the Arabic text) and upload a new version; the validation report lists every missing key.

**How do I add a setting?** Add an entry to `settings` in `theme.json` (id, type, en/ar label, default) and read it with `setting('id')`. The field appears in the settings form once the new version is uploaded.

**Can I use my own font?** Yes: put the `woff2` file in `assets/fonts/`, declare it with `@font-face` using a relative `url()`, and use it in `font-family`. Or add a `font` setting and pick a platform font.

**The upload says "path traversal" or "hidden file".** The ZIP contains hidden files (`.git`, `.env`) or paths with `..`. Create the ZIP from inside the theme folder without hidden files.
