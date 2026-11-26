import java.util.Properties

plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// White-label identity: scripts/build_tenant.sh writes tenant.properties (gitignored)
// from tenants/<key>/tenant.json. Without it the development defaults apply.
val tenant = Properties().apply {
    val file = file("tenant.properties")
    if (file.exists()) file.inputStream().use { load(it) }
}

android {
    namespace = "com.nogrgr.app"

    buildFeatures {
        resValues = true
    }
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        applicationId = tenant.getProperty("applicationId", "com.nogrgr.app")
        resValue("string", "app_name", tenant.getProperty("appName", "Nogrgr"))
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    // Release signing per tenant: keystore path + passwords come from the environment of
    // the build machine (never committed). Without them release builds use the debug key.
    val keystore = System.getenv("NOGRGR_KEYSTORE")
    signingConfigs {
        if (keystore != null) {
            create("tenant") {
                storeFile = file(keystore)
                storePassword = System.getenv("NOGRGR_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("NOGRGR_KEY_ALIAS")
                keyPassword = System.getenv("NOGRGR_KEY_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName(if (keystore != null) "tenant" else "debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
