import 'package:app/core/auth/token_storage.dart';
import 'package:app/features/auth/presentation/login_screen.dart';
import 'package:app/features/auth/presentation/reset_password_screen.dart';
import 'package:app/features/auth/presentation/verify_email_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/harness.dart';

Future<void> openLogin(WidgetTester tester) async {
  await Harness.openTab(tester, 'Account');
  await tester.tap(find.byKey(const ValueKey('account-sign-in')));
  await Harness.settle(tester);
}

void main() {
  testWidgets('guest account opens sign in; empty submit shows field errors', (tester) async {
    final harness = Harness(prefs: Harness.returning());
    await harness.pump(tester);
    await openLogin(tester);

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Welcome back'), findsOneWidget);
    // No social sign-in: the API does not offer it, so no dead buttons.
    expect(find.text('Continue with Google'), findsNothing);

    await tester.tap(find.byKey(const ValueKey('login-submit')));
    await tester.pump();

    expect(find.text('This field is required.'), findsNWidgets(2));
    expect(harness.adapter.to('auth/login'), isEmpty);
  });

  testWidgets('sign in with a verified account lands on the account menu', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => request.path == 'auth/login'
          ? FakeReply.ok({'customer': customerJson(), 'tokens': tokensJson()})
          : defaultHandler(request),
    );
    await harness.pump(tester);
    await openLogin(tester);

    await tester.enterText(find.byKey(const ValueKey('login-email')), 'fatma@example.com');
    await tester.enterText(find.byKey(const ValueKey('login-password')), 'secret-pass');
    await tester.tap(find.byKey(const ValueKey('login-submit')));
    await Harness.settle(tester);

    final sent = harness.adapter.to('auth/login').single;
    expect(sent.body['email'], 'fatma@example.com');
    expect(sent.body['device_id'], 'device-test-0001');
    expect((await harness.storage.read())!.accessToken, 'access');
    expect(find.text('Fatma Elsonny'), findsOneWidget);
    expect(find.text('FE'), findsOneWidget);
    expect(find.text('Log out'), findsOneWidget);
  });

  testWidgets('wrong password shows the API message', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => request.path == 'auth/login'
          ? FakeReply.validation({'email': ['These credentials do not match our records.']})
          : defaultHandler(request),
    );
    await harness.pump(tester);
    await openLogin(tester);

    await tester.enterText(find.byKey(const ValueKey('login-email')), 'fatma@example.com');
    await tester.enterText(find.byKey(const ValueKey('login-password')), 'nope-nope');
    await tester.tap(find.byKey(const ValueKey('login-submit')));
    await Harness.settle(tester);

    expect(find.text('These credentials do not match our records.'), findsOneWidget);
  });

  testWidgets('sign up sends the form and opens the e-mail code screen; the code verifies', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => switch (request.path) {
        'auth/register' => FakeReply(201, {'success': true, 'message': 'Welcome', 'data': {'customer': customerJson(verified: false), 'tokens': tokensJson()}}),
        'auth/email/verification' => FakeReply.ok({'verified': false, 'resend_in': 30, 'expires_in_minutes': 15}),
        'auth/email/verify' => FakeReply.ok({'verified': true}, message: 'Your email is verified.'),
        _ => defaultHandler(request),
      },
    );
    await harness.pump(tester);
    await openLogin(tester);
    await tester.tap(find.text('New here? Create an account'));
    await Harness.settle(tester);

    await tester.enterText(find.byKey(const ValueKey('register-name')), 'Fatma Elsonny');
    await tester.enterText(find.byKey(const ValueKey('register-email')), 'fatma@example.com');
    await tester.enterText(find.byKey(const ValueKey('register-phone')), '551234567');
    await tester.enterText(find.byKey(const ValueKey('register-password')), 'short');
    await tester.tap(find.byKey(const ValueKey('register-submit')));
    await tester.pump();
    expect(find.text('At least 8 characters.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('register-password')), 'long-enough-1');
    await tester.tap(find.byKey(const ValueKey('register-submit')));
    await Harness.settle(tester);

    final body = harness.adapter.to('auth/register').single.body as Map;
    expect(body['phone_country'], 'SA');
    expect(body['password_confirmation'], 'long-enough-1');
    expect(find.byType(VerifyEmailScreen), findsOneWidget);
    expect(find.textContaining('fatma@example.com'), findsOneWidget);
    expect(find.textContaining('Resend code in 00:'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('code-input')), '123456');
    await Harness.settle(tester);

    expect(harness.adapter.to('auth/email/verify').single.body, {'code': '123456'});
    expect(find.text('Fatma Elsonny'), findsOneWidget);
  });

  testWidgets('forgot password → code + new password → back to sign in', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      handler: (request) async => switch (request.path) {
        'auth/password/forgot' => FakeReply.ok({'resend_in': 45}, message: 'sent'),
        'auth/password/reset' => FakeReply.ok(null, message: 'Your password has been reset.'),
        _ => defaultHandler(request),
      },
    );
    await harness.pump(tester);
    await openLogin(tester);
    await tester.tap(find.text('Forgot password?'));
    await Harness.settle(tester);

    await tester.enterText(find.byKey(const ValueKey('forgot-email')), 'not-an-email');
    await tester.tap(find.byKey(const ValueKey('forgot-submit')));
    await tester.pump();
    expect(find.text('Enter a valid email address.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('forgot-email')), 'fatma@example.com');
    await tester.tap(find.byKey(const ValueKey('forgot-submit')));
    await Harness.settle(tester);

    expect(find.byType(ResetPasswordScreen), findsOneWidget);
    // Let the "code sent" snack bar go (it floats over the bottom action bar).
    await tester.pump(const Duration(seconds: 5));
    await Harness.settle(tester);
    expect(find.text('Enter the code sent to fatma@example.com'), findsOneWidget);
    expect(find.textContaining('Resend code in 00:'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('code-input')), '481920');
    await tester.enterText(find.byKey(const ValueKey('reset-password')), 'new-password-1');
    await tester.enterText(find.byKey(const ValueKey('reset-confirm')), 'different-1');
    await tester.tap(find.byKey(const ValueKey('reset-submit')));
    await tester.pump();
    expect(find.text('Passwords do not match.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('reset-confirm')), 'new-password-1');
    await tester.tap(find.byKey(const ValueKey('reset-submit')));
    await Harness.settle(tester);

    expect(harness.adapter.to('auth/password/reset').single.body,
        {'email': 'fatma@example.com', 'code': '481920', 'password': 'new-password-1', 'password_confirmation': 'new-password-1'});
    expect(find.byType(LoginScreen), findsOneWidget);
  });

  testWidgets('stored session is restored and log out returns to guest', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: (request) async => request.path == 'auth/logout' ? FakeReply.ok(null) : defaultHandler(request),
    );
    await harness.pump(tester);
    await Harness.openTab(tester, 'Account');

    expect(find.text('Fatma Elsonny'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('account-logout')));
    await Harness.settle(tester);
    await tester.tap(find.widgetWithText(TextButton, 'Log out'));
    await Harness.settle(tester);

    expect(harness.adapter.to('auth/logout'), hasLength(1));
    expect(await harness.storage.read(), isNull);
    expect(find.text('Sign in for the best experience'), findsOneWidget);
  });

  testWidgets('Arabic runs right-to-left with Arabic texts', (tester) async {
    final harness = Harness(prefs: Harness.returning(locale: 'ar'), locale: 'ar');
    await harness.pump(tester);
    await Harness.openTab(tester, 'حسابي');

    expect(find.text('سجّل الدخول لتجربة أفضل'), findsOneWidget);
    final direction = Directionality.of(tester.element(find.text('سجّل الدخول لتجربة أفضل')));
    expect(direction, TextDirection.rtl);
  });
}
