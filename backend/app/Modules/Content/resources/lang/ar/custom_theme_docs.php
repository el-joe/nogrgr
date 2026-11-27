<?php

/*
 * لوحة التاجر › المظهر › القالب المخصص › دليل المطوّر (VEN-036، القرار رقم 117).
 * جداول المتغيرات وجدول الحدود تُعرض أسفل هذه الأقسام من ThemeVariableReference و CustomThemeLimits::documented().
 */
return [
    'title' => 'دليل المطوّر للقوالب المخصصة',
    'intro' => 'صمّم واجهة متجرك الخاصة باستخدام قوالب Twig مع CSS و JavaScript عادية. يتحكم قالبك في شكل الصفحة الرئيسية وصفحات القوائم والمنتج والسلة والحساب وصفحات المحتوى، بينما تواصل المنصة إدارة الكتالوج والأسعار والسلة وإتمام الطلب والمدفوعات. ابدأ من القالب الأساسي، وعدّله، وارفعه كملف ZIP، ثم عاينه وأرسله للمراجعة.',

    'sections' => [
        'getting_started' => [
            'title' => 'البداية',
            'body' => [
                'حمّل القالب الأساسي من هذه الصفحة. إنه قالب كامل وجاهز يجتاز التحقق، ويدعم العربية والإنجليزية، ويعمل على الهواتف وأجهزة الكمبيوتر.',
                'عدّل الملفات بأي محرر نصوص، ولا تحتاج إلى أي أداة بناء: القوالب ملفات Twig، والأنماط CSS عادية، والسكربتات JavaScript عادية.',
                'عندما تنتهي، ضع مجلد القالب في ملف ZIP وارفعه. يعرض تقرير الرفع كل مشكلة مع اسم الملف ورقم السطر لتصلحها وترفع مرة أخرى.',
            ],
            'steps' => [
                'حمّل ملف ZIP للقالب الأساسي وفك ضغطه.',
                'غيّر الاسم والإصدار في theme.json.',
                'عدّل ملفات templates/*.twig و assets/css/theme.css والنصوص في locales/en.json و locales/ar.json.',
                'اضغط المجلد في ملف ZIP (يجب أن يكون theme.json في أعلى الملف أو داخل مجلد رئيسي واحد) ثم ارفعه.',
                'افتح رابط المعاينة، وراجع كل الصفحات باللغتين، ثم أرسل الإصدار للمراجعة.',
            ],
        ],

        'package_structure' => [
            'title' => 'بنية الحزمة',
            'body' => [
                'حزمة القالب ملف ZIP بالبنية التالية. لا تُقبل إلا هذه المجلدات والملفات، وأي ملف آخر يرفضه المدقق.',
                'theme.json (إلزامي) يصف القالب وإعداداته. يحتوي templates/ على قوالب Twig ويمكن استخدام مجلدات فرعية مثل templates/partials/. يحتوي locales/ على الملفين en.json و ar.json فقط. ويحتوي assets/ على ملفات CSS و JavaScript والصور (png و jpg و webp و gif و avif و svg و ico) والخطوط (woff و woff2 و ttf و otf).',
                'ملفات اختيارية في الجذر: README.md و LICENSE و LICENSE.md و LICENSE.txt و CHANGELOG.md و screenshot.png أو ‎.jpg أو ‎.webp. تُحفظ هذه الملفات لكنها لا تُعرض للزوار أبدًا.',
                'يجب أن تتكون أسماء الملفات من الحروف والأرقام و "." و "_" و "-" و "/" فقط. لا يُسمح بالملفات المخفية (التي تبدأ بنقطة) ولا بالروابط الرمزية ولا بالمسارات المطلقة ولا بـ ".." ولا بالشرطة المائلة العكسية. وتُرفض دائمًا ملفات الخادم مثل ‎.php و ‎.htaccess و ‎.sh.',
            ],
            'code' => "my-theme/\n├── theme.json\n├── templates/\n│   ├── layout.twig\n│   ├── home.twig  listing.twig  categories.twig  category.twig\n│   ├── product.twig  search.twig  cart.twig  account.twig\n│   ├── page.twig  404.twig  500.twig\n│   └── partials/\n│       ├── header.twig  footer.twig  product-card.twig\n│       └── macros.twig\n├── locales/\n│   ├── en.json\n│   └── ar.json\n└── assets/\n    ├── css/theme.css\n    ├── js/theme.js\n    └── img/placeholder.svg",
        ],

        'theme_json' => [
            'title' => 'ملف theme.json',
            'body' => [
                'الحقول الإلزامية: name (من 1 إلى 60 حرفًا)، و version (إصدار دلالي مثل 1.0.0، ارفعه مع كل رفع جديد)، و engine (دائمًا "nogrgr-twig/1")، و author.name. والحقول الاختيارية: author.email، و author.url (روابط https فقط)، و description (حتى 500 حرف).',
                'يجب أن يذكر الحقل supports كل قوالب الصفحات: home و listing و categories و category و product و search و cart و account و page و 404 و 500.',
                'يخبر الحقل rtl المنصة بأن القالب يدعم الصفحات من اليمين إلى اليسار. اتركه true ليحصل المتسوقون العرب على dir="rtl" في كل الصفحات.',
                'الحقل settings هو قائمة الخيارات التي يستطيع صاحب المتجر تغييرها دون تعديل الملفات (راجع القسم التالي).',
            ],
            'code' => "{\n    \"name\": \"Starter\",\n    \"version\": \"1.0.0\",\n    \"engine\": \"nogrgr-twig/1\",\n    \"author\": {\"name\": \"Nogrgr\"},\n    \"description\": \"A clean, responsive starter theme.\",\n    \"supports\": [\"home\", \"listing\", \"categories\", \"category\", \"product\", \"search\", \"cart\", \"account\", \"page\", \"404\", \"500\"],\n    \"rtl\": true,\n    \"settings\": []\n}",
        ],

        'settings_schema' => [
            'title' => 'إعدادات القالب',
            'body' => [
                'كل عنصر في settings يصبح حقلًا في نموذج إعدادات القالب في لوحة التاجر. يحتاج كل إعداد إلى id (بحروف صغيرة وشرطة سفلية، حتى 40 حرفًا)، و type، و label بالإنجليزية والعربية، وقيمة default. أما help (بالإنجليزية والعربية) فاختياري.',
                'الأنواع: color ‏(#RRGGBB)، و text ‏(max_length حتى 500)، و textarea ‏(max_length حتى 2000)، و number ‏(min و max)، و select ‏(خيارات لكل منها value و label بالإنجليزية والعربية)، و toggle ‏(true أو false)، و font ‏(أحد الخطوط: outfit و inter و manrope و noto-sans-arabic و ibm-plex-sans-arabic و tajawal).',
                'اقرأ القيمة في أي قالب باستخدام setting(\'id\'). تُرجع إعدادات الخطوط قيمة font-family جاهزة لـ CSS، وتحمّل المنصة ملف الخط المختار تلقائيًا.',
                'إذا كانت القيمة المحفوظة غير صالحة تُستخدم القيمة الافتراضية، لذلك يمكن للقوالب الاعتماد دائمًا على قيمة من النوع الصحيح.',
            ],
            'code' => "{\"id\": \"accent\", \"type\": \"color\", \"label\": {\"en\": \"Accent colour\", \"ar\": \"اللون المميز\"}, \"default\": \"#0F766E\"}\n{\"id\": \"products_per_row\", \"type\": \"select\", \"label\": {\"en\": \"Products per row\", \"ar\": \"عدد المنتجات في الصف\"},\n \"options\": [{\"value\": \"3\", \"label\": {\"en\": \"3\", \"ar\": \"3\"}}, {\"value\": \"4\", \"label\": {\"en\": \"4\", \"ar\": \"4\"}}], \"default\": \"4\"}\n\n<html style=\"--accent: {{ setting('accent') }};\">",
        ],

        'templates' => [
            'title' => 'القوالب',
            'body' => [
                'layout.twig هو الإطار المشترك لكل الصفحات، ويجب أن يحتوي على <html lang="{{ store.locale }}" dir="{{ store.dir }}">، و {{ head() }} داخل <head>، و {{ scripts() }} قبل </body>. يبدأ كل قالب صفحة بـ {% extends "layout.twig" %} ويملأ {% block content %}.',
                'home.twig: البانرات والأقسام وصفوف المنتجات (home.*). listing.twig: قوائم الأكثر مبيعًا ووصل حديثًا والأعلى تقييمًا والعروض (listing.*). category.twig: قسم واحد مع منتجاته وأقسامه الفرعية (listing.* و category و subcategories). search.twig: نتائج البحث (listing.* و search.term و search.popular). categories.twig: كل الأقسام (categories).',
                'product.twig: صفحة المنتج (product.* بما في ذلك الخيارات والوسائط والتقييمات والمنتجات المشابهة). cart.twig: السلة (cart_page.*). account.twig: قائمة طلبات العميل ومفضلته (قيمة account.section هي "orders" أو "wishlist"). page.twig: صفحات المحتوى مثل "من نحن" أو "سياسة الخصوصية" (cms_page.*). 404.twig و 500.twig: صفحات الأخطاء (error.*).',
                'يستلم كل قالب أيضًا المتغيرات العامة store و menus و customer و cart و request و theme و settings و seo و template. تحصل القوالب على بيانات بسيطة فقط: قوائم وكائنات تقرؤها بالنقطة (product.name)، ولا تحصل أبدًا على كائنات PHP أو دوالّها.',
                'صفحات إتمام الطلب والدفع وتسجيل الدخول وإنشاء الحساب واستعادة كلمة المرور وتفاصيل الطلب وتتبعه والمرتجعات والملف الشخصي والعناوين تستخدم دائمًا صفحات المنصة المدمجة. اربطها باستخدام url(\'checkout\') و url(\'login\') وغيرها.',
                'تجد القائمة الكاملة لمتغيرات كل قالب مع أنواعها وأمثلة لقيمها في الجداول أدناه.',
            ],
            'code' => "{% extends \"layout.twig\" %}\n{% import \"partials/macros.twig\" as ui %}\n\n{% block content %}\n    <h1>{{ listing.title }}</h1>\n    {% for item in listing.products %}\n        {% include \"partials/product-card.twig\" with {card: item} only %}\n    {% else %}\n        <p>{{ t('listing.empty_title') }}</p>\n    {% endfor %}\n{% endblock %}",
        ],

        'functions_filters' => [
            'title' => 'الدوال والمرشّحات',
            'body' => [
                'تُرجع url(name, params) رابطًا داخل المتجر. الأسماء المتاحة: home و search و categories و category و product و page و best_sellers و new_in و top_rated و offers و cart و checkout و account و orders و wishlist و returns و profile و addresses و support و login و register و logout و preferences و newsletter. يحتاج product إلى {id, slug}، ويحتاج category و page إلى {slug}، ويقبل search المعامل {q}. الأسماء غير المعروفة تُرجع "#" وتظهر كتحذيرات.',
                'تُرجع asset(path) رابط ملف داخل assets/. وتطبع script(path) وسم <script> لملف ‎.js داخل assets/ (وهي الطريقة الوحيدة لتحميل JavaScript).',
                'تنسّق money(minor) والمرشّح money مبلغًا بالوحدات الصغرى (السنتات) بعملة العرض التي اختارها المتسوق. معظم الأسعار تصلك منسّقة مسبقًا (product.price).',
                'تقرأ t(key, params) والمرشّح t نصوص قالبك من locales/*.json، بينما تقرأ st(key, params) نصوص المتجر الخاصة بالمنصة.',
                'تقرأ setting(id) أحد إعدادات القالب. تطبع head() وسوم تحسين محركات البحث وسياسة الأمان ورمز CSRF وبيانات المتجر، وتطبع scripts() سكربت المنصة. تطبع csrf_field() الحقل المخفي للرمز في نماذج POST، وتُرجع csrf_token() الرمز نفسه.',
                'المرشّحات المسموحة: abs و batch و capitalize و column و date و default و e / escape و filter و first و join و json_encode و keys و last و length و lower و map و merge و nl2br و number_format و replace و reverse و round و slice و sort و split و striptags و title و trim و upper و url_encode و money و t و json (JSON آمن لجزر البيانات). والدوال الأساسية المسموحة: block و parent و include و cycle و max و min و date.',
            ],
            'code' => "<a href=\"{{ url('product', {id: product.id, slug: product.slug}) }}\">{{ product.name }}</a>\n<a href=\"{{ url('search', {q: 'shoes'}) }}\">{{ t('search.title') }}</a>\n<link rel=\"stylesheet\" href=\"{{ asset('css/theme.css') }}\">\n{{ script('js/theme.js') }}\n{{ money(1999) }}  {{ 1999|money }}\n{{ t('cart.items', {count: cart.count}) }}  {{ 'cart.title'|t }}\n{{ st('cart.title') }}\n{% if setting('show_hero') %}…{% endif %}\n<form method=\"post\" action=\"{{ url('logout') }}\">{{ csrf_field() }}<button>{{ t('account.sign_out') }}</button></form>",
        ],

        'assets_csp' => [
            'title' => 'الملفات وسياسة أمان المحتوى',
            'body' => [
                'تعمل صفحات المتجر بسياسة أمان محتوى صارمة، فيمنع المتصفح كتل <script> المضمّنة وسمات الأحداث مثل onclick="…" وروابط javascript:. ضع JavaScript في assets/js/ وحمّله باستخدام {{ script(\'js/theme.js\') }}.',
                'يُسمح بجزر بيانات JSON ‏(<script type="application/json">) لتمرير البيانات إلى سكربتاتك.',
                'الملفات الخارجية محظورة: ضع الخطوط والصور والأنماط داخل assets/ واستخدم مسارات url() نسبية في CSS. ولا يجوز أن يستخدم CSS الأمر @import لملفات خارجية أو expression() أو ربط السلوكيات.',
                'الأنماط المضمّنة مسموحة، وهي أسهل طريقة لتحويل الإعدادات إلى متغيرات CSS: style="--accent: {{ setting(\'accent\') }}" على وسم <html>.',
                'لا يجوز أن تحتوي ملفات SVG على سكربتات أو سمات أحداث أو روابط (href أو src) أو <use> أو <foreignObject> أو تعريفات DOCTYPE أو ENTITY.',
                'استخدم object-fit: contain لصور المنتجات حتى لا تُقصّ أبدًا.',
            ],
            'code' => "/* assets/css/theme.css */\n.card__img { object-fit: contain; }\n.badge { inset-inline-start: 10px; } /* الخصائص المنطقية تعمل في الاتجاهين */\n@font-face { font-family: \"Brand\"; src: url(\"../fonts/brand.woff2\") format(\"woff2\"); }",
        ],

        'localization' => [
            'title' => 'اللغات والكتابة من اليمين إلى اليسار',
            'body' => [
                'يمر كل نص في قالبك عبر t(). ضع النصوص الإنجليزية في locales/en.json والنصوص العربية في locales/ar.json. يمكن أن تكون المفاتيح كائنات متداخلة وتُقرأ بالنقطة: t(\'cart.title\'). إذا غاب المفتاح في العربية يُستخدم النص الإنجليزي، وإذا غاب في اللغتين يظهر المفتاح نفسه ويحذّرك المدقق.',
                'تبدأ المتغيرات داخل النص بنقطتين رأسيتين: "مرحبًا، :name" مع t(\'header.hello\', {name: customer.name}).',
                'محتوى المتجر (أسماء المنتجات والأقسام والبانرات والصفحات) يصل بلغة المتسوق مسبقًا.',
                'قيمة store.locale هي "en" أو "ar"، وقيمة store.dir هي "ltr" أو "rtl". ضع القيمتين على وسم <html> واستخدم خصائص CSS المنطقية (margin-inline-start و padding-inline-end و inset-inline-start و text-align: start) ليعمل ملف أنماط واحد في الاتجاهين. استخدم المحدد [dir="rtl"] فقط للأشياء القليلة التي يجب عكسها مثل أيقونات الأسهم.',
            ],
            'code' => "{\n    \"header\": {\"hello\": \"Hi, :name\", \"cart\": \"Cart\"}\n}\n\n{\n    \"header\": {\"hello\": \"مرحبًا، :name\", \"cart\": \"السلة\"}\n}",
        ],

        'runtime' => [
            'title' => 'سكربت المنصة: السلة والمفضلة والقسائم',
            'body' => [
                'لا تكتب منطق المتجر بنفسك. يحمّل {{ scripts() }} سكربت المنصة الذي يربط العناصر التي تحمل سمات data-nt-* بالمتجر.',
                'الإضافة إلى السلة: زر يحمل data-nt-add-to-cart و data-product-id (و data-variant-id و data-quantity اختياريًا). داخل نموذج يحمل data-nt-product-form يقرأ السكربت الحقلين variant_id و quantity بدلًا من ذلك.',
                'عدّاد السلة: يُحدَّث كل عنصر يحمل data-nt-cart-count بعد أي تغيير في السلة. صفحة السلة: حقل يحمل data-nt-cart-qty و data-line-id يغيّر الكمية، وزر يحمل data-nt-cart-remove و data-line-id يحذف المنتج، ونموذج يحمل data-nt-coupon-form (حقل name="code") يطبّق القسيمة، وزر يحمل data-nt-coupon-remove يزيلها. تُعاد تحميل الصفحة بالإجماليات الجديدة بعد كل تغيير.',
                'المفضلة: زر يحمل data-nt-favorite و data-product-id يضيف المنتج أو يزيله ويضبط aria-pressed. يُوجَّه الزوار غير المسجلين إلى صفحة تسجيل الدخول.',
                'الخيارات: قائمة select تحمل data-nt-variant-select داخل نموذج المنتج تحدّث العنصرين data-nt-price و data-nt-compare من جزيرة <script type="application/json" data-nt-variants> التي تحتوي product.variants.',
                'النشرة البريدية: نموذج يحمل data-nt-newsletter-form (حقل name="email") ويرسل إلى url(\'newsletter\') يشترك فيه المتسوق دون مغادرة الصفحة.',
                'رسائل الحالة: إذا احتوت الصفحة على عنصر يحمل data-nt-status (استخدم role="status" aria-live="polite") يكتب فيه السكربت رسائل النجاح والخطأ. وتوفر السمات الاختيارية data-msg-added و data-msg-error و data-msg-favorited و data-msg-unfavorited و data-msg-subscribed نصوصك الخاصة عندما لا يرسل الخادم رسالة.',
            ],
            'code' => "<button type=\"button\" data-nt-add-to-cart data-product-id=\"{{ card.id }}\" data-quantity=\"1\">{{ t('product.add_to_cart') }}</button>\n<span data-nt-cart-count>{{ cart.count }}</span>\n\n<form data-nt-product-form>\n    <script type=\"application/json\" data-nt-variants>{{ product.variants|json }}</script>\n    <select name=\"variant_id\" data-nt-variant-select>…</select>\n    <input type=\"number\" name=\"quantity\" value=\"1\" min=\"1\">\n    <button type=\"submit\" data-nt-add-to-cart data-product-id=\"{{ product.id }}\">{{ t('product.add_to_cart') }}</button>\n</form>\n<span data-nt-price>{{ product.price }}</span> <s data-nt-compare>{{ product.compare_price }}</s>\n\n<input type=\"number\" value=\"{{ line.quantity }}\" data-nt-cart-qty data-line-id=\"{{ line.id }}\">\n<button type=\"button\" data-nt-cart-remove data-line-id=\"{{ line.id }}\">{{ t('cart.remove') }}</button>\n<button type=\"button\" data-nt-favorite data-product-id=\"{{ card.id }}\" aria-pressed=\"false\">♥</button>\n<div data-nt-status role=\"status\" aria-live=\"polite\"></div>",
        ],

        'testing_preview' => [
            'title' => 'الرفع والمعاينة والنشر',
            'body' => [
                'كل عملية رفع تُنشئ إصدارًا جديدًا من قالبك، ولا يتغير متجرك المنشور حتى تتم الموافقة على إصدار ونشره.',
                'يعرض تقرير التحقق الأخطاء (لا يمكن استخدام الإصدار حتى تصلحها) والتحذيرات (مثل نص مفقود أو ملف مفقود أو صفحة لا تطبع {{ head() }}). أصلح التحذيرات أيضًا، فهي تظهر للمتسوقين كنصوص أو صور معطّلة.',
                'يمكنك الرجوع إلى أي إصدار منشور سابق في أي وقت، ويعود المتجر إليه فورًا.',
            ],
            'steps' => [
                'ارفع ملف ZIP. تفحص المنصة الملفات، وتترجم كل القوالب، وتعرض كل صفحة ببيانات تجريبية.',
                'اقرأ تقرير التحقق. إذا كان فيه أخطاء فأصلحها وارفع إصدارًا جديدًا.',
                'افتح رابط المعاينة. أنت وحدك ترى المعاينة، بينما يواصل المتسوقون رؤية التصميم الحالي. راجع كل الصفحات بالعربية والإنجليزية وعلى الهاتف.',
                'أرسل الإصدار للمراجعة.',
                'يراجع فريق المنصة القالب ويوافق عليه أو يعيده إليك مع ملاحظات.',
                'انشر الإصدار المعتمد، وسيراه المتسوقون عند تحميل الصفحة التالية.',
                'إذا ظهرت مشكلة فارجع إلى الإصدار السابق.',
            ],
        ],

        'limits' => [
            'title' => 'الحدود',
            'body' => [
                'للحزم وعرض الصفحات حدود للحجم والوقت حتى يبقى كل متجر سريعًا. يعرض الجدول أدناه القيم الحالية: حجم ملف ZIP والحجم بعد فك الضغط، وعدد الملفات، وحجم الملف الواحد والقالب الواحد وكل القوالب معًا، وحجم ملفات JSON، ووقت العرض، وخطوات الحلقات، وعمق التداخل، وحجم النطاق، وعدد الإعدادات، وعدد الإصدارات المحفوظة.',
                'الصفحة التي تتجاوز وقت العرض أو حد خطوات الحلقات تتوقف عن العرض وتظهر صفحة الخطأ، لذلك اجعل الحلقات على بيانات المتجر بسيطة وتجنّب الحلقات المتداخلة على القوائم الكبيرة.',
            ],
        ],

        'security' => [
            'title' => 'قواعد الأمان',
            'body' => [
                'تعمل القوالب داخل بيئة معزولة، ولا يمكن تشغيل أي كود PHP: تُرفض وسوم PHP، ولا تستلم القوالب إلا بيانات بسيطة، ولا تحصل أبدًا على كائنات أو دوال.',
                'لا تعمل إلا الوسوم والمرشّحات والدوال والاختبارات المسموحة المذكورة في هذا الدليل، وأي شيء آخر يوقف الرفع بخطأ. الوسوم autoescape و sandbox و do و flush و deprecated و use و guard و types و cache، والمرشّح raw، والدوال attribute و constant و source و range و random و dump و template_from_string غير متاحة.',
                'كل المخرجات تُهرَّب تلقائيًا من HTML. أما product.description_html و cms_page.body_html فتنظّفهما المنصة مسبقًا وتُطبعان كـ HTML باستخدام {{ }}.',
                'صفحات إتمام الطلب والدفع وتسجيل الدخول وإنشاء الحساب وكلمة المرور وحساب العميل (تفاصيل الطلب والتتبع والمرتجعات والملف الشخصي والعناوين) تستخدم دائمًا صفحات المنصة نفسها لتبقى المدفوعات والبيانات الشخصية محمية.',
                'لا ملفات خارجية ولا سكربتات مضمّنة: كل ما يحتاجه قالبك يجب أن يكون داخل الحزمة.',
            ],
        ],
    ],

    'faq' => [
        [
            'q' => 'لماذا لا يعمل كود JavaScript الخاص بي؟',
            'a' => 'تمنع سياسة أمان المتجر كتل <script> المضمّنة وسمات onclick="…". انقل الكود إلى assets/js/theme.js وحمّله باستخدام {{ script(\'js/theme.js\') }} قبل </body>.',
        ],
        [
            'q' => 'هل يمكنني تغيير صفحة إتمام الطلب؟',
            'a' => 'لا. تستخدم صفحات إتمام الطلب والدفع وتسجيل الدخول دائمًا صفحات المنصة الآمنة، وتُعرض بالقالب المدمج لمتجرك.',
        ],
        [
            'q' => 'كيف أعرض السعر بعملة أخرى؟',
            'a' => 'لا تحتاج إلى ذلك. الأسعار مثل product.price تصلك منسّقة بالعملة التي اختارها المتسوق. وللمبالغ بالوحدات الصغرى استخدم money(amount).',
        ],
        [
            'q' => 'يظهر مفتاح النص (مثل "cart.title") بدلًا من الجملة.',
            'a' => 'المفتاح غير موجود في locales/en.json. أضفه (مع ترجمته العربية في locales/ar.json) وارفع إصدارًا جديدًا. يعرض تقرير التحقق كل المفاتيح المفقودة.',
        ],
        [
            'q' => 'كيف أضيف إعدادًا جديدًا؟',
            'a' => 'أضف عنصرًا إلى settings في theme.json يحتوي على id و type و label بالإنجليزية والعربية وقيمة default، ثم اقرأه باستخدام setting(\'id\'). بعد رفع الإصدار الجديد يظهر الحقل في نموذج إعدادات القالب.',
        ],
        [
            'q' => 'هل يمكنني استخدام خطي الخاص؟',
            'a' => 'نعم. ضع ملف woff2 في assets/fonts/، وعرّفه باستخدام @font-face في ملف CSS مع مسار url() نسبي، ثم استخدمه في font-family. أو استخدم إعدادًا من نوع font لاختيار أحد خطوط المنصة.',
        ],
        [
            'q' => 'يُرفض الرفع برسالة "path traversal" أو "hidden file".',
            'a' => 'يحتوي ملف ZIP على ملفات مخفية مثل ‎.git أو ‎.env، أو على مسارات فيها "..". أنشئ ملف ZIP من داخل مجلد القالب واستبعد الملفات والمجلدات المخفية.',
        ],
    ],
];
