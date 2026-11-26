# White-label store apps

One Flutter codebase, one build per store. Each store has a folder here:

```
tenants/<key>/
  tenant.json            identity of the build (committed)
  icon.png               1024×1024 launcher icon, no transparency (committed)
  icon_foreground.png    optional Android adaptive foreground, transparent (committed)
  splash.png             optional splash logo, ~288×288 transparent (committed)
  firebase.env           optional push client options (gitignored, never committed)
  firebase.env.example   template for the above
```

## tenant.json

| Field | Meaning |
|---|---|
| `key` | folder name; also namespaces device storage (tokens, settings, text cache) |
| `tenant_id` | store id, sent as `X-Tenant` on every API call |
| `app_name` | launcher / store-listing name |
| `android_application_id`, `ios_bundle_id` | store identities (must be unique per store) |
| `api_base_url` | platform host; the app calls `<host>/api/mobile/v1` and `<host>/store-api/v1` |
| `theme_family`, `theme_variant` | `ecommet` / `elora` / `souqify` + variant, used until `GET bootstrap` answers (bootstrap wins) |
| `primary_color`, `splash_color` | `#RRGGBB`; native splash + first frame colour (bootstrap brand colour wins afterwards) |
| `default_locale` | `en` / `ar` before the store's default is known |
| `version`, `build_number` | build name / number |
| `icon`, `icon_foreground`, `splash_image` | file names inside the folder |

Nothing in `tenant.json` is secret. Signing keys and Firebase options stay on the build machine.

## Build

```
scripts/build_tenant.sh <key> [apk|appbundle|ipa|all] [--debug] [--skip-assets] [--prepare-only]
```

The script writes `android/app/tenant.properties` and `ios/Flutter/Tenant.xcconfig` (gitignored, they
override the development defaults `com.nogrgr.app` / "Nogrgr"), writes the dart-defines to
`build/tenant/<key>/defines.<platform>.json`, regenerates launcher icons (flutter_launcher_icons) and
the native splash (flutter_native_splash), runs `flutter build` and copies the result to `dist/<key>/`.

* Debug APK for a quick check: `scripts/build_tenant.sh demo apk --debug`
* Play Store bundle: export `NOGRGR_KEYSTORE`, `NOGRGR_KEYSTORE_PASSWORD`, `NOGRGR_KEY_ALIAS`,
  `NOGRGR_KEY_PASSWORD`, then `scripts/build_tenant.sh <key> appbundle`
* iOS: on macOS with Xcode signing set up, `scripts/build_tenant.sh <key> ipa`
* Run from the IDE as a tenant: `scripts/build_tenant.sh <key> --prepare-only`, then
  `flutter run --dart-define-from-file=build/tenant/<key>/defines.android.json`

Icons and the native splash are generated into `android/app/src/main/res` and
`ios/Runner/Assets.xcassets`; after building another store, rebuild `demo` (or discard those
changes) before committing.

## Push (FCM)

Push is optional per build. Without `firebase.env` the app never initialises Firebase and device
registration is a no-op. With it, the client options are passed as dart-defines
(`FIREBASE_APP_ID`, `FIREBASE_API_KEY`, `FIREBASE_PROJECT_ID`, `FIREBASE_SENDER_ID`); no
`google-services.json` / `GoogleService-Info.plist` is used or committed. The server side (service
account, sending) is configured in Admin › Integrations › Firebase.
