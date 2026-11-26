import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Access + refresh token pair of one app install (`data.tokens` of sign-in / sign-up /
/// refresh). Access: ability `api`, 24 h. Refresh: ability `auth:refresh`, 60 days, rotating.
class TokenPair {
  const TokenPair({
    required this.accessToken,
    required this.refreshToken,
    this.expiresAt,
    this.refreshExpiresAt,
  });

  factory TokenPair.fromJson(Map<String, dynamic> json) => TokenPair(
        accessToken: json['access_token'] as String,
        refreshToken: json['refresh_token'] as String,
        expiresAt: DateTime.tryParse('${json['expires_at'] ?? ''}'),
        refreshExpiresAt: DateTime.tryParse('${json['refresh_expires_at'] ?? ''}'),
      );

  final String accessToken;
  final String refreshToken;
  final DateTime? expiresAt;
  final DateTime? refreshExpiresAt;

  bool get refreshExpired => refreshExpiresAt != null && refreshExpiresAt!.isBefore(DateTime.now());

  Map<String, String> toStorage() => {
        'access_token': accessToken,
        'refresh_token': refreshToken,
        'expires_at': expiresAt?.toIso8601String() ?? '',
        'refresh_expires_at': refreshExpiresAt?.toIso8601String() ?? '',
      };
}

/// Where the token pair lives. Keyed per tenant so two store apps on one device (or a
/// store switch in development) never share a session.
abstract class TokenStorage {
  Future<TokenPair?> read();

  Future<void> write(TokenPair tokens);

  Future<void> clear();
}

/// Keychain / Android Keystore storage (flutter_secure_storage), with an in-memory copy.
class SecureTokenStorage implements TokenStorage {
  SecureTokenStorage(this._namespace, [FlutterSecureStorage? storage]) : _storage = storage ?? const FlutterSecureStorage();

  final String _namespace;
  final FlutterSecureStorage _storage;
  TokenPair? _cached;
  bool _loaded = false;

  String get _key => 'nogrgr.$_namespace.tokens';

  @override
  Future<TokenPair?> read() async {
    if (_loaded) {
      return _cached;
    }

    final access = await _storage.read(key: '$_key.access');
    final refresh = await _storage.read(key: '$_key.refresh');
    _loaded = true;

    if (access == null || refresh == null || access.isEmpty || refresh.isEmpty) {
      return _cached = null;
    }

    return _cached = TokenPair.fromJson({
      'access_token': access,
      'refresh_token': refresh,
      'expires_at': await _storage.read(key: '$_key.expires_at'),
      'refresh_expires_at': await _storage.read(key: '$_key.refresh_expires_at'),
    });
  }

  @override
  Future<void> write(TokenPair tokens) async {
    _cached = tokens;
    _loaded = true;

    for (final entry in tokens.toStorage().entries) {
      await _storage.write(key: '$_key.${entry.key.replaceFirst('_token', '')}', value: entry.value);
    }
  }

  @override
  Future<void> clear() async {
    _cached = null;
    _loaded = true;

    for (final suffix in ['access', 'refresh', 'expires_at', 'refresh_expires_at']) {
      await _storage.delete(key: '$_key.$suffix');
    }
  }
}

/// Test / preview storage.
class MemoryTokenStorage implements TokenStorage {
  MemoryTokenStorage([this._tokens]);

  TokenPair? _tokens;

  @override
  Future<TokenPair?> read() async => _tokens;

  @override
  Future<void> write(TokenPair tokens) async => _tokens = tokens;

  @override
  Future<void> clear() async => _tokens = null;
}
