<?php

/*
| الموضوع والنص الافتراضي لكل قالب بريد (سجل EmailTemplateEvent، القرار #118).
*/

return [
    'vendor' => [
        'welcome' => [
            'subject' => 'مرحبًا بك في {{ platform.name }}، متجر {{ store.name }} جاهز',
            'body' => '<h2>مرحبًا بك في {{ platform.name }}، {{ vendor.name }}</h2><p>متجرك {{ store.name }} جاهز. أكمل الإعداد وابدأ البيع اليوم.</p><table><tbody><tr><td>المتجر</td><td>{{ store.name }}</td></tr><tr><td>العنوان</td><td>{{ store.url }}</td></tr></tbody></table><p><a href="{{ login.url }}">افتح لوحة متجرك</a></p><p>تحتاج مساعدة؟ ردّ على هذه الرسالة.</p>',
        ],
        'registration_link' => [
            'subject' => 'أكمل تسجيلك في {{ platform.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>شكرًا لاختيارك {{ platform.name }}. استخدم الزر أدناه لإكمال إعداد متجرك.</p><p><a href="{{ registration.url }}">أكمل التسجيل</a></p><p>الرابط صالح لمدة {{ registration.expires_hours }} ساعة. إن لم تطلبه يمكنك تجاهل هذه الرسالة.</p>',
        ],
        'registration_receipt' => [
            'subject' => 'تم استلام الدفع - {{ platform.name }}',
            'body' => '<p>مرحبًا،</p><p>استلمنا دفعتك لباقة <strong>{{ plan.name }}</strong> ({{ plan.term }}).</p><table><tbody><tr><td>المبلغ</td><td>{{ payment.amount }}</td></tr><tr><td>المرجع</td><td>{{ payment.reference }}</td></tr></tbody></table><p>نجهّز متجرك الآن وسنراسلك فور جاهزيته.</p>',
        ],
        'invoice_issued' => [
            'subject' => 'فاتورة جديدة {{ invoice.number }} لمتجر {{ store.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>صدرت الفاتورة <strong>{{ invoice.number }}</strong> بقيمة {{ invoice.total }} لمتجر {{ store.name }}، وتستحق في {{ invoice.due_date }}.</p><p><a href="{{ invoice.url }}">عرض الفاتورة</a></p>',
        ],
        'invoice_paid' => [
            'subject' => 'تم دفع الفاتورة {{ invoice.number }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>شكرًا لك! تم دفع الفاتورة <strong>{{ invoice.number }}</strong> بقيمة {{ invoice.total }} لمتجر {{ store.name }}.</p><p><a href="{{ invoice.url }}">عرض الفواتير</a></p>',
        ],
        'payment_failed' => [
            'subject' => 'فشل دفع الفاتورة {{ invoice.number }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>تعذّر تحصيل مبلغ {{ payment.amount }} عبر {{ payment.gateway }} للفاتورة {{ invoice.number }}. يرجى تحديث وسيلة الدفع ليستمر عمل {{ store.name }}.</p><p><a href="{{ invoice.url }}">تحديث الدفع</a></p>',
        ],
        'payment_refunded' => [
            'subject' => 'تم استرداد دفعة الفاتورة {{ invoice.number }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>استرددنا مبلغ {{ payment.amount }} عبر {{ payment.gateway }} للفاتورة {{ invoice.number }}. قد يستغرق وصوله إلى حسابك بضعة أيام.</p><p><a href="{{ invoice.url }}">عرض الفواتير</a></p>',
        ],
        'subscription_activated' => [
            'subject' => 'تم تفعيل باقة {{ plan.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }} مفعّلة الآن حتى {{ subscription.ends_at }}.</p>',
        ],
        'subscription_past_due' => [
            'subject' => 'تعذّر تجديد باقة {{ plan.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>فشل تجديد باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }}. يرجى دفع الفاتورة المفتوحة قبل {{ subscription.ends_at }} لتجنب التوقف.</p>',
        ],
        'subscription_expired' => [
            'subject' => 'انتهت باقة {{ plan.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>انتهت باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }} في {{ subscription.ends_at }}. جدّدها لإعادة فتح متجرك.</p>',
        ],
        'compliance_decision' => [
            'subject' => 'مراجعة الامتثال لمتجر {{ store.name }}: {{ compliance.status }}',
            'body' => '<p>مرحبًا،</p><p>{{ compliance.message }}</p><blockquote>{{ compliance.note }}</blockquote><p>لأي استفسار، ردّ على هذه الرسالة أو افتح تذكرة دعم من لوحتك.</p>',
        ],
        'verify_email' => [
            'subject' => 'أكّد بريدك الإلكتروني',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>يرجى تأكيد بريدك الإلكتروني لإكمال إنشاء حساب متجرك.</p><p><a href="{{ verify.url }}">تأكيد البريد</a></p><p>تنتهي صلاحية الرابط خلال {{ verify.expires_minutes }} دقيقة.</p>',
        ],
        'password_reset' => [
            'subject' => 'إعادة تعيين كلمة مرور {{ platform.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>تلقّينا طلبًا لإعادة تعيين كلمة مرور حساب متجرك.</p><p><a href="{{ reset.url }}">إعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية الرابط خلال {{ reset.expires_minutes }} دقيقة. إن لم تطلب ذلك فتجاهل هذه الرسالة.</p>',
        ],
        'staff_invitation' => [
            'subject' => 'دعوة للانضمام إلى {{ store.name }}',
            'body' => '<p>مرحبًا،</p><p>دعاك {{ inviter.name }} للانضمام إلى <strong>{{ store.name }}</strong> بدور {{ role.name }}.</p><p><a href="{{ invitation.url }}">قبول الدعوة</a></p><p>تنتهي صلاحية الدعوة خلال {{ invitation.expires_minutes }} دقيقة. إن لم تكن تتوقعها فتجاهل هذه الرسالة.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح في لوحتك</a></p>',
        ],
        'new_order' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<h2>طلب جديد</h2><p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'order_cancelled' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'return_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح المرتجع</a></p>',
        ],
        'refund_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'payout_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح المالية</a></p>',
        ],
        'review_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح التقييمات</a></p>',
        ],
        'contact_message' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الرسالة</a></p>',
        ],
        'request_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'theme_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح القوالب</a></p>',
        ],
        'team_update' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الفريق</a></p>',
        ],
        'subscription_cancelled' => [
            'subject' => 'تم إلغاء باقة {{ plan.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>تم إلغاء باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }}. يبقى متجرك مفتوحًا حتى {{ subscription.ends_at }}.</p><p>يمكنك اختيار باقة من جديد في أي وقت من صفحة الفواتير.</p>',
        ],
        'subscription_renewed' => [
            'subject' => 'تم تجديد باقة {{ plan.name }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>تم تجديد باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }} حتى {{ subscription.ends_at }}. شكرًا لك!</p>',
        ],
        'invoice_voided' => [
            'subject' => 'تم إلغاء الفاتورة {{ invoice.number }}',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>تم إلغاء الفاتورة <strong>{{ invoice.number }}</strong> بقيمة {{ invoice.total }} لمتجر {{ store.name }}. لا حاجة لدفعها.</p><p><a href="{{ invoice.url }}">عرض الفواتير</a></p>',
        ],
        'domain_connected' => [
            'subject' => 'تم ربط {{ domain.name }} بمتجر {{ store.name }}',
            'body' => '<h2>نطاقك يعمل الآن</h2><p>مرحبًا {{ vendor.name }}،</p><p>تم التحقق من النطاق <strong>{{ domain.name }}</strong> وأصبح يفتح {{ store.name }} بشهادة آمنة.</p><p><a href="{{ login.url }}">افتح لوحة متجرك</a></p>',
        ],
        'domain_dns_failed' => [
            'subject' => 'تعذر التحقق من {{ domain.name }}',
            'body' => '<h2>نطاقك يحتاج إلى مراجعة</h2><p>مرحبًا {{ vendor.name }}،</p><p>لم نجد في آخر فحص سجلات DNS الخاصة بالنطاق <strong>{{ domain.name }}</strong> لمتجر {{ store.name }}. قد لا يتمكن الزوار من فتح متجرك على هذا النطاق حتى تعود السجلات.</p><p>افتح الإعدادات › النطاقات في لوحة متجرك وقارن السجلات مع مزود DNS ثم أعد الفحص.</p><p><a href="{{ login.url }}">افتح لوحة متجرك</a></p>',
        ],
        'low_stock' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><table><tbody><tr><td>المنتج</td><td>{{ product.name }}</td></tr><tr><td>المتوفر</td><td>{{ product.stock }}</td></tr><tr><td>حد التنبيه</td><td>{{ product.threshold }}</td></tr></tbody></table><p><a href="{{ action.url }}">افتح المنتج</a></p>',
        ],
        'settlement_due' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><table><tbody><tr><td>المبلغ المستحق</td><td>{{ settlement.amount }}</td></tr></tbody></table><p><a href="{{ action.url }}">ادفع للمنصة</a></p>',
        ],
        'settlement_receipt' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><table><tbody><tr><td>المبلغ</td><td>{{ settlement.amount }}</td></tr><tr><td>المرجع</td><td>{{ settlement.reference }}</td></tr><tr><td>الطلب</td><td>{{ order.number }}</td></tr></tbody></table><p><a href="{{ action.url }}">افتح المالية</a></p>',
        ],
        'gateway_limit' => [
            'subject' => '{{ alert.title }} · {{ store.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p>اربط مفاتيح بوابة الدفع الخاصة بك من الإعدادات › المدفوعات لتواصل استقبال المدفوعات الإلكترونية دون حدود.</p><p><a href="{{ action.url }}">افتح إعدادات الدفع</a></p>',
        ],
        'subscription_renewal_reminder' => [
            'subject' => 'تجديد باقة {{ plan.name }} بعد {{ reminder.days }} أيام',
            'body' => '<p>مرحبًا {{ vendor.name }}،</p><p>تنتهي باقة <strong>{{ plan.name }}</strong> لمتجر {{ store.name }} في {{ subscription.ends_at }}، أي بعد {{ reminder.days }} أيام. جدّدها في الوقت المناسب ليبقى متجرك متاحًا.</p><p><a href="{{ renew.url }}">جدّد باقتك</a></p>',
        ],
    ],
    'order' => [
        'confirmation' => [
            'subject' => 'تم تأكيد الطلب {{ order.number }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>شكرًا لتسوقك من {{ store.name }}! استلمنا طلبك <strong>{{ order.number }}</strong> ({{ order.total }}) وسنبلغك عند شحنه. فاتورتك متاحة في صفحة الطلب.</p><p><a href="{{ order.url }}">عرض طلبك</a></p>',
        ],
        'shipped' => [
            'subject' => 'طلبك {{ order.number }} في الطريق إليك',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>أخبار سارة: تم شحن الطلب <strong>{{ order.number }}</strong> مع {{ shipment.company }}. رقم التتبع: {{ shipment.tracking_no }}.</p><p><a href="{{ shipment.tracking_url }}">تتبّع شحنتك</a></p>',
        ],
        'delivered' => [
            'subject' => 'تم توصيل طلبك {{ order.number }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>تم توصيل الطلب <strong>{{ order.number }}</strong>. نتمنى أن ينال إعجابك! شارك رأيك مع المتسوقين الآخرين.</p><p><a href="{{ order.review_url }}">اكتب تقييمًا</a></p>',
        ],
        'cancelled' => [
            'subject' => 'تم إلغاء الطلب {{ order.number }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>تم إلغاء طلبك <strong>{{ order.number }}</strong> من {{ store.name }}. إذا دفعت إلكترونيًا فالمبلغ في طريقه إليك.</p><p><a href="{{ order.url }}">عرض طلبك</a></p>',
        ],
        'refund_processed' => [
            'subject' => 'تم استرداد {{ refund.amount }} للطلب {{ order.number }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>استرددنا <strong>{{ refund.amount }}</strong> للطلب {{ order.number }}. قد يستغرق ظهوره في كشف حسابك بضعة أيام.</p><p><a href="{{ order.url }}">عرض طلبك</a></p>',
        ],
        'update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>{{ alert.body }}</p><p><a href="{{ order.url }}">عرض طلبك</a></p>',
        ],
        'shipping_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>{{ alert.body }}</p><table><tbody><tr><td>الطلب</td><td>{{ order.number }}</td></tr><tr><td>شركة الشحن</td><td>{{ shipment.company }}</td></tr><tr><td>رقم التتبع</td><td>{{ shipment.tracking_no }}</td></tr></tbody></table><p><a href="{{ order.url }}">اعرض طلبك</a></p>',
        ],
        'return_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>{{ alert.body }}</p><table><tbody><tr><td>المرتجع</td><td>{{ return.reference }}</td></tr><tr><td>الحالة</td><td>{{ return.status }}</td></tr><tr><td>الطلب</td><td>{{ order.number }}</td></tr></tbody></table><p>{{ return.message }}</p><p><a href="{{ order.url }}">اعرض طلبك</a></p>',
        ],
    ],
    'customer' => [
        'password_reset' => [
            'subject' => 'إعادة تعيين كلمة مرور {{ store.name }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>تلقّينا طلبًا لإعادة تعيين كلمة مرورك في {{ store.name }}.</p><p><a href="{{ reset.url }}">إعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية الرابط خلال {{ reset.expires_minutes }} دقيقة. إن لم تطلب ذلك فتجاهل هذه الرسالة.</p>',
        ],
        'password_reset_code' => [
            'subject' => 'رمز إعادة التعيين في {{ store.name }}: {{ code.value }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>استخدم هذا الرمز لإعادة تعيين كلمة مرورك في {{ store.name }}:</p><h2>{{ code.value }}</h2><p>تنتهي صلاحية الرمز خلال {{ code.expires_minutes }} دقيقة. إن لم تطلبه فتجاهل هذه الرسالة.</p>',
        ],
        'email_code' => [
            'subject' => 'رمز الدخول إلى {{ store.name }}: {{ code.value }}',
            'body' => '<p>مرحبًا،</p><p>استخدم هذا الرمز لتسجيل الدخول إلى {{ store.name }}:</p><h2>{{ code.value }}</h2><p>تنتهي صلاحية الرمز خلال {{ code.expires_minutes }} دقيقة. إن لم تطلبه فتجاهل هذه الرسالة.</p>',
        ],
        'ticket_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>{{ alert.body }}</p><blockquote>{{ reply.excerpt }}</blockquote><p><a href="{{ ticket.url }}">عرض التذكرة {{ ticket.reference }}</a></p>',
        ],
        'welcome' => [
            'subject' => 'مرحبًا بك في {{ store.name }}',
            'body' => '<h2>أهلًا {{ customer.first_name }}!</h2><p>حسابك في {{ store.name }} جاهز. تابع طلباتك واحفظ عناوينك وأتمم الشراء أسرع في المرة القادمة.</p><p><a href="{{ store.url }}">ابدأ التسوق</a></p>',
        ],
        'back_in_stock' => [
            'subject' => '{{ product.name }} متوفر من جديد',
            'body' => '<h2>عاد من جديد!</h2><p>خبر سار: <strong>{{ product.name }}</strong> متوفر الآن في {{ store.name }}. الكميات قد تكون محدودة، فاطلبه قريبًا.</p><p><a href="{{ product.url }}">تسوّق الآن</a></p><p>تصلك هذه الرسالة مرة واحدة لأنك طلبت إشعارك عند توفره.</p>',
        ],
        'review_published' => [
            'subject' => 'تقييمك لـ {{ product.name }} منشور الآن',
            'body' => '<p>مرحبًا {{ customer.first_name }}،</p><p>شكرًا لك! تقييمك ({{ review.rating }} نجوم) لـ <strong>{{ product.name }}</strong> منشور الآن في {{ store.name }}.</p><p><a href="{{ product.url }}">شاهد تقييمك</a></p>',
        ],
    ],
    'affiliate' => [
        'welcome' => [
            'subject' => 'مرحبًا بك في برنامج التسويق بالعمولة من {{ platform.name }}',
            'body' => '<p>مرحبًا {{ affiliate.name }}،</p><p>شكرًا لانضمامك إلى برنامج التسويق بالعمولة من {{ platform.name }}. سنراجع حسابك قريبًا.</p><p><a href="{{ login.url }}">تسجيل الدخول</a></p>',
        ],
        'payout_sent' => [
            'subject' => 'تم إرسال الدفعة {{ payout.reference }}',
            'body' => '<p>مرحبًا {{ affiliate.name }}،</p><p>أرسلنا دفعتك بقيمة <strong>{{ payout.amount }}</strong> (المرجع {{ payout.reference }}).</p>',
        ],
        'activated' => [
            'subject' => 'تم تفعيل حساب المسوّق في {{ platform.name }}',
            'body' => '<h2>مرحبًا بك في برنامج الشركاء، {{ affiliate.name }}</h2><p>تمت الموافقة على حساب الشريك. شارك رابطك وابدأ بربح العمولة.</p><table><tbody><tr><td>رابطك</td><td>{{ referral.url }}</td></tr><tr><td>رمز الخصم</td><td>{{ affiliate.code }}</td></tr></tbody></table><p><a href="{{ login.url }}">افتح لوحة الشريك</a></p>',
        ],
        'conversion' => [
            'subject' => 'التحويل {{ conversion.status }}: {{ commission.amount }}',
            'body' => '<p>مرحبًا {{ affiliate.name }}،</p><p>أصبحت إحالتك {{ store.name }} ({{ plan.name }}) بحالة <strong>{{ conversion.status }}</strong>. العمولة: {{ commission.amount }}.</p><p>{{ conversion.note }}</p><p><a href="{{ dashboard.url }}">افتح لوحتك</a></p>',
        ],
        'payout' => [
            'subject' => 'الدفعة {{ payout.reference }}: {{ payout.status }}',
            'body' => '<p>مرحبًا {{ affiliate.name }}،</p><p>طلب الدفعة بقيمة <strong>{{ payout.amount }}</strong> عبر {{ payout.method }} حالته {{ payout.status }} (المرجع {{ payout.reference }}).</p><p>{{ payout.note }}</p><p><a href="{{ dashboard.url }}">افتح لوحتك</a></p>',
        ],
        'verify_email' => [
            'subject' => 'أكّد بريدك الإلكتروني',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>يرجى تأكيد بريدك الإلكتروني لتفعيل حساب المسوّق.</p><p><a href="{{ verify.url }}">تأكيد البريد</a></p><p>تنتهي صلاحية الرابط خلال {{ verify.expires_minutes }} دقيقة.</p>',
        ],
        'password_reset' => [
            'subject' => 'إعادة تعيين كلمة مرور حساب المسوّق',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>تلقّينا طلبًا لإعادة تعيين كلمة مرور حساب المسوّق.</p><p><a href="{{ reset.url }}">إعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية الرابط خلال {{ reset.expires_minutes }} دقيقة. إن لم تطلب ذلك فتجاهل هذه الرسالة.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح لوحتك</a></p>',
        ],
    ],
    'admin' => [
        'new_vendor' => [
            'subject' => '{{ alert.title }}',
            'body' => '<h2>متجر جديد</h2><p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">مراجعة المتجر</a></p>',
        ],
        'payment_failed' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الدفعة</a></p>',
        ],
        'password_reset' => [
            'subject' => 'إعادة تعيين كلمة مرور الإدارة في {{ platform.name }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>تلقّينا طلبًا لإعادة تعيين كلمة مرور حساب الإدارة.</p><p><a href="{{ reset.url }}">إعادة تعيين كلمة المرور</a></p><p>تنتهي صلاحية الرابط خلال {{ reset.expires_minutes }} دقيقة. إن لم تطلب ذلك فتواصل مع المشرف العام.</p>',
        ],
        'contact_message' => [
            'subject' => 'نموذج التواصل: {{ contact.subject }}',
            'body' => '<p>رسالة جديدة من <strong>{{ contact.name }}</strong> ({{ contact.email }}، {{ contact.phone }}).</p><p><strong>{{ contact.subject }}</strong></p><blockquote>{{ contact.message }}</blockquote><p>ردّ على هذه الرسالة للإجابة على المرسل.</p>',
        ],
        'alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح في لوحة الإدارة</a></p>',
        ],
        'welcome' => [
            'subject' => '{{ alert.title }}',
            'body' => '<h2>مرحبًا بك في {{ platform.name }}</h2><p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح لوحة الإدارة</a></p>',
        ],
        'password_changed' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p>إن لم تكن أنت، أعد تعيين كلمة المرور الآن وتواصل مع المشرف العام.</p><p><a href="{{ action.url }}">مراجعة حسابك</a></p>',
        ],
        'team_update' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الفريق</a></p>',
        ],
        'order_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'return_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح المرتجعات</a></p>',
        ],
        'request_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الطلب</a></p>',
        ],
        'billing_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح الفواتير</a></p>',
        ],
        'vendor_alert' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">فتح المتجر</a></p>',
        ],
        'shipping_escalation' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح قائمة الشحن</a></p>',
        ],
        'gateway_limit_warning' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح حدود البوابات</a></p>',
        ],
        'gateway_limit_block' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">افتح حدود البوابات</a></p>',
        ],
        'affiliate_payout_request' => [
            'subject' => '{{ alert.title }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>{{ alert.body }}</p><p><a href="{{ action.url }}">راجع طلبات الصرف</a></p>',
        ],
    ],
    'support' => [
        'ticket_reply' => [
            'subject' => '[{{ ticket.reference }}] رد جديد: {{ ticket.subject }}',
            'body' => '<p>مرحبًا {{ recipient.name }}،</p><p>ردّ {{ reply.author }} على تذكرتك <strong>{{ ticket.reference }}</strong> «{{ ticket.subject }}»:</p><blockquote>{{ reply.excerpt }}</blockquote><p><a href="{{ ticket.url }}">عرض المحادثة</a></p>',
        ],
    ],
    'central' => [
        'newsletter_confirmation' => [
            'subject' => 'أكّد اشتراكك في نشرة {{ platform.name }}',
            'body' => '<p>مرحبًا،</p><p>يرجى تأكيد رغبتك في استلام نشرة {{ platform.name }} البريدية.</p><p><a href="{{ confirm.url }}">تأكيد الاشتراك</a></p><p>إن لم تشترك فتجاهل هذه الرسالة. يمكنك <a href="{{ unsubscribe.url }}">إلغاء الاشتراك</a> في أي وقت.</p>',
        ],
    ],
];
