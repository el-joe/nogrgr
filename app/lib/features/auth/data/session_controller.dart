import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../core/push/push_service.dart';
import 'auth_repository.dart';

enum SessionStatus { unknown, guest, signedIn }

class SessionState {
  const SessionState({required this.status, this.customer, this.expired = false});

  final SessionStatus status;
  final Customer? customer;

  /// True after the refresh token was rejected: the sign-in screen shows the notice.
  final bool expired;

  bool get signedIn => status == SessionStatus.signedIn && customer != null;
}

/// Who is using the app. Restores the stored token pair at start (`GET me`, which
/// refreshes once on 401), signs in / up / out, and drops to guest when the refresh
/// token is rejected.
class SessionController extends Notifier<SessionState> {
  @override
  SessionState build() {
    final expiry = ref.watch(sessionExpiryProvider);
    void onExpired() => state = const SessionState(status: SessionStatus.guest, expired: true);
    expiry.addListener(onExpired);
    ref.onDispose(() => expiry.removeListener(onExpired));

    return const SessionState(status: SessionStatus.unknown);
  }

  AuthRepository get _auth => ref.read(authRepositoryProvider);

  Future<void> restore() async {
    final tokens = await ref.read(tokenStorageProvider).read();

    if (tokens == null) {
      state = const SessionState(status: SessionStatus.guest);

      return;
    }

    try {
      state = SessionState(status: SessionStatus.signedIn, customer: await _auth.me());
    } on ApiException catch (error) {
      // Offline: keep the tokens, act as guest until the next start.
      state = SessionState(status: SessionStatus.guest, expired: error.kind == ApiErrorKind.unauthorized);
    }
  }

  Future<Customer> login(String email, String password) async {
    final customer = await _auth.login(email: email, password: password);
    _signedIn(customer);

    return customer;
  }

  /// Google / Apple sign-in (APP-020 / 021) with the native SDK credential.
  Future<SocialSession> social(String provider, {required String idToken, String? nonce, String? name}) async {
    final session = await _auth.social(provider, idToken: idToken, nonce: nonce, name: name);
    _signedIn(session.customer);

    return session;
  }

  Future<Customer> register({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? phoneCountry,
  }) async {
    final customer = await _auth.register(
      name: name,
      email: email,
      password: password,
      phone: phone,
      phoneCountry: phoneCountry,
    );
    _signedIn(customer);

    return customer;
  }

  void markVerified() {
    final customer = state.customer;

    if (customer != null) {
      state = SessionState(status: SessionStatus.signedIn, customer: customer.copyWith(emailVerified: true));
    }
  }

  /// Profile saved (APP-025): the screen hands back the fresh customer.
  void updateCustomer(Customer customer) {
    if (state.signedIn) {
      state = SessionState(status: SessionStatus.signedIn, customer: customer);
    }
  }

  /// Account deleted (APP-027): the server already revoked every token.
  Future<void> forget() async {
    await ref.read(tokenStorageProvider).clear();
    state = const SessionState(status: SessionStatus.guest);
    ref.read(pushServiceProvider).sync().ignore();
  }

  Future<void> logout() async {
    try {
      await _auth.logout();
    } on ApiException {
      // Tokens are cleared locally either way.
    }

    state = const SessionState(status: SessionStatus.guest);
    ref.read(pushServiceProvider).sync().ignore();
  }

  void _signedIn(Customer customer) {
    state = SessionState(status: SessionStatus.signedIn, customer: customer);
    // Binds this install's push token to the customer.
    ref.read(pushServiceProvider).sync().ignore();
  }
}

final sessionProvider = NotifierProvider<SessionController, SessionState>(SessionController.new);
