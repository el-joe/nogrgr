<?php

/*
| Vendor help center (VEN-092 / 092a). Each article: title, summary and sections with
| paragraphs (body), numbered steps and an optional tip. Menu paths match the vendor
| sidebar (config/navigation/vendor.php).
*/

return [
    'title' => 'Help center',
    'subtitle' => 'Guides for every part of your store panel.',
    'search_placeholder' => 'Search help articles…',
    'search' => 'Search',
    'topics' => 'Topics',
    'articles_count' => '{0} No articles|{1} :count article|[2,*] :count articles',
    'read_time' => ':minutes min read',
    'results_title' => 'Results for “:term”',
    'results_count' => '{0} No articles found|{1} :count article found|[2,*] :count articles found',
    'no_results_title' => 'Nothing matches “:term”',
    'no_results_description' => 'Try other words, browse the topics or ask our team in a support ticket.',
    'open_ticket' => 'Open a support ticket',
    'related' => 'Related articles',
    'back_to_topic' => 'All :topic articles',
    'still_need_help' => 'Still need help?',
    'still_need_help_text' => 'Our team answers support tickets in your panel, usually within one working day.',
    'feedback' => [
        'question' => 'Was this helpful?',
        'yes' => 'Yes, this helped',
        'no' => 'No, this did not help',
        'thanks' => 'Thanks for your feedback.',
        'voted_yes' => 'You found this helpful.',
        'voted_no' => 'Sorry this did not help — open a ticket and we will answer you directly.',
    ],
    'articles' => [
        'setup-checklist' => [
            'title' => 'Your setup checklist',
            'summary' => 'The Setup page in your sidebar lists the steps that make your store ready to sell, and the badge shows how many are still open.',
            'sections' => [
                [
                    'heading' => 'What the checklist covers',
                    'body' => [
                        'Open Setup from the sidebar. Each step links to the page where you complete it: store details and logo, selling countries, languages and currencies, a theme for your storefront, your first products and a payment gateway.',
                        'A step is ticked automatically as soon as the matching setting is saved, so you never have to mark anything by hand.',
                    ],
                ],
                [
                    'heading' => 'Finishing the essentials first',
                    'steps' => [
                        'Settings › General: store name, logo, contact e-mail and phone.',
                        'Settings › Selling countries: the countries you ship to — prices and themes can differ per country.',
                        'Products › All products: import products from the central catalog.',
                        'Your Store › Themes: pick a theme and a home variant for each country.',
                        'Settings › Payment gateways: connect at least one gateway so shoppers can pay.',
                    ],
                    'tip' => 'The dashboard keeps a "Live store preview" with a country selector, so you can check how each country sees your store while you work through the list.',
                ],
            ],
        ],
        'how-your-store-works' => [
            'title' => 'How your store works with the platform',
            'summary' => 'Your store sells products from the platform\'s central catalog and your own products; the platform handles sourcing and shipping for catalog orders.',
            'sections' => [
                [
                    'heading' => 'Catalog products and own products',
                    'body' => [
                        'Catalog products come from the central catalog. You choose which ones to sell and set your markup; the platform keeps the product data, stock and cost up to date for every store.',
                        'Own products are items you create yourself under Products › Own products. You set their price, stock and countries, and you fulfil those orders yourself.',
                    ],
                ],
                [
                    'heading' => 'Who does what on an order',
                    'body' => [
                        'For catalog products the platform ships the order and records the cost and shipping on its side; your profit is the markup you set. For own products you ship and you keep the full sale price.',
                        'All amounts are stored in US dollars. Shoppers see their own currency as a display conversion, and every payment is charged in USD.',
                    ],
                    'tip' => 'Finance › Settlements shows, order by order, what the platform owes you and what you owe the platform.',
                ],
            ],
        ],
        'import-central-products' => [
            'title' => 'Importing products from the central catalog',
            'summary' => 'Add catalog products to your store in a few clicks and keep them in sync automatically.',
            'sections' => [
                [
                    'heading' => 'Add products',
                    'steps' => [
                        'Go to Products › New products or Today\'s chances to see what is available.',
                        'Use the filters (category, country, price) and select the products you want.',
                        'Click Import: the products appear under Products › All products with your default markup.',
                    ],
                ],
                [
                    'heading' => 'What stays in sync',
                    'body' => [
                        'Names, descriptions, images, variants, cost and stock come from the central catalog and update in your store automatically. You never re-enter them.',
                        'Your own settings — markup, visibility, sort order, store categories and badges — are kept per store and are never overwritten by catalog updates.',
                    ],
                    'tip' => 'If a catalog product needs a correction (wrong photo, missing size), send an edit request instead of creating a duplicate product.',
                ],
            ],
        ],
        'markup-per-country' => [
            'title' => 'How markup per country works',
            'summary' => 'Your shopper price is the central cost plus your markup. You can set a different markup for each country and variant.',
            'sections' => [
                [
                    'heading' => 'The price formula',
                    'body' => [
                        'Selling price = cost + profit. Your profit is a percentage of the cost or a fixed amount. A product with a $10 cost and a 25% markup sells for $12.50.',
                        'Fixed shipping set by the platform for a product is added once per order line, and the shipping charge by weight and zone is added on the order. Flash-sale discounts and coupons are applied on top of that price.',
                    ],
                ],
                [
                    'heading' => 'Setting markup',
                    'steps' => [
                        'Your store default markup applies to every imported product.',
                        'Open a product under Products › All products and use the pricing tab to set a markup per selling country.',
                        'For products with variants, override the markup of a single variant when one size or colour needs a different price.',
                        'Save: the new price shows on the storefront right away for that country.',
                    ],
                    'tip' => 'The AI price suggestion compares similar products and proposes a markup; you always decide before anything is saved.',
                ],
            ],
        ],
        'own-products' => [
            'title' => 'Selling your own products',
            'summary' => 'Create products that are not in the central catalog, set your own price and stock, and fulfil those orders yourself.',
            'sections' => [
                [
                    'heading' => 'Create an own product',
                    'steps' => [
                        'Go to Products › Own products and click Add product.',
                        'Enter the name and description in each of your store languages, add images and variants.',
                        'Set the sale price per variant, the stock under Products › Inventory, and the countries the product is sold in.',
                    ],
                ],
                [
                    'heading' => 'Pricing and shipping rules',
                    'body' => [
                        'Own products have no platform cost: the price you set is your full revenue, and it is the same in every country (shoppers only see a currency conversion).',
                        'Own products ship free on the platform side and are never added to the platform delivery queue — you ship them yourself. When a cart mixes catalog and own products, the order is split so each side is fulfilled correctly.',
                    ],
                    'tip' => 'If you tick only some countries, the product is hidden from visitors in other countries and checkout refuses an address outside that list.',
                ],
            ],
        ],
        'edit-requests' => [
            'title' => 'Asking for a catalog product change',
            'summary' => 'Edit requests let you correct a catalog product for your store without changing it for every other store.',
            'sections' => [
                [
                    'heading' => 'Send an edit request',
                    'steps' => [
                        'Open the product under Products › All products and choose Request edit.',
                        'Change the fields that are wrong (title, description, images) and explain why.',
                        'Track the request under Products › Edit requests: pending, approved or rejected.',
                    ],
                ],
                [
                    'heading' => 'What happens after review',
                    'body' => [
                        'When the platform approves your request, the change is applied to your store only; the central product stays as it was for everyone else.',
                        'You receive a notification for every decision, with the reason when a request is rejected.',
                    ],
                    'tip' => 'Need a product that is not in the catalog at all? Use Requests › Product requests to ask the platform to source it.',
                ],
            ],
        ],
        'order-lifecycle' => [
            'title' => 'Following an order from payment to delivery',
            'summary' => 'Every order moves through clear statuses and you can see each step under Orders › All orders.',
            'sections' => [
                [
                    'heading' => 'Order statuses',
                    'body' => [
                        'A paid order is confirmed, then processed and shipped. The shipping company and tracking number appear on the order once it leaves the warehouse, and the shopper receives an e-mail at each step.',
                        'Orders with own products are fulfilled by you: update their status and tracking yourself from the order page.',
                    ],
                ],
                [
                    'heading' => 'Working with orders',
                    'steps' => [
                        'Use the filters (status, country, date, payment) and the search to find an order.',
                        'Open the order to see items, prices, shipping, payment and the timeline.',
                        'Print the invoice or export the list to CSV when you need it.',
                        'Cancel an order that has not shipped yet; the shopper is refunded through the original payment.',
                    ],
                    'tip' => 'The sidebar badge on Orders shows how many new orders arrived since you last looked.',
                ],
            ],
        ],
        'returns-and-refunds' => [
            'title' => 'Handling returns and refunds',
            'summary' => 'Shoppers request returns from their account; you review them, follow the parcel and refund in one place.',
            'sections' => [
                [
                    'heading' => 'Return requests',
                    'steps' => [
                        'New requests appear under Orders › Returns with the reason and the shopper\'s photos.',
                        'Approve or reject the request and reply to the shopper in the return conversation.',
                        'When the item is received and checked, complete the return and choose the refund amount.',
                    ],
                ],
                [
                    'heading' => 'Refunds and your return policy',
                    'body' => [
                        'Refunds go back to the shopper\'s original payment method and are listed under Orders › Refunds. A manual refund is also available on the order page.',
                        'Your return window and conditions are set in Settings › Return policy and shown to shoppers on your storefront. Orders › Return analytics shows return rates and the most common reasons.',
                    ],
                ],
            ],
        ],
        'themes-and-home-variants' => [
            'title' => 'Choosing a theme',
            'summary' => 'Your storefront can use a different theme for each selling country.',
            'sections' => [
                [
                    'heading' => 'Pick a theme',
                    'steps' => [
                        'Go to Your Store › Themes and preview the available themes.',
                        'Activate a theme for all countries, or choose one per country.',
                        'Under Your Store › Themes by country, check which theme each country shows and switch a country back to your store default.',
                    ],
                ],
                [
                    'heading' => 'Previewing before you publish',
                    'body' => [
                        'Use the live preview on the dashboard and switch the country to see exactly what shoppers there will see — theme, prices, banners and products.',
                        'Themes adapt automatically to Arabic and other right-to-left languages, and to light and dark mode.',
                    ],
                ],
            ],
        ],
        'custom-twig-themes' => [
            'title' => 'Building your own theme with Twig',
            'summary' => 'A developer can build a complete storefront theme with Twig, upload it as a ZIP and publish it after the platform reviews it.',
            'sections' => [
                [
                    'heading' => 'How it works',
                    'steps' => [
                        'Go to Your Store › Custom theme and download the starter theme.',
                        'Change the templates, strings and styles, then zip the folder and upload it.',
                        'Read the validation report, preview the version on your storefront and submit it for review.',
                        'When the platform approves it, publish it. You can roll back to any approved version at any time.',
                    ],
                ],
                [
                    'heading' => 'Safe by design',
                    'body' => [
                        'Themes run in a sandbox: no PHP code, no access to the server, no remote files and no inline scripts. Checkout, payment, sign-in and order pages always use the secure built-in pages.',
                        'If the live theme ever fails to load, shoppers see your built-in theme and you get a notification.',
                    ],
                    'tip' => 'The developer guide lists every template, variable, function and limit, generated from the data your store really sends.',
                    'link' => ['label' => 'Open the developer guide', 'route' => 'store.custom-themes.docs'],
                ],
            ],
        ],
        'pages-banners-appearance' => [
            'title' => 'Pages, banners and appearance',
            'summary' => 'Make the store yours with your logo, colours, fonts, content pages and banners.',
            'sections' => [
                [
                    'heading' => 'Appearance',
                    'body' => [
                        'Your Store › Appearance sets your logo, favicon, brand colours and fonts. You can also generate a logo with AI and keep or discard the result.',
                    ],
                ],
                [
                    'heading' => 'Pages and banners',
                    'steps' => [
                        'Your Store › Pages: create pages such as About us or Shipping information, in each store language.',
                        'Your Store › Banners: upload banners for the home page and choose the countries and dates they show.',
                        'Platform banners appear automatically next to your own; you can see them read-only in the same list.',
                    ],
                    'tip' => 'Banner images are shown whole inside their frame — use the recommended size shown in the upload field for the sharpest result.',
                ],
            ],
        ],
        'coupons' => [
            'title' => 'Creating coupons',
            'summary' => 'Coupons give shoppers a discount at checkout with the rules you choose.',
            'sections' => [
                [
                    'heading' => 'Create a coupon',
                    'steps' => [
                        'Go to Ads & Marketing › Coupons and click Add coupon.',
                        'Enter the code, choose a percentage or a fixed amount, and set the minimum order if needed.',
                        'Limit the coupon by dates, countries, total uses and uses per customer.',
                        'Save: the coupon works immediately on your storefront and mobile app.',
                    ],
                ],
                [
                    'heading' => 'Who pays for the discount',
                    'body' => [
                        'A coupon you create is paid from your profit. Coupons created by the platform are paid by the platform and are listed read-only next to yours.',
                    ],
                ],
            ],
        ],
        'flash-sales' => [
            'title' => 'Running a flash sale',
            'summary' => 'Flash sales discount selected products for a limited time and add a countdown to your storefront.',
            'sections' => [
                [
                    'heading' => 'Set up a flash sale',
                    'steps' => [
                        'Go to Ads & Marketing › Flash sales and click Add flash sale.',
                        'Set the discount percentage, start and end time, and the countries.',
                        'Add the products; the sale starts and ends automatically.',
                    ],
                ],
                [
                    'heading' => 'How the discount is calculated',
                    'body' => [
                        'The flash-sale percentage applies to the line price plus the product\'s fixed shipping. Your own flash sales come out of your profit; platform flash sales are shown read-only and paid by the platform.',
                    ],
                    'tip' => 'Products in an active flash sale show a strikethrough price and a timer on every theme.',
                ],
            ],
        ],
        'subscription-and-invoices' => [
            'title' => 'Your subscription and invoices',
            'summary' => 'Manage your plan, renew or upgrade, and download every invoice from Finance.',
            'sections' => [
                [
                    'heading' => 'Subscription',
                    'steps' => [
                        'Finance › Subscription shows your plan, its limits and the renewal date.',
                        'Choose Change plan to upgrade or switch between monthly and yearly billing; the credit from your current plan is shown before you pay.',
                        'Pay with a saved card or a new one; the new plan is active as soon as the payment succeeds.',
                    ],
                ],
                [
                    'heading' => 'Invoices and purchases',
                    'body' => [
                        'Finance › Invoices lists every platform invoice with its status; open one to print or download it. Finance › Purchases lists add-ons such as extra languages or AI translation.',
                        'If a renewal payment fails you are notified and the invoice stays payable from the same page.',
                    ],
                ],
            ],
        ],
        'settlements-and-payouts' => [
            'title' => 'Settlements and payouts',
            'summary' => 'See what each order earns you and when the platform pays you.',
            'sections' => [
                [
                    'heading' => 'Settlements',
                    'body' => [
                        'Finance › Settlements shows the money split of every order: your profit on catalog products, the full price of own products, and the platform\'s share (cost, fixed shipping, shipping charge and platform discounts).',
                        'Your balance is the total the platform owes you minus anything you owe the platform, for example when you collected an order payment through your own gateway.',
                    ],
                ],
                [
                    'heading' => 'Payouts',
                    'body' => [
                        'Finance › Payouts lists every transfer from the platform with its method, reference and status (scheduled, processing, paid). Keep your bank details up to date in your account settings so payouts are not delayed.',
                    ],
                    'tip' => 'The dashboard shows an alert when you owe the platform money, with a button to pay it.',
                ],
            ],
        ],
        'custom-domain' => [
            'title' => 'Connecting your own domain',
            'summary' => 'Use your own domain name for the storefront and the store panel.',
            'sections' => [
                [
                    'heading' => 'Add the domain',
                    'steps' => [
                        'Go to Settings › Domains and click Add domain.',
                        'Enter the domain (for example shop.example.com).',
                        'Copy the DNS records shown on the page into your domain provider\'s DNS settings.',
                        'Click Check DNS. When the records are found, the domain becomes active and the certificate is issued.',
                    ],
                ],
                [
                    'heading' => 'If the check fails',
                    'body' => [
                        'DNS changes can take up to 48 hours to spread. The page shows which record is missing or wrong; fix it and check again. You are notified when the domain connects or when the check keeps failing.',
                    ],
                    'tip' => 'Your free platform address keeps working, so you can switch without downtime.',
                ],
            ],
        ],
        'staff-and-roles' => [
            'title' => 'Adding staff and roles',
            'summary' => 'Give your team their own logins with exactly the access they need.',
            'sections' => [
                [
                    'heading' => 'Invite staff',
                    'steps' => [
                        'Go to Settings › Staff and click Add staff member.',
                        'Enter the name and e-mail and pick a role; they receive an invitation e-mail to set a password.',
                        'Deactivate a member at any time; their access stops immediately.',
                    ],
                ],
                [
                    'heading' => 'Roles and permissions',
                    'body' => [
                        'Settings › Roles lists the permissions grouped by area (products, orders, store design, finance, settings, support…). "Manage" always includes "View".',
                        'The owner role has every permission and cannot be removed. Settings › Audit log shows who changed what.',
                    ],
                ],
            ],
        ],
        'payment-gateways' => [
            'title' => 'Connecting payment gateways',
            'summary' => 'Choose how shoppers pay: the platform\'s gateways or your own accounts.',
            'sections' => [
                [
                    'heading' => 'Set up a gateway',
                    'steps' => [
                        'Go to Settings › Payment gateways.',
                        'Enable a gateway and enter the credentials from your own merchant account, or use the platform account where it is offered.',
                        'Use test mode to place a test order, then switch to live.',
                    ],
                ],
                [
                    'heading' => 'Good to know',
                    'body' => [
                        'Every payment is charged in US dollars, whatever currency the shopper sees. Payments collected through your own gateway are settled with the platform in Finance › Settlements.',
                        'If a gateway stops appearing at checkout, check that its credentials are still valid — providers sometimes rotate merchant IDs and keys.',
                    ],
                ],
            ],
        ],
        'languages-and-currencies' => [
            'title' => 'Languages, currencies and translation',
            'summary' => 'Sell in more languages and show prices in your shoppers\' currencies.',
            'sections' => [
                [
                    'heading' => 'Languages',
                    'body' => [
                        'Arabic and English are always on. Settings › Languages lists the other languages the platform offers: enable a free one or buy a paid one, then set the default language and the order in the language switcher.',
                        'Every text field has a tab per enabled language. Settings › Translations can translate your store texts with AI for the languages you choose.',
                    ],
                ],
                [
                    'heading' => 'Currencies',
                    'body' => [
                        'Settings › Currencies chooses which currencies shoppers can switch to. Rates update daily; prices are converted for display only and payments stay in USD.',
                    ],
                ],
            ],
        ],
        'support-tickets' => [
            'title' => 'Getting help with support tickets',
            'summary' => 'Open a ticket from Support in your sidebar and follow the conversation live.',
            'sections' => [
                [
                    'heading' => 'Open a ticket',
                    'steps' => [
                        'Go to Support and click New ticket.',
                        'Write a clear subject, pick the category and the priority, and describe the problem.',
                        'Attach screenshots, invoices or files (up to 5, 10 MB each) and send.',
                    ],
                ],
                [
                    'heading' => 'Follow the conversation',
                    'body' => [
                        'Replies appear instantly in the ticket and in your notifications; the person who opened the ticket also gets an e-mail. "Needs your reply" means the team is waiting for information from you.',
                        'Close the ticket when your problem is solved. If it comes back, reopen it so the history stays in one place.',
                    ],
                    'tip' => 'Urgent tickets are for problems that stop sales, such as checkout or payment failures. They are answered first.',
                ],
            ],
        ],
    ],
];
