import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/models/product_card.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/data/session_controller.dart';
import '../../bootstrap/data/startup_controller.dart';
import '../../notifications/data/notifications_repository.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';

/// First name of the signed-in customer for the greeting header.
final sessionCustomerNameProvider = Provider<String?>((ref) {
  final name = ref.watch(sessionProvider.select((s) => s.customer?.name));

  return name == null || name.trim().isEmpty ? null : name.trim().split(RegExp(r'\s+')).first;
});

final homeProvider = FutureProvider<HomeData>((ref) {
  // Prices / sections follow the shopping country, language and display currency.
  ref.watch(localeCodeProvider);
  ref.watch(preferencesProvider.select((p) => (p.currency, p.country)));

  return ref.watch(catalogRepositoryProvider).home();
});

/// Ecommet has no category rail in `home`; it reads the tree.
final homeCategoriesProvider = FutureProvider<List<Category>>((ref) {
  ref.watch(localeCodeProvider);

  return ref.watch(catalogRepositoryProvider).categories();
});

enum HomeHeader { logo, greet, menu }

enum HomeTop { banner, duo, carousel }

/// Section order of one Figma home frame (APP-004 … APP-004l).
class HomeSkin {
  const HomeSkin({required this.header, required this.top, this.usp = false, this.categoryTiles = false, this.midBanner = false, this.railBeforeCategories = false});

  final HomeHeader header;
  final HomeTop top;

  /// "USP strip" under the carousel (free shipping / support).
  final bool usp;

  /// "Shop by category" 2×2 tiles (Elora v2 / v4, Souqify v2 / v4 / v5).
  final bool categoryTiles;

  /// "Promo banner" between rails (Elora v3 / v5 / v6, Souqify v1 / v3).
  final bool midBanner;

  /// Souqify v1 opens with a product rail before the category rail.
  final bool railBeforeCategories;

  static HomeSkin of(String family, int variant) => switch ((family, variant)) {
        ('ecommet', _) => const HomeSkin(header: HomeHeader.logo, top: HomeTop.banner),
        ('elora', 1) => const HomeSkin(header: HomeHeader.greet, top: HomeTop.duo),
        ('elora', 2) => const HomeSkin(header: HomeHeader.menu, top: HomeTop.carousel, usp: true, categoryTiles: true),
        ('elora', 3) => const HomeSkin(header: HomeHeader.menu, top: HomeTop.carousel, midBanner: true),
        ('elora', 4) => const HomeSkin(header: HomeHeader.greet, top: HomeTop.carousel, usp: true, categoryTiles: true),
        ('elora', 5) => const HomeSkin(header: HomeHeader.menu, top: HomeTop.carousel, midBanner: true),
        ('elora', _) => const HomeSkin(header: HomeHeader.greet, top: HomeTop.carousel, midBanner: true),
        ('souqify', 1) => const HomeSkin(header: HomeHeader.logo, top: HomeTop.carousel, midBanner: true, railBeforeCategories: true),
        ('souqify', 2) => const HomeSkin(header: HomeHeader.logo, top: HomeTop.carousel, usp: true, categoryTiles: true),
        ('souqify', 3) => const HomeSkin(header: HomeHeader.logo, top: HomeTop.carousel, midBanner: true),
        ('souqify', 4) || ('souqify', 5) => const HomeSkin(header: HomeHeader.greet, top: HomeTop.carousel, usp: true, categoryTiles: true),
        ('souqify', _) => const HomeSkin(header: HomeHeader.logo, top: HomeTop.carousel, categoryTiles: true),
        _ => const HomeSkin(header: HomeHeader.greet, top: HomeTop.duo),
      };
}

/// Home tab (APP-004 family / variant skins, Figma 145:102280 + 146:106404): header with
/// search + camera, banners, category rail, flash sale with countdown, product rails
/// (swipe / drag, snap), grids; pull to refresh, skeleton, error, empty.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final texts = ref.watch(textsProvider);
    final home = ref.watch(homeProvider);
    final bootstrap = ref.watch(startupProvider.select((s) => s.bootstrap));
    final family = home.value?.family ?? bootstrap?.branding.family ?? 'elora';
    final variant = home.value?.variant ?? bootstrap?.branding.variant ?? 1;
    final skin = HomeSkin.of(family, variant);

    Future<void> refresh() async {
      ref.invalidate(homeProvider);
      ref.invalidate(homeCategoriesProvider);
      await ref.read(homeProvider.future);
    }

    final slivers = switch (home) {
      AsyncData(:final value) when value.isEmpty => [
          SliverFillRemaining(
            hasScrollBody: false,
            child: AsyncStateView.empty(title: texts.t('catalog.home.empty_title'), body: texts.t('catalog.home.empty_body')),
          ),
        ],
      AsyncData(:final value) => _sections(context, ref, value, skin),
      AsyncError(:final error) => [SliverFillRemaining(hasScrollBody: false, child: AsyncStateView.error(error: error, onRetry: () => ref.invalidate(homeProvider)))],
      _ => [const SliverToBoxAdapter(child: _HomeSkeleton())],
    };

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: refresh,
        child: CustomScrollView(
          key: const ValueKey('home-scroll'),
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(child: _HomeHeader(skin: skin, storeName: bootstrap?.storeName ?? '', logoUrl: bootstrap?.storeLogoUrl)),
            ...slivers,
            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }

  List<Widget> _sections(BuildContext context, WidgetRef ref, HomeData data, HomeSkin skin) {
    final texts = ref.watch(textsProvider);
    final categories = data.categories.isNotEmpty ? data.categories : (ref.watch(homeCategoriesProvider).value ?? const <Category>[]);
    final flashCards = data.offers.isNotEmpty ? data.offers : data.deals;
    final newCards = data.newRail.isNotEmpty ? data.newRail : data.newIn;
    final grid = data.recommended.isNotEmpty ? data.recommended : data.bestSelling;
    final out = <Widget>[];
    void box(Widget child) => out.add(SliverToBoxAdapter(child: child));
    void rail(String title, List<ProductCard> cards, {VoidCallback? seeAll, Widget? trailing}) {
      if (cards.isEmpty) {
        return;
      }

      box(SectionHeader(title: title, onSeeAll: seeAll, trailing: trailing));
      box(ProductRail(products: cards));
    }

    void list(String kind, String titleKey) => context.push(Routes.listing(kind: kind, title: texts.t(titleKey)));

    // Top banners.
    final banners = data.hero.isNotEmpty ? data.hero : data.promo;

    if (banners.isNotEmpty) {
      box(Padding(
        padding: const EdgeInsets.only(top: 12),
        child: switch (skin.top) {
          HomeTop.banner => _BannerImage(banner: banners.first, height: 73),
          HomeTop.duo => _BannerDuo(banners: banners.take(2).toList()),
          HomeTop.carousel => _HeroCarousel(banners: banners),
        },
      ));
    }

    if (skin.usp) {
      box(const _UspStrip());
    }

    if (skin.railBeforeCategories) {
      rail(texts.t('catalog.home.best_selling'), data.bestSelling, seeAll: () => list('best_selling', 'catalog.listing.best_selling.title'));
    }

    if (categories.isNotEmpty) {
      box(SectionHeader(title: texts.t('search.categories'), onSeeAll: () => StatefulNavigationShell.maybeOf(context)?.goBranch(1)));
      box(SizedBox(
        height: 88,
        child: ScrollConfiguration(
          behavior: const DragScrollBehavior(),
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 16),
            physics: const SnapScrollPhysics(itemExtent: 80, parent: BouncingScrollPhysics()),
            itemCount: categories.length,
            separatorBuilder: (_, _) => const SizedBox(width: 12),
            itemBuilder: (_, i) => CategoryBubble(
              name: categories[i].name,
              image: categories[i].image,
              onTap: () => context.push(Routes.listing(category: categories[i].slug, title: categories[i].name)),
            ),
          ),
        ),
      ));
    }

    // Flash sale / lightning deals with the countdown.
    final flash = data.flash;
    rail(
      flash != null && flash.title.isNotEmpty ? flash.title : texts.t(data.family == 'ecommet' ? 'catalog.home.lightning_deals' : 'catalog.home.flash_now'),
      flashCards,
      seeAll: () => list('offers', 'catalog.listing.offers.title'),
      trailing: flash != null && flash.endsAt.isAfter(DateTime.now()) ? Countdown(endsAt: flash.endsAt, onDone: () => ref.invalidate(homeProvider)) : null,
    );

    if (data.family == 'ecommet') {
      rail(texts.t('catalog.home.clearance_deals'), data.clearance);
    } else {
      rail(texts.t('catalog.listing.new_in.title'), newCards, seeAll: () => list('new_in', 'catalog.listing.new_in.title'));
      rail(texts.t('app.home.trending'), data.trending, seeAll: () => list('best_selling', 'catalog.listing.best_selling.title'));
    }

    if (skin.categoryTiles && categories.length >= 2) {
      box(SectionHeader(title: texts.t('app.home.shop_by_category'), onSeeAll: () => StatefulNavigationShell.maybeOf(context)?.goBranch(1)));
      box(_CategoryTiles(categories: categories.take(4).toList()));
    }

    if (skin.midBanner && data.promo.isNotEmpty) {
      box(Padding(padding: const EdgeInsets.only(top: 20), child: _BannerImage(banner: data.promo.last, height: 120)));
    }

    if (data.family != 'ecommet') {
      if (!skin.railBeforeCategories) {
        rail(texts.t('catalog.home.best_selling'), data.bestSelling, seeAll: () => list('best_selling', 'catalog.listing.best_selling.title'));
      }

      rail(texts.t('catalog.listing.top_rated.title'), data.topRatedRail.isNotEmpty ? data.topRatedRail : data.topRated, seeAll: () => list('top_rated', 'catalog.listing.top_rated.title'));

      for (final shelf in data.badges) {
        rail(shelf.name, shelf.items);
      }
    }

    if (grid.isNotEmpty) {
      box(SectionHeader(
        title: texts.t(data.recommended.isNotEmpty ? 'app.home.recommended_for_you' : 'catalog.home.best_selling'),
        onSeeAll: () => list('best_selling', 'catalog.listing.best_selling.title'),
      ));
      out.add(ProductGridSliver(products: grid.take(8).toList()));
    }

    return out;
  }
}

class _HomeHeader extends ConsumerWidget {
  const _HomeHeader({required this.skin, required this.storeName, this.logoUrl});

  final HomeSkin skin;
  final String storeName;
  final String? logoUrl;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final text = Theme.of(context).textTheme;
    final texts = ref.watch(textsProvider);
    final customer = ref.watch(sessionCustomerNameProvider);
    final unread = ref.watch(unreadCountProvider).value ?? 0;
    // Ecommet: the whole header sits on the brand colour (Figma 145:99799).
    final onBrand = skin.header == HomeHeader.logo && ref.watch(startupProvider.select((s) => s.bootstrap?.branding.family)) == 'ecommet';
    final fg = onBrand ? colors.onPrimary : colors.ink;
    final top = MediaQuery.paddingOf(context).top;

    final title = switch (skin.header) {
      HomeHeader.greet => Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(texts.t('app.home.hello', {'name': customer ?? texts.t('app.home.guest')}), style: text.bodyMedium?.copyWith(color: colors.muted)),
            Text(storeName, style: text.titleLarge?.copyWith(color: fg), maxLines: 1, overflow: TextOverflow.ellipsis),
          ],
        ),
      _ => Row(
          children: [
            if (skin.header == HomeHeader.menu) ...[
              InkResponse(
                onTap: () => StatefulNavigationShell.maybeOf(context)?.goBranch(1),
                child: Semantics(label: texts.t('search.categories'), button: true, child: Icon(Icons.menu_rounded, color: fg)),
              ),
              const SizedBox(width: 12),
            ],
            Flexible(
              child: Text(
                storeName.toUpperCase() == storeName ? storeName : storeName,
                style: text.headlineSmall?.copyWith(color: onBrand ? fg : colors.primary, fontWeight: FontWeight.w700),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
    };

    return Container(
      color: onBrand ? colors.primary : colors.bg,
      padding: EdgeInsets.fromLTRB(16, top + 12, 16, 12),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: title),
              IconButton(
                key: const ValueKey('home-inbox'),
                tooltip: texts.t('app.inbox.title'),
                onPressed: () => context.push(Routes.notifications),
                icon: Badge(isLabelVisible: unread > 0, label: Text('$unread'), child: AppIcon('bell', color: fg)),
              ),
              IconButton(
                tooltip: texts.t('cart.title'),
                onPressed: () => StatefulNavigationShell.maybeOf(context)?.goBranch(2),
                icon: AppIcon('cart', color: fg),
              ),
            ],
          ),
          const SizedBox(height: 12),
          SearchEntry(onBrand: onBrand),
        ],
      ),
    );
  }
}

/// "Search row": tappable field opening search, camera icon opening image search.
class SearchEntry extends ConsumerWidget {
  const SearchEntry({super.key, this.onBrand = false});

  final bool onBrand;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final texts = ref.watch(textsProvider);

    return Material(
      color: onBrand ? colors.surface : colors.primarySubtle.withValues(alpha: 0.6),
      borderRadius: BorderRadius.circular(AppRadius.input + 2),
      child: InkWell(
        key: const ValueKey('home-search'),
        borderRadius: BorderRadius.circular(AppRadius.input + 2),
        onTap: () => context.push(Routes.search),
        child: SizedBox(
          height: 44,
          child: Row(
            children: [
              const SizedBox(width: 12),
              AppIcon('search', size: 20, color: colors.muted),
              const SizedBox(width: 8),
              Expanded(child: Text(texts.t('app.search.placeholder'), style: Theme.of(context).textTheme.bodyLarge?.copyWith(color: colors.muted))),
              IconButton(
                key: const ValueKey('home-image-search'),
                tooltip: texts.t('search.image.open'),
                onPressed: () => context.push(Routes.imageSearch),
                icon: AppIcon('camera', size: 20, color: colors.primary),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Opens a banner link: product / category / list paths of the store, else the browser.
void openBannerLink(BuildContext context, String? url) {
  if (url == null || url.isEmpty) {
    return;
  }

  final uri = Uri.tryParse(url);
  final segments = uri?.pathSegments.where((s) => s.isNotEmpty).toList() ?? const [];

  if (segments.length >= 2 && (segments.first == 'products' || segments.first == 'p')) {
    final id = int.tryParse(segments[1].split('-').first);

    if (id != null) {
      context.push(Routes.product(id));

      return;
    }
  }

  if (segments.length >= 2 && (segments.first == 'categories' || segments.first == 'category' || segments.first == 'c')) {
    context.push(Routes.listing(category: segments[1]));

    return;
  }

  context.push(Routes.listing(kind: 'offers'));
}

class _BannerImage extends StatelessWidget {
  const _BannerImage({required this.banner, required this.height});

  final HomeBanner banner;
  final double height;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Semantics(
        button: true,
        label: banner.title ?? banner.cta,
        child: InkWell(
          onTap: () => openBannerLink(context, banner.url),
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: SizedBox(height: height, child: NetImage(banner.image, icon: 'store')),
        ),
      ),
    );
  }
}

class _BannerDuo extends StatelessWidget {
  const _BannerDuo({required this.banners});

  final List<HomeBanner> banners;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          for (var i = 0; i < banners.length; i++) ...[
            if (i > 0) const SizedBox(width: 12),
            Expanded(
              child: InkWell(
                onTap: () => openBannerLink(context, banners[i].url),
                borderRadius: BorderRadius.circular(AppRadius.card),
                child: SizedBox(height: 106, child: NetImage(banners[i].image)),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// "Hero carousel (auto 5 s, swipe; reduced motion = no autoplay)" + dots.
class _HeroCarousel extends StatefulWidget {
  const _HeroCarousel({required this.banners});

  final List<HomeBanner> banners;

  @override
  State<_HeroCarousel> createState() => _HeroCarouselState();
}

class _HeroCarouselState extends State<_HeroCarousel> {
  final _controller = PageController();
  Timer? _timer;
  int _index = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _timer?.cancel();

    if (!MediaQuery.disableAnimationsOf(context) && widget.banners.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (_controller.hasClients) {
          _controller.animateToPage((_index + 1) % widget.banners.length, duration: const Duration(milliseconds: 450), curve: Curves.easeInOutCubic);
        }
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Column(
      children: [
        SizedBox(
          height: 180,
          child: ScrollConfiguration(
            behavior: const DragScrollBehavior(),
            child: PageView.builder(
              controller: _controller,
              itemCount: widget.banners.length,
              onPageChanged: (index) => setState(() => _index = index),
              itemBuilder: (_, index) => _BannerImage(banner: widget.banners[index], height: 180),
            ),
          ),
        ),
        if (widget.banners.length > 1) ...[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 0; i < widget.banners.length; i++)
                AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  width: i == _index ? 18 : 6,
                  height: 6,
                  decoration: BoxDecoration(color: i == _index ? colors.primary : colors.border, borderRadius: BorderRadius.circular(3)),
                ),
            ],
          ),
        ],
      ],
    );
  }
}

class _UspStrip extends ConsumerWidget {
  const _UspStrip();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final colors = context.colors;
    final texts = ref.watch(textsProvider);
    final style = Theme.of(context).textTheme.labelSmall?.copyWith(fontWeight: FontWeight.w400);

    Widget chip(IconData icon, String label) => Container(
          height: 32,
          padding: const EdgeInsets.symmetric(horizontal: 10),
          decoration: BoxDecoration(border: Border.all(color: colors.border), borderRadius: BorderRadius.circular(6)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [Icon(icon, size: 16, color: colors.primary), const SizedBox(width: 6), Text(label, style: style)]),
        );

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          chip(Icons.local_shipping_outlined, texts.t('cart.free_shipping_banner')),
          chip(Icons.support_agent_outlined, texts.t('app.home.support')),
        ],
      ),
    );
  }
}

class _CategoryTiles extends StatelessWidget {
  const _CategoryTiles({required this.categories});

  final List<Category> categories;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 11,
        crossAxisSpacing: 11,
        childAspectRatio: 164 / 110,
        children: [
          for (final category in categories)
            InkWell(
              onTap: () => context.push(Routes.listing(category: category.slug, title: category.name)),
              borderRadius: BorderRadius.circular(AppRadius.card),
              child: Stack(
                children: [
                  Positioned.fill(child: NetImage(category.image, icon: 'grid')),
                  PositionedDirectional(
                    start: 8,
                    bottom: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(color: colors.surface, borderRadius: BorderRadius.circular(4)),
                      child: Text(category.name, style: Theme.of(context).textTheme.labelSmall),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _HomeSkeleton extends StatelessWidget {
  const _HomeSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SkeletonBox(height: 160),
          SizedBox(height: 20),
          SkeletonBox(height: 18, width: 140, radius: 4),
          SizedBox(height: 12),
          Row(children: [SkeletonBox(width: 64, height: 64, radius: 32), SizedBox(width: 12), SkeletonBox(width: 64, height: 64, radius: 32), SizedBox(width: 12), SkeletonBox(width: 64, height: 64, radius: 32)]),
          SizedBox(height: 20),
          Row(children: [Expanded(child: SkeletonBox(height: 220)), SizedBox(width: 12), Expanded(child: SkeletonBox(height: 220))]),
        ],
      ),
    );
  }
}
