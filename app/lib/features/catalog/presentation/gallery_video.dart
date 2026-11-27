import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';

import '../../../core/providers.dart';
import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/catalog_widgets.dart';
import '../data/catalog_models.dart';

/// Video slide of the product gallery (decision #114 f): the poster (contained) with a
/// play button; tapping plays the video inline with the native player, contained at its
/// own aspect ratio. Pauses when swiped away ([active] false), when the screen is
/// covered / its tab is hidden (TickerMode off) and when the app goes to background.
class GalleryVideo extends ConsumerStatefulWidget {
  const GalleryVideo({super.key, required this.media, required this.active, this.semanticLabel});

  final ProductMedia media;

  /// The slide is the current gallery page.
  final bool active;
  final String? semanticLabel;

  @override
  ConsumerState<GalleryVideo> createState() => _GalleryVideoState();
}

class _GalleryVideoState extends ConsumerState<GalleryVideo> with WidgetsBindingObserver {
  VideoPlayerController? _controller;
  bool _loading = false;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    // Covered by another route or in a hidden tab.
    if (!TickerMode.valuesOf(context).enabled) {
      _pause();
    }
  }

  @override
  void didUpdateWidget(GalleryVideo oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (!widget.active) {
      _pause();
    }

    if (oldWidget.media.url != widget.media.url) {
      _disposeController();
      _failed = false;
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state != AppLifecycleState.resumed) {
      _pause();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _disposeController();
    super.dispose();
  }

  void _disposeController() {
    final controller = _controller;
    _controller = null;
    controller?.removeListener(_changed);

    // Stop the sound at once (swiped out of the page cache / screen closed); the native
    // release below is asynchronous.
    if (controller != null && controller.value.isPlaying) {
      controller.pause();
    }

    controller?.dispose();
  }

  void _changed() {
    if (mounted) {
      setState(() {});
    }
  }

  void _pause() {
    final controller = _controller;

    if (controller != null && controller.value.isPlaying) {
      controller.pause();
    }
  }

  Future<void> _play() async {
    var controller = _controller;

    if (controller == null) {
      controller = VideoPlayerController.networkUrl(Uri.parse(widget.media.url));
      _controller = controller;
      setState(() {
        _loading = true;
        _failed = false;
      });

      try {
        await controller.initialize();
      } catch (_) {
        if (mounted && identical(_controller, controller)) {
          _disposeController();
          setState(() {
            _loading = false;
            _failed = true;
          });
        }

        return;
      }

      if (!mounted || !identical(_controller, controller)) {
        return;
      }

      controller.addListener(_changed);
      setState(() => _loading = false);
    }

    // Swiped away while loading: stay paused on the first frame.
    if (!widget.active) {
      return;
    }

    final value = controller.value;

    if (value.duration > Duration.zero && value.position >= value.duration) {
      await controller.seekTo(Duration.zero);
    }

    await controller.play();
  }

  void _toggle() {
    final controller = _controller;

    if (_loading) {
      return;
    }

    if (controller != null && controller.value.isPlaying) {
      controller.pause();
    } else {
      _play();
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final texts = ref.watch(textsProvider);
    final controller = _controller;
    final ready = controller != null && controller.value.isInitialized;
    final playing = ready && controller.value.isPlaying;

    return Semantics(
      button: true,
      label: playing ? texts.t('app.product.pause_video') : texts.t('app.product.play_video'),
      child: GestureDetector(
        key: const ValueKey('gallery-video'),
        behavior: HitTestBehavior.opaque,
        onTap: _toggle,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: ColoredBox(
            color: colors.imageWell,
            child: Stack(
              fit: StackFit.expand,
              children: [
                if (ready)
                  Center(
                    child: AspectRatio(
                      aspectRatio: controller.value.aspectRatio <= 0 ? 16 / 9 : controller.value.aspectRatio,
                      child: VideoPlayer(controller, key: const ValueKey('gallery-video-player')),
                    ),
                  )
                else
                  NetImage(widget.media.poster, semanticLabel: widget.semanticLabel),
                if (!playing)
                  Center(
                    child: Container(
                      key: const ValueKey('gallery-video-play'),
                      width: 60,
                      height: 60,
                      decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.55), shape: BoxShape.circle),
                      alignment: Alignment.center,
                      child: _loading
                          ? const SizedBox(width: 26, height: 26, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                          : const Icon(Icons.play_arrow_rounded, size: 36, color: Colors.white),
                    ),
                  ),
                if (_failed)
                  PositionedDirectional(
                    start: 12,
                    end: 12,
                    bottom: 12,
                    child: Text(
                      texts.t('app.product.video_error'),
                      key: const ValueKey('gallery-video-error'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(color: colors.danger),
                    ),
                  ),
                if (ready)
                  Positioned(
                    left: 0,
                    right: 0,
                    bottom: 0,
                    // Timeline keeps its natural left-to-right direction so scrubbing matches.
                    child: Directionality(
                      textDirection: TextDirection.ltr,
                      child: VideoProgressIndicator(
                        controller,
                        allowScrubbing: true,
                        padding: const EdgeInsets.only(top: 8),
                        colors: VideoProgressColors(playedColor: colors.primary, bufferedColor: colors.border, backgroundColor: colors.border.withValues(alpha: 0.4)),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
