import 'package:flutter/material.dart';

import '../../core/theme/app_tokens.dart';
import '../../core/widgets/app_widgets.dart';

class AppTabItem {
  const AppTabItem({required this.icon, required this.label, this.badge = 0});

  final String icon;
  final String label;
  final int badge;
}

/// "App/Tab bar" (Figma 142:99877): 83 high incl. the 34 home-indicator inset, top
/// border, 60-wide tabs with a 24 icon, 3 gap and an 11 medium label; active tab in
/// primary, the rest muted; 18 primary badge at the icon's top end.
class AppTabBar extends StatelessWidget {
  const AppTabBar({super.key, required this.items, required this.currentIndex, required this.onTap});

  final List<AppTabItem> items;
  final int currentIndex;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final bottom = MediaQuery.paddingOf(context).bottom;

    return Container(
      decoration: BoxDecoration(
        color: colors.tabbar,
        border: Border(top: BorderSide(color: colors.border)),
      ),
      padding: EdgeInsets.fromLTRB(20, 8, 20, bottom > 0 ? bottom : 12),
      child: Row(
        mainAxisAlignment: items.length > 2 ? MainAxisAlignment.spaceBetween : MainAxisAlignment.spaceAround,
        children: [
          for (var i = 0; i < items.length; i++)
            _Tab(item: items[i], active: i == currentIndex, onTap: () => onTap(i)),
        ],
      ),
    );
  }
}

class _Tab extends StatelessWidget {
  const _Tab({required this.item, required this.active, required this.onTap});

  final AppTabItem item;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = context.colors;
    final color = active ? colors.primary : colors.muted;

    return Semantics(
      selected: active,
      button: true,
      label: item.label,
      excludeSemantics: true,
      child: InkResponse(
        onTap: onTap,
        radius: 32,
        child: SizedBox(
          width: 60,
          child: Stack(
            clipBehavior: Clip.none,
            alignment: Alignment.topCenter,
            children: [
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TweenAnimationBuilder<Color?>(
                    tween: ColorTween(end: color),
                    duration: const Duration(milliseconds: 180),
                    builder: (context, value, _) => AppIcon(item.icon, color: value),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    item.label,
                    maxLines: 1,
                    overflow: TextOverflow.visible,
                    softWrap: false,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(color: color),
                  ),
                ],
              ),
              if (item.badge > 0)
                PositionedDirectional(
                  start: 36,
                  top: -4,
                  child: Container(
                    constraints: const BoxConstraints(minWidth: 18),
                    height: 18,
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(color: colors.primary, borderRadius: BorderRadius.circular(9)),
                    alignment: Alignment.center,
                    child: Text(
                      item.badge > 99 ? '99+' : '${item.badge}',
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(color: colors.onPrimary),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
