import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/tenant_config.dart';
import '../network/api_client.dart';
import '../network/api_exception.dart';
import '../providers.dart';

/// A received push: notification text + the data payload
/// `{notification_id, event, order_uuid, ticket_id}` (decision #104 e / #108 c).
class PushMessage {
  const PushMessage({this.title, this.body, this.data = const {}});

  factory PushMessage.fromRemote(RemoteMessage message) => PushMessage(
        title: message.notification?.title,
        body: message.notification?.body,
        data: {for (final entry in message.data.entries) entry.key: '${entry.value}'},
      );

  final String? title;
  final String? body;
  final Map<String, String> data;

  String? _value(String key) {
    final value = data[key];

    return value == null || value.isEmpty || value == 'null' ? null : value;
  }

  String? get notificationId => _value('notification_id');

  String? get orderUuid => _value('order_uuid');

  int? get ticketId => int.tryParse(_value('ticket_id') ?? '');
}

/// FCM device registration (`POST devices`). A no-op when the build has no Firebase
/// client options (`FIREBASE_*` dart-defines) or the store has push switched off: the
/// app never ships google-services files, the options come from the build.
class PushService {
  PushService({
    required this.config,
    required this.api,
    required this.deviceId,
    required this.platform,
    required this.appVersion,
  });

  final TenantConfig config;
  final ApiClient api;
  final String deviceId;
  final String? platform;
  final String appVersion;

  bool _initialised = false;
  bool _enabled = false;
  String? _token;
  StreamSubscription<String>? _refresh;
  final List<StreamSubscription<RemoteMessage>> _listeners = [];
  final _foreground = StreamController<PushMessage>.broadcast();
  final _opened = StreamController<PushMessage>.broadcast();

  bool get available => config.firebase != null && platform != null;

  /// Null until asked; false when the user refused notifications in the OS.
  bool? permissionGranted;

  /// Pushes that arrive while the app is in the foreground (in-app banner).
  Stream<PushMessage> get foreground => _foreground.stream;

  /// Pushes the user tapped (background or from a cold start).
  Stream<PushMessage> get opened => _opened.stream;

  @visibleForTesting
  void emitForeground(PushMessage message) => _foreground.add(message);

  @visibleForTesting
  void emitOpened(PushMessage message) => _opened.add(message);

  void dispose() {
    _refresh?.cancel();
    for (final listener in _listeners) {
      listener.cancel();
    }
    _foreground.close();
    _opened.close();
  }

  /// Asks permission, reads the FCM token and registers this install (guest installs are
  /// allowed; the API binds the install to the customer on sign-in).
  Future<void> register() async {
    if (!available) {
      return;
    }

    try {
      final messaging = await _messaging();
      final settings = await messaging.requestPermission();
      _enabled = settings.authorizationStatus == AuthorizationStatus.authorized ||
          settings.authorizationStatus == AuthorizationStatus.provisional;
      permissionGranted = _enabled;

      if (_listeners.isEmpty) {
        _listeners
          ..add(FirebaseMessaging.onMessage.listen((m) => _foreground.add(PushMessage.fromRemote(m))))
          ..add(FirebaseMessaging.onMessageOpenedApp.listen((m) => _opened.add(PushMessage.fromRemote(m))));
        final initial = await messaging.getInitialMessage();

        // Cold start from a push: the coordinator (subscribed at app start) keeps
        // the target until the start-up gates are passed.
        if (initial != null) {
          _opened.add(PushMessage.fromRemote(initial));
        }
      }
      // onRegistered emits the FCM token now and on every rotation.
      _refresh ??= messaging.onRegistered.listen((token) {
        _token = token;
        _send(token, _enabled).ignore();
      });
      await messaging.register();
    } on FirebaseException catch (error) {
      debugPrint('Push registration skipped: ${error.message}');
    } on ApiException catch (error) {
      debugPrint('Push registration failed: $error');
    }
  }

  /// After sign-out the API releases the install from the customer; sending the known
  /// token again keeps guest pushes (store news) working.
  Future<void> reRegister() async {
    final token = _token;

    if (!available || token == null) {
      return;
    }

    try {
      await _send(token, _enabled);
    } on ApiException catch (error) {
      debugPrint('Push re-registration failed: $error');
    }
  }

  /// Sign-in / sign-out: re-sends the known token (the API binds or releases the
  /// install), or registers when push was never set up in this run.
  Future<void> sync() => _token == null ? register() : reRegister();

  Future<FirebaseMessaging> _messaging() async {
    if (!_initialised) {
      final firebase = config.firebase!;
      await Firebase.initializeApp(
        options: FirebaseOptions(
          apiKey: firebase.apiKey,
          appId: firebase.appId,
          messagingSenderId: firebase.messagingSenderId,
          projectId: firebase.projectId,
          iosBundleId: firebase.iosBundleId.isEmpty ? null : firebase.iosBundleId,
        ),
      );
      _initialised = true;
    }

    return FirebaseMessaging.instance;
  }

  Future<void> _send(String token, bool enabled) => api.post('devices', body: {
        'device_id': deviceId,
        'platform': platform,
        'push_token': token,
        'push_enabled': enabled,
        'app_version': appVersion,
      });
}

final pushServiceProvider = Provider<PushService>((ref) {
  final service = PushService(
    config: ref.watch(tenantConfigProvider),
    api: ref.watch(apiClientProvider),
    deviceId: ref.watch(preferencesProvider.select((p) => p.deviceId)),
    platform: ref.watch(appPlatformProvider),
    appVersion: ref.watch(appVersionProvider),
  );
  ref.onDispose(service.dispose);

  return service;
});
