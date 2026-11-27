import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../app/router.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../../core/widgets/paged_list.dart';
import '../../../core/widgets/select_field.dart';
import '../../auth/data/session_controller.dart';
import '../../auth/presentation/auth_form.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';
import 'product_screen.dart';

/// Reviews list + write review (APP-010a): summary bars, star filter, paged list,
/// eligibility (signed in + delivered order), moderation notice.
class ReviewsScreen extends ConsumerStatefulWidget {
  const ReviewsScreen({super.key, required this.productId});

  final int productId;

  @override
  ConsumerState<ReviewsScreen> createState() => _ReviewsScreenState();
}

class _ReviewsScreenState extends ConsumerState<ReviewsScreen> {
  ReviewPage? _first;
  final List<Review> _items = [];
  int _page = 1;
  bool _hasMore = false;
  bool _loading = true;
  bool _loadingMore = false;
  Object? _error;
  int? _rating;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final page = await ref.read(catalogRepositoryProvider).reviews(widget.productId, rating: _rating);

      if (!mounted) {
        return;
      }

      setState(() {
        _first = page;
        _items
          ..clear()
          ..addAll(page.items);
        _page = 1;
        _hasMore = page.meta.hasMore;
      });
    } catch (error) {
      if (mounted) {
        setState(() => _error = error);
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _more() async {
    if (!_hasMore || _loadingMore) {
      return;
    }

    setState(() => _loadingMore = true);

    try {
      final page = await ref.read(catalogRepositoryProvider).reviews(widget.productId, page: _page + 1, rating: _rating);

      if (mounted) {
        setState(() {
          _items.addAll(page.items);
          _page++;
          _hasMore = page.meta.hasMore;
        });
      }
    } finally {
      if (mounted) {
        setState(() => _loadingMore = false);
      }
    }
  }

  Future<void> _write() async {
    final texts = ref.read(textsProvider);

    if (!ref.read(sessionProvider).signedIn) {
      context.push(Routes.login);

      return;
    }

    if (_first != null && !_first!.canWrite) {
      final reason = _first!.blockReason;
      showAppMessage(context, texts.t(reason == null ? 'reviews.errors.not_purchased' : 'reviews.errors.$reason'));

      return;
    }

    final message = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _WriteReviewSheet(productId: widget.productId),
    );

    if (message != null && mounted) {
      showAppMessage(context, message.isEmpty ? texts.t('reviews.messages.pending') : message);
      _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final text = Theme.of(context).textTheme;
    final first = _first;

    Widget body;

    if (_loading && first == null) {
      body = const ListSkeleton();
    } else if (_error != null && first == null) {
      body = AsyncStateView.error(error: _error!, onRetry: _load);
    } else {
      body = PagedScroll(
        onRefresh: _load,
        onLoadMore: _more,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            if (first != null && first.summary.count > 0)
              Row(
                children: [
                  Column(children: [
                    Text(first.summary.average.toStringAsFixed(1), style: text.headlineSmall?.copyWith(fontSize: 32)),
                    Text(texts.t('product.reviews.count', {'count': first.summary.count}), style: text.bodyMedium),
                  ]),
                  const SizedBox(width: 16),
                  Expanded(child: RatingBars(summary: first.summary)),
                ],
              ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AppChip(label: texts.t('product.reviews.all'), selected: _rating == null, onTap: () => setState(() {
                      _rating = null;
                      _load();
                    })),
                for (final stars in [5, 4, 3, 2, 1])
                  AppChip(label: '$stars★', selected: _rating == stars, onTap: () => setState(() {
                        _rating = stars;
                        _load();
                      })),
              ],
            ),
            const SizedBox(height: 16),
            if (_items.isEmpty)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 32),
                child: Text(texts.t(_rating == null ? 'product.reviews.none' : 'product.reviews.empty_filtered'), style: text.bodyLarge, textAlign: TextAlign.center),
              ),
            for (final review in _items) _ReviewTile(review: review),
            if (_loadingMore) const LoadingMore(),
          ],
        ),
      );
    }

    return PageScaffold(
      title: texts.t('product.reviews.title'),
      body: body,
      bottom: AppButton(key: const ValueKey('write-review'), label: texts.t('product.reviews.write'), onPressed: _write),
    );
  }
}

class _ReviewTile extends ConsumerWidget {
  const _ReviewTile({required this.review});

  final Review review;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final text = Theme.of(context).textTheme;
    final colors = context.colors;
    final texts = ref.watch(textsProvider);

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(border: Border(bottom: BorderSide(color: colors.border))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(child: Text(review.author, style: text.labelMedium)),
              if (review.createdHuman != null) Text(review.createdHuman!, style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400)),
            ],
          ),
          const SizedBox(height: 4),
          Row(
            children: [
              for (var i = 1; i <= 5; i++) Icon(i <= review.rating ? Icons.star_rounded : Icons.star_outline_rounded, size: 16, color: const Color(0xFFF5A623)),
              if (review.verified) ...[const SizedBox(width: 8), StatusPill(label: texts.t('product.reviews.verified'), tone: StateTone.info)],
            ],
          ),
          if (review.title != null) ...[const SizedBox(height: 6), Text(review.title!, style: text.bodyLarge?.copyWith(fontWeight: FontWeight.w600))],
          const SizedBox(height: 4),
          Text(review.body, style: text.bodyLarge),
        ],
      ),
    );
  }
}

class _WriteReviewSheet extends ConsumerStatefulWidget {
  const _WriteReviewSheet({required this.productId});

  final int productId;

  @override
  ConsumerState<_WriteReviewSheet> createState() => _WriteReviewSheetState();
}

class _WriteReviewSheetState extends ConsumerState<_WriteReviewSheet> {
  int _rating = 0;
  final _title = TextEditingController();
  final _body = TextEditingController();
  Map<String, String?> _errors = const {};
  bool _busy = false;

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final texts = ref.read(textsProvider);

    if (_rating == 0) {
      setState(() => _errors = {'rating': texts.t('orders.rating_required')});

      return;
    }

    setState(() {
      _busy = true;
      _errors = const {};
    });

    try {
      final message = await ref.read(catalogRepositoryProvider).writeReview(
            widget.productId,
            rating: _rating,
            title: _title.text.trim().isEmpty ? null : _title.text.trim(),
            body: _body.text.trim(),
          );

      if (mounted) {
        Navigator.of(context).pop(message);
      }
    } on ApiException catch (error) {
      setState(() => _errors = {
            'rating': error.fieldError('rating'),
            'title': error.fieldError('title'),
            'body': error.fieldError('body') ?? (error.errors.isEmpty ? apiErrorMessage(texts, error) : null),
          });
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;

    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 16 + MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(texts.t('product.reviews.write'), style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text(texts.t('product.reviews.your_rating'), style: Theme.of(context).textTheme.labelMedium),
            Row(
              children: [
                for (var i = 1; i <= 5; i++)
                  IconButton(
                    key: ValueKey('review-star-$i'),
                    tooltip: texts.t('product.reviews.stars', {'count': i}),
                    onPressed: () => setState(() => _rating = i),
                    icon: Icon(i <= _rating ? Icons.star_rounded : Icons.star_outline_rounded, color: const Color(0xFFF5A623), size: 32),
                  ),
              ],
            ),
            if (_errors['rating'] != null) Text(_errors['rating']!, style: TextStyle(color: colors.danger, fontSize: 11)),
            const SizedBox(height: 8),
            AppTextField(label: texts.t('product.reviews.title_label'), controller: _title, error: _errors['title']),
            AppTextField(
              fieldKey: const ValueKey('review-body'),
              label: texts.t('product.reviews.your_review'),
              controller: _body,
              error: _errors['body'],
              keyboardType: TextInputType.multiline,
              textInputAction: TextInputAction.newline,
            ),
            Text(texts.t('product.reviews.moderation'), style: Theme.of(context).textTheme.bodyMedium),
            const SizedBox(height: 12),
            AppButton(key: const ValueKey('review-submit'), label: texts.t('product.reviews.submit'), loading: _busy, onPressed: _submit),
          ],
        ),
      ),
    );
  }
}
