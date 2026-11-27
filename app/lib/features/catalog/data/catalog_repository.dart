import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/models/json.dart';
import '../../../core/models/product_card.dart';
import '../../../core/network/store_api.dart';
import 'catalog_models.dart';

/// Store API catalog + content (decision #106): home, categories, listings, search,
/// product details, reviews, image search, CMS pages.
class CatalogRepository {
  CatalogRepository(this._api);

  final StoreApi _api;

  Future<HomeData> home() async => HomeData.fromJson((await _api.get('home')).map);

  Future<List<ProductCard>> homeTab(String tab, {int page = 1}) async =>
      ProductCard.list((await _api.get('home/tabs/$tab', query: {'page': page})).map['items']);

  Future<List<Category>> categories() async => Category.list((await _api.get('categories')).map['items']);

  Future<Category> category(String slug) async => Category.fromJson(jsonMap((await _api.get('categories/$slug')).map['category']));

  Future<Listing> listing(ListingSource source, {ListingFilters filters = const ListingFilters(), String? sort, int page = 1, int perPage = 20}) async {
    final query = {...source.toQuery(), ...filters.toQuery(), 'sort': ?sort, 'page': page, 'per_page': perPage};
    final response = await _api.get(source.isSearch ? 'search' : 'products', query: query);

    return Listing.fromJson(response.map);
  }

  Future<Suggestions> autocomplete(String term) async {
    final data = (await _api.get('search/autocomplete', query: {'q': term, 'limit': 6})).map;

    return Suggestions(products: ProductCard.list(data['products']), categories: Category.list(data['categories']));
  }

  Future<ProductDetail> product(int id, {int? variant}) async =>
      ProductDetail.fromJson((await _api.get('products/$id', query: {'variant': ?variant})).map);

  Future<ReviewPage> reviews(int productId, {int page = 1, int? rating, String? sort}) async {
    final data = (await _api.get('products/$productId/reviews', query: {'page': page, 'per_page': 10, 'rating': ?rating, 'sort': ?sort})).map;
    final eligibility = jsonMap(data['eligibility']);

    return ReviewPage(
      summary: ReviewSummary.fromJson(data['summary']),
      items: jsonList(data['items']).map(Review.fromJson).toList(),
      meta: PageMeta.fromJson(data['meta']),
      canWrite: eligibility['can_write'] == true,
      blockReason: jsonString(eligibility['reason']),
    );
  }

  /// Returns the server message (published / waiting for approval).
  Future<String> writeReview(int productId, {required int rating, String? title, required String body}) async =>
      (await _api.post('products/$productId/reviews', body: {'rating': rating, 'title': ?title, 'body': body})).message;

  Future<ImageSearchResult> imageSearch(String path) async {
    final form = FormData.fromMap({'image': await MultipartFile.fromFile(path)});
    final data = (await _api.post('search/image', body: form)).map;

    return ImageSearchResult(title: '${data['title'] ?? ''}', products: jsonList(data['products']).map(ProductCard.fromImageMatch).toList());
  }

  Future<List<StorePage>> pages() async => jsonList((await _api.get('pages')).map['items']).map(StorePage.fromJson).toList();

  Future<StorePage> page(String slug) async => StorePage.fromJson((await _api.get('pages/$slug')).map);
}

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) => CatalogRepository(ref.watch(storeApiProvider)));
