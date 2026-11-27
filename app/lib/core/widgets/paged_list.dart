import 'package:flutter/material.dart';

/// Pull to refresh + "load the next page near the end" around a scroll view.
class PagedScroll extends StatelessWidget {
  const PagedScroll({super.key, required this.onRefresh, required this.onLoadMore, required this.child});

  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: NotificationListener<ScrollNotification>(
        onNotification: (notification) {
          if (notification.metrics.axis == Axis.vertical && notification.metrics.extentAfter < 400) {
            onLoadMore();
          }

          return false;
        },
        child: child,
      ),
    );
  }
}

/// Spinner row under a list while the next page loads.
class LoadingMore extends StatelessWidget {
  const LoadingMore({super.key});

  @override
  Widget build(BuildContext context) {
    return const Padding(padding: EdgeInsets.all(16), child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.4))));
  }
}
