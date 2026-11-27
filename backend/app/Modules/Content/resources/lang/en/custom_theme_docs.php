<?php

/*
 * Vendor panel › Appearance › Custom theme › Developer documentation (VEN-036, decision #121).
 * The variable tables and the limits table are rendered below these sections from
 * ThemeVariableReference and CustomThemeLimits::documented().
 */
return [
    'title' => 'Custom theme developer guide',
    'intro' => 'Build your own storefront design with Twig templates, plain CSS and JavaScript. Your theme controls how the home, listing, product, cart, account and content pages look; the platform keeps running the catalogue, prices, cart, checkout and payments. Start from the Starter theme, change it, upload it as a ZIP, preview it and submit it for review.',

    'sections' => [
        'getting_started' => [
            'title' => 'Getting started',
            'body' => [
                'Download the Starter theme from this page. It is a complete, working theme that already passes validation, supports Arabic and English and works on phones and desktops.',
                'Edit the files with any text editor. You do not need a build tool: templates are Twig files, styles are plain CSS and scripts are plain JavaScript.',
                'When you are ready, put the theme folder in a ZIP file and upload it. The upload report lists every problem with its file and line so you can fix it and upload again.',
            ],
            'steps' => [
                'Download the Starter theme ZIP and unpack it.',
                'Change the name and version in theme.json.',
                'Edit templates/*.twig, assets/css/theme.css and the texts in locales/en.json and locales/ar.json.',
                'ZIP the folder (theme.json must be at the top of the ZIP or inside one top folder) and upload it.',
                'Open the preview link, check every page in both languages, then submit the version for review.',
            ],
        ],

        'package_structure' => [
            'title' => 'Package structure',
            'body' => [
                'A theme package is a ZIP file with this layout. Only these folders and files are accepted; anything else is rejected by the validator.',
                'theme.json (required) describes the theme and its settings. templates/ holds the Twig templates; sub-folders such as templates/partials/ are allowed. locales/ holds exactly en.json and ar.json. assets/ holds CSS, JavaScript, images (png, jpg, webp, gif, avif, svg, ico) and fonts (woff, woff2, ttf, otf).',
                'Optional root files: README.md, LICENSE, LICENSE.md, LICENSE.txt, CHANGELOG.md and screenshot.png / .jpg / .webp. They are stored but never served.',
                'File names may only use letters, digits, ".", "_", "-" and "/". Hidden files (names starting with "."), symbolic links, absolute paths, ".." and backslashes are not allowed. Server files such as .php, .htaccess or .sh are always rejected.',
            ],
            'code' => "my-theme/\n├── theme.json\n├── templates/\n│   ├── layout.twig\n│   ├── home.twig  listing.twig  categories.twig  category.twig\n│   ├── product.twig  search.twig  cart.twig  account.twig\n│   ├── page.twig  404.twig  500.twig\n│   └── partials/\n│       ├── header.twig  footer.twig  product-card.twig\n│       └── macros.twig\n├── locales/\n│   ├── en.json\n│   └── ar.json\n└── assets/\n    ├── css/theme.css\n    ├── js/theme.js\n    └── img/placeholder.svg",
        ],

        'theme_json' => [
            'title' => 'theme.json',
            'body' => [
                'name (1–60 characters), version (semantic version such as 1.0.0, raise it for every upload), engine (always "nogrgr-twig/1") and author.name are required. author.email, author.url (https only) and description (up to 500 characters) are optional.',
                'supports must list every page template: home, listing, categories, category, product, search, cart, account, page, 404 and 500.',
                'rtl tells the platform the theme supports right-to-left pages. Keep it true: Arabic shoppers get dir="rtl" on every page.',
                'settings is the list of options the store owner can change without editing files (see the next section).',
            ],
            'code' => "{\n    \"name\": \"Starter\",\n    \"version\": \"1.0.0\",\n    \"engine\": \"nogrgr-twig/1\",\n    \"author\": {\"name\": \"Nogrgr\"},\n    \"description\": \"A clean, responsive starter theme.\",\n    \"supports\": [\"home\", \"listing\", \"categories\", \"category\", \"product\", \"search\", \"cart\", \"account\", \"page\", \"404\", \"500\"],\n    \"rtl\": true,\n    \"settings\": []\n}",
        ],

        'settings_schema' => [
            'title' => 'Theme settings',
            'body' => [
                'Every entry in settings becomes a field in the theme settings form of the vendor panel. Each setting needs an id (lower_snake_case, up to 40 characters), a type, a label in English and Arabic and a default value. help (English and Arabic) is optional.',
                'Types: color (#RRGGBB), text (max_length up to 500), textarea (max_length up to 2000), number (min and max), select (options with value and en / ar label), toggle (true / false) and font (one of: outfit, inter, manrope, noto-sans-arabic, ibm-plex-sans-arabic, tajawal).',
                'Read a value in any template with setting(\'id\'). Font settings return a ready CSS font-family value and the platform loads the chosen font file for you.',
                'Invalid saved values fall back to the default, so templates can always rely on a value of the right type.',
            ],
            'code' => "{\"id\": \"accent\", \"type\": \"color\", \"label\": {\"en\": \"Accent colour\", \"ar\": \"اللون المميز\"}, \"default\": \"#0F766E\"}\n{\"id\": \"products_per_row\", \"type\": \"select\", \"label\": {\"en\": \"Products per row\", \"ar\": \"عدد المنتجات في الصف\"},\n \"options\": [{\"value\": \"3\", \"label\": {\"en\": \"3\", \"ar\": \"3\"}}, {\"value\": \"4\", \"label\": {\"en\": \"4\", \"ar\": \"4\"}}], \"default\": \"4\"}\n\n<html style=\"--accent: {{ setting('accent') }};\">",
        ],

        'templates' => [
            'title' => 'Templates',
            'body' => [
                'layout.twig is the shared shell. It must contain <html lang="{{ store.locale }}" dir="{{ store.dir }}">, {{ head() }} inside <head> and {{ scripts() }} before </body>. Every page template starts with {% extends "layout.twig" %} and fills {% block content %}.',
                'home.twig: banners, categories and product rows (home.*). listing.twig: best sellers, new in, top rated and offers listings (listing.*). category.twig: one category with its products and sub-categories (listing.*, category, subcategories). search.twig: search results (listing.*, search.term, search.popular). categories.twig: all categories (categories).',
                'product.twig: the product page (product.*, including variants, media, reviews and related products). cart.twig: the cart (cart_page.*). account.twig: the customer\'s orders list and wishlist (account.section is "orders" or "wishlist"). page.twig: content pages such as About or Privacy (cms_page.*). 404.twig and 500.twig: error pages (error.*).',
                'Every template also receives the globals store, menus, customer, cart, request, theme, settings, seo and template. Templates get plain data only: lists and objects you read with dots (product.name), never PHP objects or methods.',
                'Checkout, payment, sign-in, registration, password reset, order details and tracking, returns, profile and addresses always use the platform\'s built-in pages. Link to them with url(\'checkout\'), url(\'login\') and so on.',
                'The full list of variables of every template, with types and example values, is in the tables below.',
            ],
            'code' => "{% extends \"layout.twig\" %}\n{% import \"partials/macros.twig\" as ui %}\n\n{% block content %}\n    <h1>{{ listing.title }}</h1>\n    {% for item in listing.products %}\n        {% include \"partials/product-card.twig\" with {card: item} only %}\n    {% else %}\n        <p>{{ t('listing.empty_title') }}</p>\n    {% endfor %}\n{% endblock %}",
        ],

        'functions_filters' => [
            'title' => 'Functions and filters',
            'body' => [
                'url(name, params) returns a store link. Names: home, search, categories, category, product, page, best_sellers, new_in, top_rated, offers, cart, checkout, account, orders, wishlist, returns, profile, addresses, support, login, register, logout, preferences, newsletter. product needs {id, slug}, category and page need {slug}, search takes {q}. Unknown names return "#" and are reported as warnings.',
                'asset(path) returns the URL of a file in assets/. script(path) prints a <script> tag for a .js file in assets/ (the only way to load JavaScript).',
                'money(minor) and the money filter format an amount in minor units (cents) in the shopper\'s display currency. Most prices are already formatted for you (product.price).',
                't(key, params) and the t filter read your theme texts from locales/*.json; st(key, params) reads the platform\'s own storefront texts.',
                'setting(id) reads a theme setting. head() prints the SEO tags, security policy, CSRF token and store data; scripts() prints the platform runtime. csrf_field() prints the hidden token field for POST forms and csrf_token() returns the token itself.',
                'Allowed filters: abs, batch, capitalize, column, date, default, e / escape, filter, first, join, json_encode, keys, last, length, lower, map, merge, nl2br, number_format, replace, reverse, round, slice, sort, split, striptags, title, trim, upper, url_encode, money, t, json (safe JSON for data islands). Allowed core functions: block, parent, include, cycle, max, min, date.',
            ],
            'code' => "<a href=\"{{ url('product', {id: product.id, slug: product.slug}) }}\">{{ product.name }}</a>\n<a href=\"{{ url('search', {q: 'shoes'}) }}\">{{ t('search.title') }}</a>\n<link rel=\"stylesheet\" href=\"{{ asset('css/theme.css') }}\">\n{{ script('js/theme.js') }}\n{{ money(1999) }}  {{ 1999|money }}\n{{ t('cart.items', {count: cart.count}) }}  {{ 'cart.title'|t }}\n{{ st('cart.title') }}\n{% if setting('show_hero') %}…{% endif %}\n<form method=\"post\" action=\"{{ url('logout') }}\">{{ csrf_field() }}<button>{{ t('account.sign_out') }}</button></form>",
        ],

        'assets_csp' => [
            'title' => 'Assets and the content security policy',
            'body' => [
                'Store pages run with a strict content security policy. Inline <script> blocks, event attributes such as onclick="…" and javascript: links are blocked by the browser. Put your JavaScript in assets/js/ and load it with {{ script(\'js/theme.js\') }}.',
                'JSON data islands (<script type="application/json">) are allowed for passing data to your scripts.',
                'Remote files are blocked: bundle fonts, images and styles inside assets/ and use relative url() paths in CSS. CSS may not use @import of remote files, expression() or behaviour bindings.',
                'Inline styles are allowed, which is the easiest way to turn settings into CSS custom properties: style="--accent: {{ setting(\'accent\') }}" on <html>.',
                'SVG files may not contain scripts, event attributes, links (href / src), <use>, <foreignObject> or DOCTYPE / ENTITY declarations.',
                'Use object-fit: contain for product images so products are never cropped.',
            ],
            'code' => "/* assets/css/theme.css */\n.card__img { object-fit: contain; }\n.badge { inset-inline-start: 10px; } /* logical properties work in RTL and LTR */\n@font-face { font-family: \"Brand\"; src: url(\"../fonts/brand.woff2\") format(\"woff2\"); }",
        ],

        'localization' => [
            'title' => 'Languages and right-to-left',
            'body' => [
                'Every text of your theme goes through t(). Put the English texts in locales/en.json and the Arabic texts in locales/ar.json. Keys may be nested objects and are read with dots: t(\'cart.title\'). If a key is missing in Arabic the English text is used; if it is missing in both, the key itself is shown and the validator warns you.',
                'Placeholders start with a colon: "Hi, :name" with t(\'header.hello\', {name: customer.name}).',
                'Store content (product names, categories, banners, pages) is already in the shopper\'s language.',
                'store.locale is "en" or "ar" and store.dir is "ltr" or "rtl". Set both on <html> and use CSS logical properties (margin-inline-start, padding-inline-end, inset-inline-start, text-align: start) so one stylesheet works in both directions. Use [dir="rtl"] selectors only for the few things that must be mirrored, such as arrow icons.',
            ],
            'code' => "{\n    \"header\": {\"hello\": \"Hi, :name\", \"cart\": \"Cart\"}\n}\n\n{\n    \"header\": {\"hello\": \"مرحبًا، :name\", \"cart\": \"السلة\"}\n}",
        ],

        'runtime' => [
            'title' => 'Runtime: cart, wishlist and coupons',
            'body' => [
                'You do not write store logic. {{ scripts() }} loads the platform runtime, which connects elements that carry data-nt-* attributes to the store.',
                'Add to cart: a button with data-nt-add-to-cart and data-product-id (optional data-variant-id and data-quantity). Inside a form with data-nt-product-form, the runtime reads the fields named variant_id and quantity instead.',
                'Cart counter: every element with data-nt-cart-count is updated after a cart change. Cart page: an input with data-nt-cart-qty and data-line-id changes the quantity, a button with data-nt-cart-remove and data-line-id removes the line, a form with data-nt-coupon-form (field name="code") applies a coupon and a button with data-nt-coupon-remove removes it. The page reloads with the new totals after each change.',
                'Wishlist: a button with data-nt-favorite and data-product-id toggles the product and sets aria-pressed. Guests are sent to the sign-in page.',
                'Variants: a select with data-nt-variant-select inside the product form updates the elements data-nt-price and data-nt-compare from a <script type="application/json" data-nt-variants> island that holds product.variants.',
                'Newsletter: a form with data-nt-newsletter-form (field name="email") posting to url(\'newsletter\') subscribes the shopper without leaving the page.',
                'Status messages: if the page has an element with data-nt-status (use role="status" aria-live="polite"), the runtime writes success and error messages into it. Optional data-msg-added, data-msg-error, data-msg-favorited, data-msg-unfavorited and data-msg-subscribed attributes provide your own texts when the server sends none.',
            ],
            'code' => "<button type=\"button\" data-nt-add-to-cart data-product-id=\"{{ card.id }}\" data-quantity=\"1\">{{ t('product.add_to_cart') }}</button>\n<span data-nt-cart-count>{{ cart.count }}</span>\n\n<form data-nt-product-form>\n    <script type=\"application/json\" data-nt-variants>{{ product.variants|json }}</script>\n    <select name=\"variant_id\" data-nt-variant-select>…</select>\n    <input type=\"number\" name=\"quantity\" value=\"1\" min=\"1\">\n    <button type=\"submit\" data-nt-add-to-cart data-product-id=\"{{ product.id }}\">{{ t('product.add_to_cart') }}</button>\n</form>\n<span data-nt-price>{{ product.price }}</span> <s data-nt-compare>{{ product.compare_price }}</s>\n\n<input type=\"number\" value=\"{{ line.quantity }}\" data-nt-cart-qty data-line-id=\"{{ line.id }}\">\n<button type=\"button\" data-nt-cart-remove data-line-id=\"{{ line.id }}\">{{ t('cart.remove') }}</button>\n<button type=\"button\" data-nt-favorite data-product-id=\"{{ card.id }}\" aria-pressed=\"false\">♥</button>\n<div data-nt-status role=\"status\" aria-live=\"polite\"></div>",
        ],

        'testing_preview' => [
            'title' => 'Upload, preview and publishing',
            'body' => [
                'Every upload creates a new version of your theme. Your live store does not change until a version is approved and published.',
                'The validation report lists errors (the version cannot be used until you fix them) and warnings (for example a missing text, a missing asset or a page without {{ head() }}). Fix warnings too: they show up as broken texts or images for shoppers.',
                'You can roll back to any earlier published version at any time; the store switches back immediately.',
            ],
            'steps' => [
                'Upload the ZIP. The platform checks the files, compiles every template and renders every page with sample data.',
                'Read the validation report. If it has errors, fix them and upload a new version.',
                'Open the preview link. Only you see the preview; shoppers keep seeing the current design. Check every page in Arabic and English and on a phone.',
                'Submit the version for review.',
                'The platform team reviews the theme and approves it or sends it back with notes.',
                'Publish the approved version. Shoppers see it on their next page load.',
                'If something is wrong, roll back to the previous version.',
            ],
        ],

        'limits' => [
            'title' => 'Limits',
            'body' => [
                'Packages and page renders have size and time limits so every store stays fast. The table below lists the current values: ZIP and unpacked size, number of files, size of one asset, one template and all templates, JSON size, render time, loop steps, nesting depth, range size, number of settings and versions kept.',
                'A page that goes over the render time or loop budget stops rendering and shows the error page, so keep loops over store data simple and avoid nested loops over large lists.',
            ],
        ],

        'security' => [
            'title' => 'Security rules',
            'body' => [
                'Templates run in a sandbox. No PHP can run: PHP tags are rejected and templates receive plain data only, never objects or methods.',
                'Only the allowed tags, filters, functions and tests listed in this guide work; anything else stops the upload with an error. The tags autoescape, sandbox, do, flush, deprecated, use, guard, types and cache, the raw filter and the functions attribute, constant, source, range, random, dump and template_from_string are not available.',
                'All output is HTML-escaped automatically. product.description_html and cms_page.body_html are already cleaned by the platform and are printed as HTML with {{ }}.',
                'Checkout, payment, sign-in, registration, password and customer account pages (order details, tracking, returns, profile, addresses) always use the platform\'s own pages so payments and personal data stay protected.',
                'No remote files and no inline scripts: everything your theme needs must be inside the package.',
            ],
        ],
    ],

    'faq' => [
        [
            'q' => 'Why does my JavaScript not run?',
            'a' => 'Inline <script> blocks and onclick="…" attributes are blocked by the store\'s security policy. Move the code to assets/js/theme.js and load it with {{ script(\'js/theme.js\') }} before </body>.',
        ],
        [
            'q' => 'Can I change the checkout page?',
            'a' => 'No. Checkout, payment and sign-in always use the platform\'s secure pages. They are shown with your store\'s built-in theme.',
        ],
        [
            'q' => 'How do I show a price in another currency?',
            'a' => 'You do not need to. Prices such as product.price are already formatted in the currency the shopper picked. For amounts in minor units use money(amount).',
        ],
        [
            'q' => 'A text shows its key (for example "cart.title") instead of a sentence.',
            'a' => 'The key is missing in locales/en.json. Add it (and its Arabic translation in locales/ar.json) and upload a new version. The validation report lists every missing key.',
        ],
        [
            'q' => 'How do I add a new setting?',
            'a' => 'Add an entry to settings in theme.json with an id, type, English and Arabic label and a default, then read it with setting(\'id\'). After you upload the new version the field appears in the theme settings form.',
        ],
        [
            'q' => 'Can I use my own font?',
            'a' => 'Yes. Put the woff2 file in assets/fonts/, declare it with @font-face in your CSS using a relative url(), and use it in font-family. Or use a font setting to pick one of the platform fonts.',
        ],
        [
            'q' => 'My upload is rejected with "path traversal" or "hidden file".',
            'a' => 'Your ZIP contains hidden files such as .git or .env, or paths with "..". Create the ZIP from inside the theme folder and leave hidden files and folders out.',
        ],
    ],
];
