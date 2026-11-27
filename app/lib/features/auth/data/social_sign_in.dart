import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

import '../../../core/config/tenant_config.dart';
import '../../../core/providers.dart';

/// Shopper social sign-in providers of `POST auth/social/{provider}`.
enum SocialProvider {
  google,
  apple;

  static SocialProvider? parse(String value) => values.where((p) => p.name == value).firstOrNull;
}

/// What the native SDK hands back: the id_token for the API, the raw nonce (Apple
/// embeds its SHA-256) and the name Apple returns only on the first authorisation.
class SocialCredential {
  const SocialCredential({required this.idToken, this.nonce, this.name});

  final String idToken;
  final String? nonce;
  final String? name;
}

/// The native SDK failed (not a user cancel): misconfigured client id, no Play services …
class SocialSignInFailure implements Exception {
  const SocialSignInFailure(this.detail);

  final String detail;

  @override
  String toString() => 'SocialSignInFailure($detail)';
}

/// Native Google / Apple sign-in. [obtain] returns null when the user cancels.
abstract class SocialAuthenticator {
  /// Whether this build + platform can run the provider's SDK.
  bool supports(SocialProvider provider);

  Future<SocialCredential?> obtain(SocialProvider provider);
}

/// Raw random nonce (URL-safe, 32 bytes) and its SHA-256 hex for Apple.
String randomNonce([Random? random]) {
  final source = random ?? Random.secure();

  return base64UrlEncode(List<int>.generate(32, (_) => source.nextInt(256))).replaceAll('=', '');
}

String sha256Hex(String value) => sha256.convert(utf8.encode(value)).toString();

/// Real SDKs. Google needs the build's client ids (Android: the "Web application"
/// server client id, iOS: the iOS client id); Apple runs on iOS only, when the build
/// carries the capability (`apple_sign_in` in tenant.json).
class NativeSocialAuthenticator implements SocialAuthenticator {
  NativeSocialAuthenticator({required this.config, required this.platform});

  final SocialClientConfig config;
  final String? platform;
  bool _googleReady = false;

  @override
  bool supports(SocialProvider provider) => switch (provider) {
        SocialProvider.google => (platform == 'android' && config.googleServerClientId.isNotEmpty) ||
            (platform == 'ios' && config.googleIosClientId.isNotEmpty),
        SocialProvider.apple => platform == 'ios' && config.appleSignIn,
      };

  @override
  Future<SocialCredential?> obtain(SocialProvider provider) =>
      provider == SocialProvider.google ? _google() : _apple();

  Future<SocialCredential?> _google() async {
    final google = GoogleSignIn.instance;

    try {
      if (!_googleReady) {
        await google.initialize(
          clientId: platform == 'ios' ? config.googleIosClientId : null,
          serverClientId: config.googleServerClientId.isEmpty ? null : config.googleServerClientId,
        );
        _googleReady = true;
      }

      final account = await google.authenticate();
      final token = account.authentication.idToken;

      if (token == null || token.isEmpty) {
        throw const SocialSignInFailure('google: no id_token');
      }

      return SocialCredential(idToken: token, name: account.displayName);
    } on GoogleSignInException catch (error) {
      if (error.code == GoogleSignInExceptionCode.canceled || error.code == GoogleSignInExceptionCode.interrupted) {
        return null;
      }

      debugPrint('Google sign-in failed: ${error.code} ${error.description}');
      throw SocialSignInFailure('google: ${error.code.name}');
    }
  }

  Future<SocialCredential?> _apple() async {
    final nonce = randomNonce();

    try {
      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: const [AppleIDAuthorizationScopes.email, AppleIDAuthorizationScopes.fullName],
        nonce: sha256Hex(nonce),
      );
      final token = credential.identityToken;

      if (token == null || token.isEmpty) {
        throw const SocialSignInFailure('apple: no identity token');
      }

      final name = [credential.givenName, credential.familyName].whereType<String>().where((part) => part.trim().isNotEmpty).join(' ');

      return SocialCredential(idToken: token, nonce: nonce, name: name.isEmpty ? null : name);
    } on SignInWithAppleAuthorizationException catch (error) {
      if (error.code == AuthorizationErrorCode.canceled) {
        return null;
      }

      throw SocialSignInFailure('apple: ${error.code.name}');
    } on SignInWithAppleException catch (error) {
      throw SocialSignInFailure('apple: $error');
    }
  }
}

final socialAuthenticatorProvider = Provider<SocialAuthenticator>(
  (ref) => NativeSocialAuthenticator(config: ref.watch(tenantConfigProvider).social, platform: ref.watch(appPlatformProvider)),
);
