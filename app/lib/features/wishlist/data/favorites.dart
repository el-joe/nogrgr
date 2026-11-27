import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../auth/data/session_controller.dart';
import 'wishlist_repository.dart';

/// Ids of the signed-in customer's favourites (null for guests / while loading), so
/// every product card and the product page show the same star state.
class FavoriteIdsController extends Notifier<Set<int>?> {
  @override
  Set<int>? build() {
    final signedIn = ref.watch(sessionProvider.select((s) => s.signedIn));

    if (!signedIn) {
      return null;
    }

    ref.listen(wishlistProvider, (_, next) {
      final ids = next.value?.ids;

      if (ids != null) {
        state = ids;
      }
    });
    Future.microtask(() => ref.read(wishlistProvider.future).ignore());

    return ref.read(wishlistProvider).value?.ids;
  }

  /// Adds / removes [productId]. Returns the new favourite state, or null for guests
  /// (the caller sends them to sign in).
  Future<bool?> toggle(int productId, {required bool current}) async {
    if (!ref.read(sessionProvider).signedIn) {
      return null;
    }

    final before = state ?? <int>{};
    final next = !current;
    state = next ? ({...before, productId}) : ({...before}..remove(productId));

    try {
      final repository = ref.read(wishlistRepositoryProvider);

      if (next) {
        await repository.add(productId);
      } else {
        await repository.remove(productId);
      }

      ref.invalidate(wishlistProvider);
    } catch (_) {
      state = before;
      rethrow;
    }

    return next;
  }
}

final favoriteIdsProvider = NotifierProvider<FavoriteIdsController, Set<int>?>(FavoriteIdsController.new);
