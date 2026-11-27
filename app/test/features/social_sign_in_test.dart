import 'dart:math';

import 'package:app/app/router.dart';
import 'package:app/core/config/tenant_config.dart';
import 'package:app/features/auth/data/social_sign_in.dart';
import 'package:app/features/bootstrap/data/bootstrap.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/harness.dart';

/// SDK stand-in: supports the given providers, answers [credential] (null = cancelled)
/// or throws [failure].
class FakeAuthenticator implements SocialAuthenticator {
  FakeAuthenticator({this.supported = const {SocialProvider.google, SocialProvider.apple}, this.credential, this.failure});

  final Set<SocialProvider> supported;
  final SocialCredential? credential;
  final Object? failure;
  final List<SocialProvider> asked = [];

  @override
  bool supports(SocialProvider provider) => supported.contains(provider);

  @override
  Future<SocialCredential?> obtain(SocialProvider provider) async {
    asked.add(provider);

    if (failure != null) {
      throw failure!;
    }

    return credential;
  }
}

Map<String, dynamic> socialBootstrap(List<String> providers) => {
      ...bootstrapJson(),
      'auth': {'social_providers': providers},
    };

Harness socialHarness({required FakeAuthenticator authenticator, List<String> providers = const ['google', 'apple'], FakeReply? reply}) => Harness(
      prefs: Harness.returning(),
      overrides: [socialAuthenticatorProvider.overrideWithValue(authenticator)],
      handler: (request) async => switch (request.path) {
        'bootstrap' => FakeReply.ok(socialBootstrap(providers)),
        'auth/social/google' || 'auth/social/apple' =>
          reply ?? FakeReply(201, {'success': true, 'message': 'Welcome, Fatma Elsonny.', 'data': {'customer': customerJson(), 'tokens': tokensJson()}}),
        _ => defaultHandler(request),
      },
    );

Future<void> openLogin(Harness harness, WidgetTester tester) async {
  harness.container.read(routerProvider).push(Routes.login);
  await Harness.settle(tester);
}

void main() {
  group('NativeSocialAuthenticator.supports', () {
    const ids = SocialClientConfig(googleServerClientId: 'web.apps.googleusercontent.com', googleIosClientId: 'ios.apps.googleusercontent.com', appleSignIn: true);

    test('Google needs the client id of the platform', () {
      expect(NativeSocialAuthenticator(config: ids, platform: 'android').supports(SocialProvider.google), isTrue);
      expect(NativeSocialAuthenticator(config: ids, platform: 'ios').supports(SocialProvider.google), isTrue);
      expect(NativeSocialAuthenticator(config: const SocialClientConfig(googleIosClientId: 'ios.apps.googleusercontent.com'), platform: 'android').supports(SocialProvider.google), isFalse);
      expect(NativeSocialAuthenticator(config: const SocialClientConfig(), platform: 'ios').supports(SocialProvider.google), isFalse);
    });

    test('Apple only on iOS builds with the capability', () {
      expect(NativeSocialAuthenticator(config: ids, platform: 'ios').supports(SocialProvider.apple), isTrue);
      expect(NativeSocialAuthenticator(config: ids, platform: 'android').supports(SocialProvider.apple), isFalse);
      expect(NativeSocialAuthenticator(config: const SocialClientConfig(), platform: 'ios').supports(SocialProvider.apple), isFalse);
    });
  });

  test('nonce helpers: 43-char URL-safe raw nonce, SHA-256 hex for Apple', () {
    final nonce = randomNonce(Random(1));
    expect(nonce, hasLength(43));
    expect(nonce, matches(RegExp(r'^[A-Za-z0-9_-]+$')));
    expect(sha256Hex('abc'), 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
  });

  test('bootstrap reads auth.social_providers', () {
    expect(Bootstrap.fromJson(socialBootstrap(['google'])).socialProviders, ['google']);
    expect(Bootstrap.fromJson(bootstrapJson()).socialProviders, isEmpty);
  });

  testWidgets('only providers the store lists AND the build supports get a button', (tester) async {
    final harness = socialHarness(authenticator: FakeAuthenticator(supported: {SocialProvider.google}));
    await harness.pump(tester);
    await openLogin(harness, tester);

    expect(find.text('or continue with'), findsOneWidget);
    expect(find.byKey(const ValueKey('social-google')), findsOneWidget);
    expect(find.text('Continue with Google'), findsOneWidget);
    expect(find.byKey(const ValueKey('social-apple')), findsNothing);

    harness.container.read(routerProvider).go(Routes.register);
    await Harness.settle(tester);
    await tester.scrollUntilVisible(find.byKey(const ValueKey('social-google')), 200, scrollable: find.byType(Scrollable).first);
    expect(find.byKey(const ValueKey('social-google')), findsOneWidget);
  });

  testWidgets('store without social providers shows no buttons', (tester) async {
    final harness = socialHarness(authenticator: FakeAuthenticator(), providers: const []);
    await harness.pump(tester);
    await openLogin(harness, tester);

    expect(find.text('or continue with'), findsNothing);
    expect(find.byKey(const ValueKey('social-google')), findsNothing);
  });

  testWidgets('Google sign-in posts the id_token and signs in (201 created)', (tester) async {
    final authenticator = FakeAuthenticator(credential: const SocialCredential(idToken: 'google-id-token', name: 'Fatma Elsonny'));
    final harness = socialHarness(authenticator: authenticator);
    await harness.pump(tester);
    await openLogin(harness, tester);

    await tester.tap(find.byKey(const ValueKey('social-google')));
    await Harness.settle(tester);

    final sent = harness.adapter.to('auth/social/google').single;
    expect(sent.body, {'id_token': 'google-id-token', 'name': 'Fatma Elsonny', 'device_id': 'device-test-0001'});
    expect((await harness.storage.read())!.accessToken, 'access');
    expect(find.text('Fatma Elsonny'), findsOneWidget);
    expect(find.text('Log out'), findsOneWidget);
  });

  testWidgets('Apple sends the raw nonce', (tester) async {
    final authenticator = FakeAuthenticator(credential: const SocialCredential(idToken: 'apple-token', nonce: 'raw-nonce'));
    final harness = socialHarness(authenticator: authenticator, reply: FakeReply.ok({'customer': customerJson(), 'tokens': tokensJson()}));
    await harness.pump(tester);
    await openLogin(harness, tester);

    await tester.tap(find.byKey(const ValueKey('social-apple')));
    await Harness.settle(tester);

    expect(harness.adapter.to('auth/social/apple').single.body, {'id_token': 'apple-token', 'nonce': 'raw-nonce', 'device_id': 'device-test-0001'});
    expect(find.text('Log out'), findsOneWidget);
  });

  testWidgets('cancelled native sheet sends nothing', (tester) async {
    final authenticator = FakeAuthenticator();
    final harness = socialHarness(authenticator: authenticator);
    await harness.pump(tester);
    await openLogin(harness, tester);

    await tester.tap(find.byKey(const ValueKey('social-google')));
    await Harness.settle(tester);

    expect(authenticator.asked, [SocialProvider.google]);
    expect(harness.adapter.to('auth/social/google'), isEmpty);
    expect(find.byKey(const ValueKey('social-google')), findsOneWidget);
  });

  testWidgets('422 shows the API message and keeps the guest signed out', (tester) async {
    final rejected = socialHarness(
      authenticator: FakeAuthenticator(credential: const SocialCredential(idToken: 'bad')),
      reply: FakeReply.validation({'id_token': ['The sign-in token could not be verified.']}),
    );
    await rejected.pump(tester);
    await openLogin(rejected, tester);
    await tester.tap(find.byKey(const ValueKey('social-google')));
    await Harness.settle(tester);
    expect(find.text('The sign-in token could not be verified.'), findsOneWidget);
    expect(await rejected.storage.read(), isNull);
  });

  testWidgets('404 provider not configured shows the unavailable notice', (tester) async {
    final harness = socialHarness(
      authenticator: FakeAuthenticator(credential: const SocialCredential(idToken: 'token')),
      reply: FakeReply.error(404, 'Not configured'),
    );
    await harness.pump(tester);
    await openLogin(harness, tester);
    await tester.tap(find.byKey(const ValueKey('social-google')));
    await Harness.settle(tester);

    expect(find.textContaining('This sign-in option is not available right now.'), findsOneWidget);
  });

  testWidgets('SDK failure shows the provider error', (tester) async {
    final harness = socialHarness(authenticator: FakeAuthenticator(failure: const SocialSignInFailure('google: clientConfigurationError')));
    await harness.pump(tester);
    await openLogin(harness, tester);
    await tester.tap(find.byKey(const ValueKey('social-google')));
    await Harness.settle(tester);

    expect(find.text('Could not sign in with Google. Please try again.'), findsOneWidget);
    expect(harness.adapter.to('auth/social/google'), isEmpty);
  });
}
