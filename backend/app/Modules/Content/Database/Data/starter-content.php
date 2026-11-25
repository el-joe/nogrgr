<?php

/*
| Starter content of the central website (P6), seeded by ContentStarterSeeder in ar + en.
| ":app" is replaced with config('app.name'). Every text stays editable in
| Admin › Website (pages, FAQs, blog).
*/

return [
    'pages' => [
        'about' => [
            'title' => ['en' => 'About us', 'ar' => 'من نحن'],
            'meta_description' => [
                'en' => ':app is an e-commerce platform that lets merchants launch and grow online stores without writing code.',
                'ar' => ':app منصة تجارة إلكترونية تمكّن التجار من إطلاق متاجرهم الإلكترونية وتنميتها دون كتابة أي كود.',
            ],
            'body' => [
                'en' => '<h2>Our mission</h2><p>We believe every merchant deserves a professional online store. :app removes the technical work so you can focus on products, customers and growth.</p><h2>What we offer</h2><ul><li><strong>13 ready-made templates</strong> designed for fashion, beauty, electronics, grocery, furniture and more.</li><li><strong>A ready product catalog</strong> you can import into your store with your own prices.</li><li><strong>Payments and shipping</strong> connected from your dashboard, with clear order tracking for your customers.</li><li><strong>Arabic and English</strong> storefronts, multiple currencies and right-to-left support out of the box.</li></ul><h2>How we work</h2><p>Our team supports merchants from the first day: choosing a plan, setting up the store, connecting a domain and launching. We keep improving the platform every month based on what our merchants need.</p>',
                'ar' => '<h2>رسالتنا</h2><p>نؤمن بأن كل تاجر يستحق متجرًا إلكترونيًا احترافيًا. يتولى :app العمل التقني عنك لتتفرغ للمنتجات والعملاء والنمو.</p><h2>ماذا نقدّم</h2><ul><li><strong>13 قالبًا جاهزًا</strong> مصممة للأزياء والتجميل والإلكترونيات والبقالة والأثاث وغيرها.</li><li><strong>كتالوج منتجات جاهز</strong> يمكنك استيراده إلى متجرك بأسعارك الخاصة.</li><li><strong>الدفع والشحن</strong> يتم ربطهما من لوحة التحكم مع تتبع واضح للطلبات لعملائك.</li><li><strong>واجهات بالعربية والإنجليزية</strong> وعملات متعددة ودعم كامل للكتابة من اليمين إلى اليسار.</li></ul><h2>كيف نعمل</h2><p>يرافق فريقنا التجار منذ اليوم الأول: اختيار الباقة وتجهيز المتجر وربط النطاق والإطلاق، ونطوّر المنصة كل شهر بناءً على احتياجات تجارنا.</p>',
            ],
        ],
        'terms' => [
            'title' => ['en' => 'Terms and conditions', 'ar' => 'الشروط والأحكام'],
            'meta_description' => [
                'en' => 'The terms that apply when you create and run a store on :app.',
                'ar' => 'الشروط التي تنطبق عند إنشاء متجرك وإدارته على :app.',
            ],
            'body' => [
                'en' => '<h2>1. Acceptance of the terms</h2><p>By creating an account or using :app you agree to these terms. If you use the platform on behalf of a company, you confirm that you are authorised to accept them for that company.</p><h2>2. Your account</h2><p>You are responsible for the accuracy of the information you provide, for keeping your password secure and for all activity under your account. Tell us immediately if you suspect unauthorised access.</p><h2>3. Plans, trials and billing</h2><p>Paid plans are billed in advance monthly or yearly in US dollars. A free trial converts to the selected plan at the end of the trial unless you cancel. Fees already paid are not refundable except where the law requires it.</p><h2>4. Your store and content</h2><p>You own the products, texts and images you publish. You confirm that you have the rights to sell your products and that your store complies with the laws of the countries you sell to. We may suspend stores that sell prohibited goods or harm customers.</p><h2>5. Payments and orders</h2><p>Payments made by your customers are processed by the payment providers you connect. You are responsible for fulfilling orders, handling returns and refunds and answering your customers.</p><h2>6. Acceptable use</h2><p>You must not use the platform to send spam, distribute malware, infringe intellectual property or attempt to access other stores or our systems without permission.</p><h2>7. Availability and changes</h2><p>We work to keep the service available at all times and may update features to improve it. Planned maintenance is announced in advance whenever possible.</p><h2>8. Termination</h2><p>You can close your store at any time from your account. We may suspend or close an account that breaches these terms after notice, or immediately in serious cases.</p><h2>9. Liability</h2><p>The service is provided as is. To the extent permitted by law, our total liability is limited to the fees you paid in the twelve months before the claim.</p><h2>10. Contact</h2><p>Questions about these terms can be sent through the contact page.</p>',
                'ar' => '<h2>1. قبول الشروط</h2><p>بإنشاء حساب أو استخدام :app فإنك توافق على هذه الشروط. وإذا كنت تستخدم المنصة نيابة عن شركة فإنك تؤكد أنك مخوّل بقبولها باسمها.</p><h2>2. حسابك</h2><p>أنت مسؤول عن صحة البيانات التي تقدمها وعن الحفاظ على سرية كلمة المرور وعن كل نشاط يتم عبر حسابك، وعليك إبلاغنا فورًا عند الاشتباه في أي دخول غير مصرح به.</p><h2>3. الباقات والفترة التجريبية والفوترة</h2><p>تُدفع الباقات المدفوعة مقدمًا شهريًا أو سنويًا بالدولار الأمريكي، وتتحول الفترة التجريبية المجانية إلى الباقة المختارة عند انتهائها ما لم تلغها. الرسوم المدفوعة غير قابلة للاسترداد إلا فيما يفرضه القانون.</p><h2>4. متجرك ومحتواه</h2><p>تملك المنتجات والنصوص والصور التي تنشرها، وتؤكد أن لديك حق بيع منتجاتك وأن متجرك يلتزم بقوانين الدول التي تبيع فيها. ويحق لنا إيقاف المتاجر التي تبيع سلعًا محظورة أو تضر بالعملاء.</p><h2>5. المدفوعات والطلبات</h2><p>تتم معالجة مدفوعات عملائك عبر مزودي الدفع الذين تربطهم بمتجرك، وأنت مسؤول عن تنفيذ الطلبات ومعالجة المرتجعات والاستردادات والرد على عملائك.</p><h2>6. الاستخدام المقبول</h2><p>يُمنع استخدام المنصة لإرسال الرسائل المزعجة أو نشر البرمجيات الضارة أو انتهاك حقوق الملكية الفكرية أو محاولة الوصول إلى متاجر أخرى أو إلى أنظمتنا دون إذن.</p><h2>7. التوفر والتحديثات</h2><p>نعمل على إبقاء الخدمة متاحة دائمًا وقد نحدّث المزايا لتحسينها، ونعلن عن الصيانة المجدولة مسبقًا كلما أمكن.</p><h2>8. إنهاء الخدمة</h2><p>يمكنك إغلاق متجرك في أي وقت من حسابك، ويحق لنا إيقاف أو إغلاق الحساب المخالف لهذه الشروط بعد الإشعار، أو فورًا في الحالات الجسيمة.</p><h2>9. المسؤولية</h2><p>تُقدّم الخدمة كما هي، وفي الحدود التي يسمح بها القانون تقتصر مسؤوليتنا الإجمالية على الرسوم التي دفعتها خلال الاثني عشر شهرًا السابقة للمطالبة.</p><h2>10. التواصل</h2><p>يمكنك إرسال أي استفسار عن هذه الشروط عبر صفحة تواصل معنا.</p>',
            ],
        ],
        'privacy' => [
            'title' => ['en' => 'Privacy policy', 'ar' => 'سياسة الخصوصية'],
            'meta_description' => [
                'en' => 'How :app collects, uses and protects the personal data of merchants and visitors.',
                'ar' => 'كيف يجمع :app البيانات الشخصية للتجار والزوار ويستخدمها ويحميها.',
            ],
            'body' => [
                'en' => '<h2>Data we collect</h2><p>When you register we collect your name, e-mail, phone number and store details. When you use the platform we record technical data such as IP address, browser and pages visited to keep the service secure and reliable.</p><h2>How we use it</h2><ul><li>To create and operate your store and your account.</li><li>To process subscription payments and send invoices.</li><li>To send service messages, and newsletters only if you subscribed.</li><li>To prevent fraud and abuse and to improve the platform.</li></ul><h2>Your customers\' data</h2><p>Data your customers enter in your store belongs to you as the merchant. We process it on your behalf only to run your store, and we never sell it.</p><h2>Sharing</h2><p>We share data only with providers that help us run the service (hosting, e-mail, payment and shipping providers you connect) and when the law requires it.</p><h2>Cookies</h2><p>We use essential cookies for sign-in, language and security. Analytics are used to understand how the website is used and can be refused in your browser.</p><h2>Security and retention</h2><p>Data is encrypted in transit, access is limited to authorised staff and we keep data only as long as your account is active or the law requires.</p><h2>Your rights</h2><p>You can request a copy of your data, its correction or deletion, and unsubscribe from marketing messages at any time using the link in every e-mail.</p><h2>Contact</h2><p>For privacy questions use the contact page and we will reply within 30 days.</p>',
                'ar' => '<h2>البيانات التي نجمعها</h2><p>عند التسجيل نجمع اسمك وبريدك الإلكتروني ورقم هاتفك وبيانات متجرك، وعند استخدام المنصة نسجل بيانات تقنية مثل عنوان IP والمتصفح والصفحات التي تزورها للحفاظ على أمان الخدمة واستقرارها.</p><h2>كيف نستخدمها</h2><ul><li>لإنشاء متجرك وحسابك وتشغيلهما.</li><li>لمعالجة مدفوعات الاشتراك وإرسال الفواتير.</li><li>لإرسال رسائل الخدمة، والنشرة البريدية فقط إذا اشتركت فيها.</li><li>لمنع الاحتيال وإساءة الاستخدام وتحسين المنصة.</li></ul><h2>بيانات عملائك</h2><p>البيانات التي يدخلها عملاؤك في متجرك ملك لك بصفتك التاجر، ونعالجها نيابة عنك فقط لتشغيل متجرك ولا نبيعها أبدًا.</p><h2>المشاركة</h2><p>نشارك البيانات فقط مع مزودي الخدمات الذين يساعدوننا في تشغيلها (الاستضافة والبريد ومزودو الدفع والشحن الذين تربطهم) وعندما يفرض القانون ذلك.</p><h2>ملفات تعريف الارتباط</h2><p>نستخدم ملفات أساسية لتسجيل الدخول واللغة والأمان، وأدوات تحليل لفهم استخدام الموقع ويمكنك رفضها من متصفحك.</p><h2>الأمان ومدة الاحتفاظ</h2><p>تُشفّر البيانات أثناء النقل، ويقتصر الوصول إليها على الموظفين المخولين، ونحتفظ بها طوال نشاط حسابك أو حسب ما يفرضه القانون.</p><h2>حقوقك</h2><p>يمكنك طلب نسخة من بياناتك أو تصحيحها أو حذفها، وإلغاء الاشتراك في الرسائل التسويقية في أي وقت عبر الرابط الموجود في كل رسالة.</p><h2>التواصل</h2><p>لأي استفسار عن الخصوصية استخدم صفحة تواصل معنا وسنرد خلال 30 يومًا.</p>',
            ],
        ],
    ],

    'faqs' => [
        ['general', 'How long does it take to launch my store?', 'كم يستغرق إطلاق متجري؟', 'Most merchants go live the same day: sign up, pick a template, import products from the catalog and connect a payment method.', 'يطلق معظم التجار متاجرهم في اليوم نفسه: سجّل واختر قالبًا واستورد المنتجات من الكتالوج واربط وسيلة دفع.'],
        ['general', 'Do I need technical or design skills?', 'هل أحتاج إلى خبرة تقنية أو تصميمية؟', 'No. Templates are ready, settings are guided by a setup wizard and everything is managed from a simple dashboard.', 'لا، فالقوالب جاهزة والإعدادات يقودها معالج إعداد خطوة بخطوة وكل شيء يُدار من لوحة تحكم بسيطة.'],
        ['stores', 'Can I change my template later?', 'هل يمكنني تغيير القالب لاحقًا؟', 'Yes. You can switch to any template included in your plan at any time; your products, orders and customers stay the same.', 'نعم، يمكنك التبديل إلى أي قالب ضمن باقتك في أي وقت، وتبقى منتجاتك وطلباتك وعملاؤك كما هي.'],
        ['stores', 'Can I use my own domain?', 'هل يمكنني استخدام نطاقي الخاص؟', 'Yes. Every store gets a free sub-domain, and plans with custom domains let you connect your own domain with step-by-step DNS instructions.', 'نعم، يحصل كل متجر على نطاق فرعي مجاني، وتتيح الباقات التي تدعم النطاقات الخاصة ربط نطاقك مع تعليمات DNS خطوة بخطوة.'],
        ['stores', 'Is my store available in Arabic and English?', 'هل يتوفر متجري بالعربية والإنجليزية؟', 'Yes. Every storefront supports Arabic (right to left) and English, and you can add more languages and currencies.', 'نعم، تدعم كل واجهات المتاجر العربية (من اليمين لليسار) والإنجليزية، ويمكنك إضافة لغات وعملات أخرى.'],
        ['billing', 'Is there a free trial?', 'هل توجد فترة تجريبية مجانية؟', 'Plans with a trial show a "Trial" button on the pricing page. You can explore the dashboard and build your store before paying.', 'تظهر الباقات التي تتضمن فترة تجريبية زر «تجربة» في صفحة الأسعار، ويمكنك استكشاف لوحة التحكم وبناء متجرك قبل الدفع.'],
        ['billing', 'Can I upgrade or downgrade my plan?', 'هل يمكنني ترقية باقتي أو تخفيضها؟', 'Yes, from your account at any time. Upgrades apply immediately; downgrades apply from the next billing period.', 'نعم، من حسابك في أي وقت؛ تطبق الترقية فورًا ويطبق التخفيض من فترة الفوترة التالية.'],
        ['technical', 'How do my customers pay?', 'كيف يدفع عملائي؟', 'Connect the payment gateways available in your country from the dashboard. Customers pay by card or the local methods you enable.', 'اربط بوابات الدفع المتاحة في بلدك من لوحة التحكم، ويدفع عملاؤك بالبطاقة أو بالوسائل المحلية التي تفعّلها.'],
        ['technical', 'How does shipping work?', 'كيف يتم الشحن؟', 'Shipping prices are calculated automatically by weight and destination, and every order can be tracked by you and your customer.', 'تُحسب أسعار الشحن تلقائيًا حسب الوزن والوجهة، ويمكنك أنت وعميلك تتبع كل طلب.'],
    ],

    'blog_category' => [
        'slug' => 'store-guides',
        'name' => ['en' => 'Store guides', 'ar' => 'أدلة المتاجر'],
        'description' => ['en' => 'Practical guides for running an online store.', 'ar' => 'أدلة عملية لإدارة متجر إلكتروني.'],
    ],

    'posts' => [
        [
            'slug' => 'launch-your-online-store',
            'days_ago' => 14,
            'title' => ['en' => 'How to launch your online store in one day', 'ar' => 'كيف تطلق متجرك الإلكتروني في يوم واحد'],
            'excerpt' => [
                'en' => 'A clear checklist to go from idea to first order: choose a template, add products, set up payments and shipping, then launch.',
                'ar' => 'قائمة واضحة للانتقال من الفكرة إلى أول طلب: اختر قالبًا وأضف المنتجات واضبط الدفع والشحن ثم انطلق.',
            ],
            'body' => [
                'en' => '<p>Opening an online store no longer takes months. With the right preparation you can publish a professional store and receive your first order within a day.</p><h2>1. Pick a template that matches your products</h2><p>Start from a template designed for your category. A fashion store needs large images and lookbooks; a grocery store needs fast category navigation and clear prices.</p><h2>2. Add your first products</h2><p>Import ready products from the catalog or add your own. Write a short, benefit-focused description, set a fair price and upload at least three photos per product.</p><h2>3. Connect payments</h2><p>Enable the gateways your customers trust and test a small payment before launch.</p><h2>4. Configure shipping</h2><p>Set the countries you deliver to and check the shipping price on a few sample orders.</p><h2>5. Publish and share</h2><p>Connect your domain, share the store link on your social accounts and ask your first customers for feedback.</p>',
                'ar' => '<p>لم يعد افتتاح متجر إلكتروني يستغرق شهورًا، فمع التحضير الصحيح يمكنك نشر متجر احترافي واستقبال أول طلب خلال يوم واحد.</p><h2>1. اختر قالبًا يناسب منتجاتك</h2><p>ابدأ بقالب مصمم لمجالك؛ فمتجر الأزياء يحتاج صورًا كبيرة وعروضًا مرئية، ومتجر البقالة يحتاج تنقلًا سريعًا بين الأقسام وأسعارًا واضحة.</p><h2>2. أضف منتجاتك الأولى</h2><p>استورد منتجات جاهزة من الكتالوج أو أضف منتجاتك، واكتب وصفًا قصيرًا يركز على الفائدة، وحدد سعرًا عادلًا، وارفع ثلاث صور على الأقل لكل منتج.</p><h2>3. اربط وسائل الدفع</h2><p>فعّل البوابات التي يثق بها عملاؤك وجرّب عملية دفع صغيرة قبل الإطلاق.</p><h2>4. اضبط الشحن</h2><p>حدد الدول التي توصل إليها وراجع سعر الشحن على بعض الطلبات التجريبية.</p><h2>5. انشر متجرك وشاركه</h2><p>اربط نطاقك وشارك رابط المتجر على حساباتك الاجتماعية واطلب آراء عملائك الأوائل.</p>',
            ],
        ],
        [
            'slug' => 'product-photos-that-sell',
            'days_ago' => 8,
            'title' => ['en' => 'Product photos that sell: a simple guide', 'ar' => 'صور منتجات تبيع: دليل مبسّط'],
            'excerpt' => [
                'en' => 'Good photos raise conversion more than any discount. Here is how to shoot clean product images with a phone and natural light.',
                'ar' => 'الصور الجيدة ترفع المبيعات أكثر من أي خصم. إليك طريقة تصوير منتجاتك بصور نظيفة باستخدام الهاتف والإضاءة الطبيعية.',
            ],
            'body' => [
                'en' => '<p>Customers cannot touch your products, so photos do the selling. You do not need a studio: a phone, a window and a white background are enough.</p><h2>Use soft natural light</h2><p>Shoot next to a window during the day and avoid direct sun. Turn off room lights to keep colours accurate.</p><h2>Keep the background simple</h2><p>A white or light grey background keeps the focus on the product and looks consistent across your store.</p><h2>Show every angle</h2><p>Take a front shot, a side shot, a close-up of details and one photo of the product in use so customers understand the size.</p><h2>Keep sizes consistent</h2><p>Crop every photo to the same ratio and upload high-resolution files; the platform optimises them automatically for fast loading.</p>',
                'ar' => '<p>لا يستطيع العميل لمس منتجك، لذلك تقوم الصور بمهمة البيع. لا تحتاج إلى استوديو؛ يكفي هاتف ونافذة وخلفية بيضاء.</p><h2>استخدم إضاءة طبيعية ناعمة</h2><p>صوّر بجوار نافذة نهارًا وتجنب أشعة الشمس المباشرة، وأطفئ إضاءة الغرفة للحفاظ على دقة الألوان.</p><h2>اجعل الخلفية بسيطة</h2><p>الخلفية البيضاء أو الرمادية الفاتحة تبقي التركيز على المنتج وتمنح متجرك مظهرًا متناسقًا.</p><h2>اعرض كل الزوايا</h2><p>التقط صورة أمامية وجانبية ولقطة قريبة للتفاصيل وصورة للمنتج أثناء الاستخدام ليفهم العميل حجمه.</p><h2>وحّد المقاسات</h2><p>اقتص كل الصور بالنسبة نفسها وارفع ملفات عالية الدقة، وستتولى المنصة ضغطها تلقائيًا لتحميل أسرع.</p>',
            ],
        ],
        [
            'slug' => 'shipping-and-payments-guide',
            'days_ago' => 3,
            'title' => ['en' => 'Shipping and payments: what every new store should set up', 'ar' => 'الشحن والدفع: ما يجب أن يضبطه كل متجر جديد'],
            'excerpt' => [
                'en' => 'Clear delivery prices and trusted payment methods remove the two biggest reasons customers abandon their carts.',
                'ar' => 'أسعار التوصيل الواضحة ووسائل الدفع الموثوقة تزيل أكبر سببين لترك العملاء سلال التسوق.',
            ],
            'body' => [
                'en' => '<p>Most abandoned carts happen at checkout: an unexpected shipping fee or a missing payment method. Setting both up carefully pays off immediately.</p><h2>Show shipping costs early</h2><p>Display delivery prices and times on product pages and in the cart so customers are never surprised at the last step.</p><h2>Offer the payment methods your market uses</h2><p>Cards are essential, but many customers prefer local wallets or cash on delivery. Enable the options that are common in your country.</p><h2>Set a clear return policy</h2><p>A short, fair return policy builds trust. Mention the return window and who pays return shipping.</p><h2>Keep customers informed</h2><p>Order confirmation, shipping and delivery e-mails reduce support questions and bring customers back.</p>',
                'ar' => '<p>تحدث معظم حالات ترك السلة عند الدفع: رسوم شحن غير متوقعة أو وسيلة دفع غير متاحة، لذا فإن ضبط الأمرين بعناية يعطي نتيجة فورية.</p><h2>اعرض تكلفة الشحن مبكرًا</h2><p>اعرض أسعار التوصيل ومدته في صفحات المنتجات وفي السلة حتى لا يتفاجأ العميل في الخطوة الأخيرة.</p><h2>وفّر وسائل الدفع المعتادة في سوقك</h2><p>البطاقات أساسية، لكن كثيرًا من العملاء يفضلون المحافظ المحلية أو الدفع عند الاستلام، ففعّل الخيارات الشائعة في بلدك.</p><h2>ضع سياسة إرجاع واضحة</h2><p>سياسة إرجاع قصيرة وعادلة تبني الثقة؛ اذكر مدة الإرجاع ومن يتحمل تكلفة شحن المرتجع.</p><h2>أبقِ عملاءك على اطلاع</h2><p>رسائل تأكيد الطلب والشحن والتسليم تقلل أسئلة الدعم وتعيد العملاء للشراء مرة أخرى.</p>',
            ],
        ],
    ],
];
