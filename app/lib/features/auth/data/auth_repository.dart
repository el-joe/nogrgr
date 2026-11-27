import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/auth/token_storage.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';

/// Signed-in customer (`data.customer`).
class Customer {
  const Customer({
    required this.id,
    required this.name,
    required this.email,
    required this.emailVerified,
    this.phone,
    this.phoneCountry,
    this.locale,
  });

  factory Customer.fromJson(Map<String, dynamic> json) => Customer(
        id: json['id'] as int? ?? 0,
        name: '${json['name'] ?? ''}',
        email: '${json['email'] ?? ''}',
        emailVerified: json['email_verified'] == true,
        phone: json['phone'] as String?,
        phoneCountry: json['phone_country'] as String?,
        locale: json['locale'] as String?,
      );

  final int id;
  final String name;
  final String email;
  final bool emailVerified;
  final String? phone;
  final String? phoneCountry;
  final String? locale;

  /// "FE" for "Fatma Elsonny" (account avatar).
  String get initials {
    final parts = name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();

    if (parts.isEmpty) {
      return '?';
    }

    String firstChar(String value) => String.fromCharCode(value.runes.first);
    final first = firstChar(parts.first);
    final last = parts.length > 1 ? firstChar(parts.last) : '';

    return (first + last).toUpperCase();
  }

  Customer copyWith({bool? emailVerified}) => Customer(
        id: id,
        name: name,
        email: email,
        emailVerified: emailVerified ?? this.emailVerified,
        phone: phone,
        phoneCountry: phoneCountry,
        locale: locale,
      );
}

class SocialSession {
  const SocialSession({required this.customer, required this.created, this.message = ''});

  final Customer customer;

  /// True when the store created a new account (HTTP 201).
  final bool created;
  final String message;
}

class VerificationStatus {
  const VerificationStatus({required this.verified, required this.resendIn, this.message = '', this.expiresInMinutes});

  final bool verified;
  final int? expiresInMinutes;
  final int resendIn;
  final String message;
}

/// Mobile API auth endpoints (APP-020…023, e-mail code). Tokens are stored here.
class AuthRepository {
  AuthRepository(this._api, this._tokens, this._deviceId);

  final ApiClient _api;
  final TokenStorage _tokens;
  final String _deviceId;

  Future<Customer> login({required String email, required String password}) async {
    final response = await _api.post(
      'auth/login',
      body: {'email': email, 'password': password, 'device_id': _deviceId},
      auth: false,
    );

    return _session(response.map);
  }

  Future<Customer> register({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? phoneCountry,
  }) async {
    final response = await _api.post(
      'auth/register',
      body: {
        'name': name,
        'email': email,
        if (phone != null && phone.isNotEmpty) 'phone': phone,
        if (phone != null && phone.isNotEmpty) 'phone_country': phoneCountry,
        'password': password,
        'password_confirmation': password,
        'device_id': _deviceId,
      },
      auth: false,
    );

    return _session(response.map);
  }

  /// `POST auth/social/{provider}` with the native SDK id_token: 201 = account created,
  /// 200 = found by provider id / linked by verified e-mail; 404 provider not set up,
  /// 422 token refused (both surface as [ApiException]).
  Future<SocialSession> social(String provider, {required String idToken, String? nonce, String? name}) async {
    final response = await _api.post(
      'auth/social/$provider',
      body: {'id_token': idToken, 'nonce': ?nonce, 'name': ?name, 'device_id': _deviceId},
      auth: false,
    );

    return SocialSession(customer: await _session(response.map), created: response.statusCode == 201, message: response.message);
  }

  Future<Customer> me() async {
    final response = await _api.get('me');

    return Customer.fromJson(response.map['customer'] as Map<String, dynamic>);
  }

  Future<void> logout({bool everywhere = false}) async {
    try {
      await _api.post('auth/logout', body: {'everywhere': everywhere});
    } finally {
      await _tokens.clear();
    }
  }

  /// Returns the seconds before a new code may be requested.
  Future<int> forgotPassword(String email) async {
    final response = await _api.post('auth/password/forgot', body: {'email': email}, auth: false);

    return response.map['resend_in'] as int? ?? 45;
  }

  Future<void> checkResetCode({required String email, required String code}) =>
      _api.post('auth/password/check', body: {'email': email, 'code': code}, auth: false);

  Future<String> resetPassword({required String email, required String code, required String password}) async {
    final response = await _api.post(
      'auth/password/reset',
      body: {'email': email, 'code': code, 'password': password, 'password_confirmation': password},
      auth: false,
    );

    return response.message;
  }

  Future<VerificationStatus> verificationStatus() async {
    final data = (await _api.get('auth/email/verification')).map;

    return VerificationStatus(
      verified: data['verified'] == true,
      resendIn: data['resend_in'] as int? ?? 0,
      expiresInMinutes: data['expires_in_minutes'] as int?,
    );
  }

  Future<void> verifyEmail(String code) => _api.post('auth/email/verify', body: {'code': code});

  Future<VerificationStatus> resendVerification() async {
    final response = await _api.post('auth/email/resend');
    final data = response.map;

    return VerificationStatus(
      verified: data['verified'] == true,
      resendIn: data['resend_in'] as int? ?? 0,
      message: response.message,
    );
  }

  Future<Customer> _session(Map<String, dynamic> data) async {
    await _tokens.write(TokenPair.fromJson(data['tokens'] as Map<String, dynamic>));

    return Customer.fromJson(data['customer'] as Map<String, dynamic>);
  }
}

final authRepositoryProvider = Provider<AuthRepository>(
  (ref) => AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
    ref.watch(preferencesProvider.select((p) => p.deviceId)),
  ),
);
