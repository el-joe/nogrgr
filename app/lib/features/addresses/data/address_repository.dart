import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/network/api_client.dart';
import '../../../core/providers.dart';
import '../../auth/data/session_controller.dart';

class Address {
  const Address({
    required this.id,
    required this.fullName,
    required this.phone,
    required this.addressLine,
    required this.city,
    required this.countryId,
    required this.isDefault,
    required this.oneLine,
    this.label,
    this.email,
    this.phoneCountry,
    this.state,
    this.countryName,
  });

  factory Address.fromJson(Map<String, dynamic> json) => Address(
        id: jsonInt(json['id']),
        label: jsonString(json['label']),
        fullName: '${json['full_name'] ?? ''}',
        email: jsonString(json['email']),
        phone: '${json['phone'] ?? ''}',
        phoneCountry: jsonString(json['phone_country']),
        addressLine: '${json['address_line_1'] ?? ''}',
        city: '${json['city'] ?? ''}',
        state: jsonString(json['state']),
        countryId: jsonInt(json['country_id']),
        countryName: jsonString(jsonMap(json['country'])['name']),
        isDefault: json['is_default'] == true,
        oneLine: '${json['one_line'] ?? ''}',
      );

  static List<Address> list(Object? source) => jsonList(source).map(Address.fromJson).toList();

  final int id;
  final String? label;
  final String fullName;
  final String? email;
  final String phone;
  final String? phoneCountry;
  final String addressLine;
  final String city;
  final String? state;
  final int countryId;
  final String? countryName;
  final bool isDefault;
  final String oneLine;
}

/// Shipping country of the address form (`countries` of `GET account/addresses`).
class AddressCountry {
  const AddressCountry({required this.id, required this.iso2, required this.name, required this.flag, required this.phoneCode});

  final int id;
  final String iso2;
  final String name;
  final String flag;
  final String phoneCode;
}

class AddressBook {
  const AddressBook({required this.addresses, required this.countries});

  final List<Address> addresses;
  final List<AddressCountry> countries;
}

/// Saved addresses (`/api/mobile/v1/account/addresses`, APP-028).
class AddressRepository {
  AddressRepository(this._api);

  final ApiClient _api;

  Future<AddressBook> book() async {
    final data = (await _api.get('account/addresses')).map;

    return AddressBook(
      addresses: Address.list(data['addresses']),
      countries: [
        for (final c in jsonList(data['countries']))
          AddressCountry(id: jsonInt(c['id']), iso2: '${c['iso2'] ?? ''}', name: '${c['name'] ?? ''}', flag: '${c['flag'] ?? ''}', phoneCode: '${c['phone_code'] ?? ''}'),
      ],
    );
  }

  /// Creates ([id] null) or updates; returns the saved address.
  Future<Address> save(Map<String, dynamic> body, {int? id}) async {
    final response = id == null ? await _api.post('account/addresses', body: body) : await _api.put('account/addresses/$id', body: body);

    return Address.fromJson(jsonMap(response.map['address']));
  }

  Future<void> makeDefault(int id) => _api.post('account/addresses/$id/default');

  Future<void> delete(int id) => _api.delete('account/addresses/$id');
}

final addressRepositoryProvider = Provider<AddressRepository>((ref) => AddressRepository(ref.watch(apiClientProvider)));

final addressBookProvider = FutureProvider<AddressBook>((ref) {
  ref.watch(sessionProvider.select((s) => s.signedIn));
  ref.watch(localeCodeProvider);

  return ref.watch(addressRepositoryProvider).book();
});
