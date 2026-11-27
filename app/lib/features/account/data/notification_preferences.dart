import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

/// Customer notification types in display order (Identity `CustomerNotificationType`).
const notificationTypes = ['order_updates', 'shipping_updates', 'returns', 'support', 'price_drops', 'back_in_stock', 'promotions'];

/// `type → {email, push}` of `account/notification-preferences`.
typedef PreferenceMatrix = Map<String, Map<String, bool>>;

PreferenceMatrix parsePreferences(Object? json) => {
      for (final entry in jsonMap(json).entries)
        entry.key: {for (final channel in jsonMap(entry.value).entries) channel.key: channel.value == true},
    };

class NotificationPreferencesRepository {
  NotificationPreferencesRepository(this._api);

  final ApiClient _api;

  Future<PreferenceMatrix> load() async => parsePreferences((await _api.get('account/notification-preferences')).map['preferences']);

  /// Partial update: only the changed switch is sent.
  Future<PreferenceMatrix> set(String type, String channel, bool enabled) async {
    final response = await _api.put('account/notification-preferences', body: {
      'preferences': {
        type: {channel: enabled},
      },
    });

    return parsePreferences(response.map['preferences']);
  }
}

final notificationPreferencesRepositoryProvider =
    Provider<NotificationPreferencesRepository>((ref) => NotificationPreferencesRepository(ref.watch(apiClientProvider)));

/// Settings › Push notifications switches (signed-in only). Optimistic; a failed save
/// restores the previous matrix and rethrows for the message.
class NotificationPreferencesController extends AsyncNotifier<PreferenceMatrix> {
  @override
  Future<PreferenceMatrix> build() async {
    if (!ref.watch(sessionProvider.select((s) => s.signedIn))) {
      return const {};
    }

    return ref.read(notificationPreferencesRepositoryProvider).load();
  }

  Future<void> toggle(String type, String channel, bool enabled) async {
    final previous = state.value ?? const {};
    state = AsyncData({
      ...previous,
      type: {...?previous[type], channel: enabled},
    });

    try {
      state = AsyncData(await ref.read(notificationPreferencesRepositoryProvider).set(type, channel, enabled));
    } catch (_) {
      state = AsyncData(previous);
      rethrow;
    }
  }
}

final notificationPreferencesProvider =
    AsyncNotifierProvider<NotificationPreferencesController, PreferenceMatrix>(NotificationPreferencesController.new);
