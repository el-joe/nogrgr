import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../../auth/presentation/auth_form.dart';
import '../data/catalog_models.dart';
import '../data/catalog_repository.dart';

/// Picks a photo (camera / gallery); overridable in tests.
final imagePickerProvider = Provider<Future<String?> Function(ImageSource source)>((ref) {
  return (source) async => (await ImagePicker().pickImage(source: source, maxWidth: 1600, imageQuality: 85))?.path;
});

/// Image search (APP-009, `POST store-api/v1/search/image`): camera or gallery photo →
/// similar products with a match %; quota / unavailable / empty states.
class ImageSearchScreen extends ConsumerStatefulWidget {
  const ImageSearchScreen({super.key});

  @override
  ConsumerState<ImageSearchScreen> createState() => _ImageSearchScreenState();
}

class _ImageSearchScreenState extends ConsumerState<ImageSearchScreen> {
  String? _path;
  bool _busy = false;
  ImageSearchResult? _result;
  String? _error;

  Future<void> _pick(ImageSource source) async {
    final path = await ref.read(imagePickerProvider)(source);

    if (path == null || !mounted) {
      return;
    }

    final texts = ref.read(textsProvider);
    final size = await File(path).length().catchError((_) => 0);

    if (size > 10 * 1024 * 1024) {
      setState(() => _error = texts.t('search.image.errors.size'));

      return;
    }

    setState(() {
      _path = path;
      _busy = true;
      _error = null;
      _result = null;
    });

    try {
      final result = await ref.read(catalogRepositoryProvider).imageSearch(path);

      if (mounted) {
        setState(() => _result = result);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message.isNotEmpty ? error.message : apiErrorMessage(texts, error));
      }
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
    final text = Theme.of(context).textTheme;
    final result = _result;

    final picker = Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: 180,
          decoration: BoxDecoration(color: colors.primarySubtle, borderRadius: BorderRadius.circular(AppRadius.card)),
          child: _path == null
              ? Center(child: AppIcon('camera', size: 48, color: colors.primary))
              : ClipRRect(borderRadius: BorderRadius.circular(AppRadius.card), child: Image.file(File(_path!), fit: BoxFit.contain)),
        ),
        const SizedBox(height: 12),
        Text(texts.t('search.image.hint'), style: text.bodyMedium, textAlign: TextAlign.center),
        const SizedBox(height: 16),
        AppButton(key: const ValueKey('image-camera'), label: texts.t('search.image.camera'), onPressed: _busy ? null : () => _pick(ImageSource.camera)),
        const SizedBox(height: 8),
        AppButton(
          key: const ValueKey('image-gallery'),
          label: texts.t('search.image.browse'),
          style: AppButtonStyle.outline,
          onPressed: _busy ? null : () => _pick(ImageSource.gallery),
        ),
        const SizedBox(height: 12),
        Text(texts.t('search.image.privacy'), style: text.labelSmall?.copyWith(color: colors.muted, fontWeight: FontWeight.w400), textAlign: TextAlign.center),
      ],
    );

    return PageScaffold(
      title: texts.t('search.image.title'),
      body: CustomScrollView(
        slivers: [
          SliverPadding(padding: const EdgeInsets.all(16), sliver: SliverToBoxAdapter(child: picker)),
          if (_busy)
            SliverToBoxAdapter(
              child: Column(children: [const CircularProgressIndicator(), const SizedBox(height: 8), Text(texts.t('search.image.searching'))]),
            ),
          if (_error != null)
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: FormBanner(message: _error!),
              ),
            ),
          if (result != null && result.products.isEmpty)
            SliverToBoxAdapter(child: AsyncStateView.empty(icon: 'search', title: texts.t('search.image.empty_title'), body: texts.t('search.image.empty_text'))),
          if (result != null && result.products.isNotEmpty) ...[
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
                child: Text(result.title, style: text.titleLarge),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(padding: const EdgeInsets.fromLTRB(16, 0, 16, 12), child: Text(texts.t('search.image.found_hint'), style: text.bodyMedium)),
            ),
            ProductGridSliver(products: result.products),
          ],
          const SliverToBoxAdapter(child: SizedBox(height: 24)),
        ],
      ),
    );
  }
}
