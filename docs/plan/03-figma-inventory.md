# Figma Inventory — file `2wXSYbP5gQSJHiVQWJJpSf`

Full node trees: `figma-metadata/*.xml`. Desktop = 1440w, mobile = 375/390w. Font: **Outfit** (Arabic: IBM Plex Sans Arabic). No Figma motion/prototype animations — animations are ours to design.

## Theme map (13 storefront themes)
| # | Theme key | Family | Home desktop | Home mobile |
|---|---|---|---|---|
| 1 | ecommet | Ecommet | 1:1002 | 1:2656 (alt "Star" 1:2710) |
| 2 | elora-1 (base) | Elora | 1:15432 | 1:21125 |
| 3–7 | elora-2…6 (Theme #1–#5) | Elora | 1:26559, 1:26978, 1:27464, 1:28050, 1:28992 | 1:24137, 1:24477, 1:24870, 1:25467, 1:25994 |
| 8 | souqify-1 (base) | Souqify | 1:72835 | 1:77852 |
| 9–13 | souqify-2…6 (Theme #1–#5) | Souqify | 1:81585, 1:82288, 1:82796, 1:83157, 1:83936 | 1:78202, 1:78599, 1:78957, 1:79266, 1:79691 |
| extra | souqify alt home / Christmas home | Souqify | 1:73212 / 1:73614 | — |

## Shared inner pages per family
- **Ecommet**: components web 1:348 / mobile 1:2764; best selling 1:1191; category 1:1693 (m 1:2979), filter 1:1796; product 1:3380 (m 1:3073); cart 1:1240, 1:1348 (m 1:3329, 1:3210), mini-cart 1:1662, item-added 1:4267; checkout 1:1520 (m 1:3540); profile 1:1833; orders all/pending/processing/shipped/delivered/canceled 1:1871, 1:2353, 1:2384, 1:4110, 1:2518, 1:2615; order details 1:2241, 1:2126, 1:2014; popup 1:2646; search 1:1163; mobile account 1:3848–1:4226.
- **Elora**: listing 1:17172 (m 1:21453), product 1:16245 (m 1:21580), cart 1:17541 (m 1:22029, empty 1:23315), checkout 1:18245 (m 1:22186), favorites 1:18588 (m 1:23220/1:23277), orders 1:18739, pending 1:19347 (m 1:23374/1:23542), order details 1:18987 (m 1:23600), tracking 1:19981 (m 1:23708, live 1:20213), address modal 1:20817 (m 1:22912, select 1:22988), filter m 1:22799, sort m 1:23024, delivery popup m 1:23053, privacy popup m 1:23152, success m 1:23007, navbar 1:20866. Featured marquee comp 1:29865; card-fan slider 1:30232; color swatches 1:26485–1:26557.
- **Souqify**: category 1:75532 (m 1:76311), product 1:74583 (m 1:77427), cart 1:75039 (m 1:76392), checkout 1:75299 (m 1:76569), favorites 1:75718 (m 1:76864), orders 1:75734/1:75862 (m 1:77130), orders empty 1:76132 (m 1:77298), tracking 1:75976 (m 1:80856, live 1:80997), address 1:76266 (m 1:80427/1:80503), success m 1:80522, order details m 1:80745, offers m 1:77354. Components: navbar 1:81293, footer 1:81428, mobile nav 1:80539, product cards 1:81148/1:81011, side card 1:74476/1:80047, deal card 1:80147. Swatches 1:81511–1:81583.

## Central website (page 12:41054)
Home 12:41421 (m 12:42786) · Templates 12:41985 (m 12:43584) · Plans 12:42011 (m 12:43222) · How it works 12:41810 (m 12:43441) · Category 12:42161 (m 12:43373) · Sign up 12:42223 (m 12:43467) · Login 12:42268 (m 12:43512) · Loading 12:42305 · Contact 12:41842 · About 12:41879 · Terms 12:41891 · FAQs 12:41906. Navbar 12:42345, footer 12:42512.
**Alternative landing** "NO GRGR" on Tenant Panel page: 12:25861 (m 12:26195) — different style; client to confirm which is current.
Register flow pages missing in Figma (to take from old project): email verification, plan choice/checkout, payment success/fail, onboarding wizard (countries → languages → theme → launch), store-not-launched, forgot/reset password.

## Tenant (vendor) panel (page 12:10147)
Dashboard 12:11267 · Order analytics 12:16223 · CLV analytics 12:16815 · Notifications 12:17282 · Products 12:12968 · Own products 12:13462 / inner 12:13819 / inventory 12:17880 · Add product 12:17411 · Categories 12:14144 / inner 12:14465 / add 12:17672 · Orders 12:15191 · Customers 12:15544 · Requests 12:15910 / add 12:16096 · Today's chances 12:12050 · Side menu 12:25829 · KPI card 12:40706 · mobile frames 12:18171…12:24527.
Screens with no design (settings, finance, store design, returns, coupons…) are built from this design language by UI/UX first.

## Tokens
| Surface | Primary | Text/dark | Others |
|---|---|---|---|
| Central website | #FF522C | #41251E | gray #717171, subtitle #707070, placeholder #FFF2EF |
| Vendor panel | #FD6F04 (50 #FFF1E6) | #0B0B0B / sub #363636 | neutrals #F5F5F5…#0B0B0B, success #03AA00, spacing 2/4/8/12/16/20/24/32/40 |
| Ecommet | — | #242424 | green #2AAF2F, red #DE1709, yellow #FFB00A, stroke #E8E8E8 |
| Elora | #FF4D00 | #121212 | bg #FDFDFD, subtitle #ADADAD, stroke #D4D4D4, shadow 0 4 7 #0000001A |
| Souqify | #0159ED | #001537 | orange #FF570F, bg #F1F1F1 |
Per-variant colors (Theme #1–#5) are hardcoded on frames → extract with get_design_context. 36-swatch vendor brand palette defined on both Elora & Souqify boards.

## Motion / sliders implied by design
Hero carousels (dots/arrows) · Featured marquee strip · card-fan/coverflow best-seller (→ SpotlightCarousel center/left per `home-pages-custom-sliders.md`) · flash-sale countdown · related-products slider · central "For scrolling" auto-scroll strips (12:41475, 12:42000) · Lottie files on panel empty/loading states · snow effect on Christmas home.

## Gaps → UI/UX tasks
1. Admin panel layout + components (none exist) — derive from Central Website language.
2. Missing vendor-panel screens.
3. **Native mobile app designs for 13 themes** (only mobile-web exists; Elora/Souqify mobile flows are a good base).
4. Souqify footers show the Elora logo in Figma — **do not edit the Figma** (client rule: existing designs stay as is). In code, the storefront footer uses the tenant's own logo, so this has no effect on the build.
