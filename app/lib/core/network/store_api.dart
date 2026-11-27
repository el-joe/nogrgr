import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../providers.dart';
import 'api_client.dart';

/// Guest cart token (`X-Cart-Token`), kept per store on the device. It is also sent
/// after sign-in once, so the server merges the guest cart into the customer's cart.
class CartTokenStore {
  CartTokenStore(this._prefs, String tenantKey) : _key = 'nogrgr.$tenantKey.cart_token';

  final SharedPreferences _prefs;
  final String _key;

  String? read() => _prefs.getString(_key);

  Future<void> write(String? token) async {
    if (token == null || token.isEmpty) {
      await _prefs.remove(_key);
    } else if (token != read()) {
      await _prefs.setString(_key, token);
    }
  }
}

final cartTokenStoreProvider = Provider<CartTokenStore>(
  (ref) => CartTokenStore(ref.watch(sharedPreferencesProvider), ref.watch(tenantConfigProvider).key),
);

/// Store API v1 (`/store-api/v1`) on top of the shared [ApiClient]: same headers + token,
/// plus the guest cart token on every call and the token the cart answers with saved.
class StoreApi {
  StoreApi(this._api, this._cartTokens);

  final ApiClient _api;
  final CartTokenStore _cartTokens;

  Map<String, String> get _headers {
    final token = _cartTokens.read();

    return {'X-Cart-Token': ?token};
  }

  Future<ApiResponse> get(String path, {Map<String, dynamic>? query}) =>
      _api.get(_api.storeUrl(path), query: query, headers: _headers);

  Future<ApiResponse> post(String path, {Object? body, Map<String, String> headers = const {}}) =>
      _api.post(_api.storeUrl(path), body: body, headers: {..._headers, ...headers});

  Future<ApiResponse> put(String path, {Object? body}) => _api.put(_api.storeUrl(path), body: body, headers: _headers);

  Future<ApiResponse> patch(String path, {Object? body}) => _api.patch(_api.storeUrl(path), body: body, headers: _headers);

  Future<ApiResponse> delete(String path, {Object? body}) => _api.delete(_api.storeUrl(path), body: body, headers: _headers);

  /// Saves the cart token of a cart payload (`{token, ...}`).
  Future<void> rememberCart(Map<String, dynamic> cart) async {
    final token = cart['token'];

    if (token is String && token.isNotEmpty) {
      await _cartTokens.write(token);
    }
  }
}

final storeApiProvider = Provider<StoreApi>((ref) => StoreApi(ref.watch(apiClientProvider), ref.watch(cartTokenStoreProvider)));
