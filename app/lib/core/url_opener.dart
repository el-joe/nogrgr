import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens a web link (invoice, CMS links, return evidence) in the in-app browser;
/// overridden in tests.
final urlOpenerProvider = Provider<Future<bool> Function(String url)>(
  (ref) => (url) => launchUrl(Uri.parse(url), mode: LaunchMode.inAppBrowserView),
);
