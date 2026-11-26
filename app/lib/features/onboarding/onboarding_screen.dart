import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/providers.dart';
import '../../core/theme/app_tokens.dart';
import '../../core/widgets/app_widgets.dart';

/// Onboarding 1/3 … 3/3 (Figma 149:104033 / 104054 / 104075): swipeable pages with a
/// 430-high image, 28 bold title, 15 muted body, page dots, Skip and a bottom Next.
class OnboardingScreen extends ConsumerStatefulWidget {
  const OnboardingScreen({super.key});

  static const pages = 3;

  @override
  ConsumerState<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _OnboardingScreenState extends ConsumerState<OnboardingScreen> {
  final _controller = PageController();
  int _page = 0;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _finish() => ref.read(preferencesProvider.notifier).update((p) => p.copyWith(onboardingSeen: true));

  void _next() {
    if (_page == OnboardingScreen.pages - 1) {
      _finish();

      return;
    }

    _controller.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
  }

  @override
  Widget build(BuildContext context) {
    final texts = ref.watch(textsProvider);
    final colors = context.colors;
    final last = _page == OnboardingScreen.pages - 1;

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: [
            Expanded(
              child: PageView.builder(
                controller: _controller,
                itemCount: OnboardingScreen.pages,
                onPageChanged: (page) => setState(() => _page = page),
                itemBuilder: (context, index) => _OnboardingPage(
                  index: index,
                  title: texts.t('app.onboarding.${index + 1}.title'),
                  body: texts.t('app.onboarding.${index + 1}.body'),
                ),
              ),
            ),
            Semantics(
              label: texts.t('app.onboarding.page', {'current': _page + 1, 'total': OnboardingScreen.pages}),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (var i = 0; i < OnboardingScreen.pages; i++)
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 220),
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      width: i == _page ? 20 : 6,
                      height: 6,
                      decoration: BoxDecoration(
                        color: i == _page ? colors.primary : colors.border,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 26,
              child: last
                  ? null
                  : AppLink(label: texts.t('app.onboarding.skip'), onTap: _finish, muted: true, align: TextAlign.center),
            ),
            const SizedBox(height: 16),
            BottomActionBar(
              child: AppButton(
                label: texts.t(last ? 'app.onboarding.start' : 'app.onboarding.next'),
                onPressed: _next,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _OnboardingPage extends StatelessWidget {
  const _OnboardingPage({required this.index, required this.title, required this.body});

  final int index;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final text = Theme.of(context).textTheme;

    return SingleChildScrollView(
      child: Column(
        children: [
          Container(
            height: 430,
            width: double.infinity,
            color: const Color(0xFFF2F2F2),
            child: Image.asset('assets/images/onboarding_${index + 1}.jpg', fit: BoxFit.contain),
          ),
          const SizedBox(height: 20),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 24),
            child: Column(
              children: [
                Text(title, style: text.displaySmall, textAlign: TextAlign.center),
                const SizedBox(height: 10),
                Text(body, style: text.bodyLarge?.copyWith(color: context.colors.muted), textAlign: TextAlign.center),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
