import 'dart:io';

import 'package:app/app/router.dart';
import 'package:app/core/auth/token_storage.dart';
import 'package:app/core/url_opener.dart';
import 'package:app/features/support/data/support_repository.dart';
import 'package:app/features/support/presentation/tickets_screens.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import '../support/fake_api.dart';
import '../support/harness.dart';

const orderUuid = '11111111-1111-4111-8111-111111111111';

Map<String, dynamic> ticketJson({int id = 41, String status = 'answered', bool unread = true, bool canReply = true, bool canClose = true, bool detail = false}) => {
      'id': id,
      'reference': 'C-${1000 + id}',
      'subject': 'Where is my parcel?',
      'topic': {'value': 'delivery', 'label': 'Delivery'},
      'status': {'value': status, 'label': status == 'closed' ? 'Closed' : 'Answered', 'tone': status == 'closed' ? 'neutral' : 'brand'},
      'order': {'uuid': orderUuid, 'number': 'N-1001'},
      'unread': unread,
      'last_reply_by': 'store',
      'last_reply_at': '2026-11-26T09:00:00Z',
      'excerpt': 'It leaves the warehouse today.',
      'can_reply': canReply,
      'can_close': canClose,
      'created_at': '2026-11-25T09:00:00Z',
      if (detail)
        'messages': [
          {'id': 1, 'author': 'customer', 'author_name': 'Fatma Elsonny', 'body': 'My parcel has not arrived.', 'created_at': '2026-11-25T09:00:00Z', 'attachments': []},
          {
            'id': 2,
            'author': 'store',
            'author_name': 'Elora Support',
            'body': 'It leaves the warehouse today.',
            'created_at': '2026-11-26T09:00:00Z',
            'attachments': [
              {'id': 9, 'name': 'label.pdf', 'mime_type': 'application/pdf', 'size': 2048, 'size_label': '2 KB', 'is_image': false, 'url': 'https://api.test/store-api/v1/support/tickets/41/attachments/9?signature=abc'},
            ],
          },
        ],
    };

Map<String, dynamic> meta(int total) => {'current_page': 1, 'last_page': 1, 'per_page': 20, 'total': total};

Future<FakeReply> ticketsHandler(SentRequest request, {String status = 'answered'}) async {
  switch (request.path) {
    case 'store/support/tickets':
      if (request.method == 'POST') {
        return FakeReply(201, {'success': true, 'message': 'Ticket C-1041 sent.', 'data': {'ticket': ticketJson(detail: true)}});
      }

      return request.query['status'] == 'closed'
          ? FakeReply.ok({'items': [], 'meta': meta(0)})
          : FakeReply.ok({'items': [ticketJson()], 'meta': meta(1)});
    case 'store/support/tickets/options':
      return FakeReply.ok({
        'topics': [
          {'value': 'delivery', 'label': 'Delivery'},
          {'value': 'other', 'label': 'Something else'},
        ],
        'orders': [
          {'uuid': orderUuid, 'number': 'N-1001'},
        ],
        'max_files': 2,
        'max_file_kb': 1,
        'file_types': ['jpg', 'png', 'pdf'],
      });
    case 'store/support/tickets/41':
      return FakeReply.ok({'ticket': ticketJson(detail: true, status: status, canReply: status != 'closed', canClose: status != 'closed')});
    case 'store/support/tickets/41/messages':
      return FakeReply(201, {'success': true, 'message': 'Reply sent.', 'data': {'message': {}, 'ticket': ticketJson()}});
    case 'store/support/tickets/41/close':
      return FakeReply.ok({'ticket': ticketJson(status: 'closed')}, message: 'Ticket C-1041 closed.');
  }

  return defaultHandler(request);
}

/// Real small files for the multipart body (sync IO, unique per test).
List<LocalFile> tempFiles(List<(String, int)> specs) {
  final dir = Directory.systemTemp.createTempSync('tickets-test-');
  addTearDown(() => dir.deleteSync(recursive: true));

  return [
    for (final (name, size) in specs) LocalFile(path: (File('${dir.path}/$name')..writeAsBytesSync(List.filled(size, 1))).path, name: name, size: size),
  ];
}

Harness ticketsHarness({String status = 'answered', List<String>? opened, List<LocalFile> pick = const []}) => Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: (request) => ticketsHandler(request, status: status),
      overrides: [
        urlOpenerProvider.overrideWithValue((url) async {
          opened?.add(url);

          return true;
        }),
        filePickerProvider.overrideWithValue((_) async => pick),
      ],
    );

/// Scrolls the thread / form list until [finder] is built and on screen.
Future<void> reveal(WidgetTester tester, Finder finder) async {
  await tester.scrollUntilVisible(finder, 200, scrollable: find.byType(Scrollable).first);
  await tester.pump();
}

Future<void> go(Harness harness, WidgetTester tester, String location) async {
  harness.container.read(routerProvider).push(location);
  await Harness.settle(tester);
}

void main() {
  test('Ticket.fromJson reads row + thread', () {
    final ticket = Ticket.fromJson(ticketJson(detail: true));

    expect(ticket.reference, 'C-1041');
    expect(ticket.topic.label, 'Delivery');
    expect(ticket.status.tone, 'brand');
    expect(ticket.order!.number, 'N-1001');
    expect(ticket.unread, isTrue);
    expect(ticket.lastReplyByStore, isTrue);
    expect(ticket.messages, hasLength(2));
    expect(ticket.messages.first.fromCustomer, isTrue);
    expect(ticket.messages.last.attachments.single.sizeLabel, '2 KB');
    expect(Ticket.fromJson({...ticketJson(), 'unread': 0}).unread, isFalse);
  });

  testWidgets('account row → active tickets; closed tab is empty', (tester) async {
    final harness = ticketsHarness();
    await harness.pump(tester);
    await Harness.openTab(tester, 'Account');

    // Active ticket count on the row.
    expect(find.descendant(of: find.byKey(const ValueKey('account-support')), matching: find.text('1')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('account-support')));
    await Harness.settle(tester);

    expect(find.text('Support tickets'), findsOneWidget);
    expect(find.text('Where is my parcel?'), findsOneWidget);
    expect(find.text('New reply'), findsOneWidget);
    expect(harness.adapter.to('store/support/tickets').first.query['status'], 'active');

    await tester.tap(find.byKey(const ValueKey('tickets-tab-closed')));
    await Harness.settle(tester);
    expect(harness.adapter.to('store/support/tickets').last.query['status'], 'closed');
    expect(find.text('No tickets in this group.'), findsOneWidget);
  });

  testWidgets('new ticket validates, attaches files within the limits and opens the thread', (tester) async {
    final files = tempFiles([('photo.jpg', 200), ('big.pdf', 4096), ('note.pdf', 100)]);
    final harness = ticketsHarness(pick: files);
    await harness.pump(tester);
    await go(harness, tester, Routes.newTicketFor(orderUuid));

    await tester.tap(find.byKey(const ValueKey('ticket-send')));
    await tester.pump();
    expect(find.text('This field is required.'), findsNWidgets(3));
    expect(harness.adapter.requests.where((r) => r.path == 'store/support/tickets' && r.method == 'POST'), isEmpty);

    await tester.tap(find.byKey(const ValueKey('ticket-topic')));
    await Harness.settle(tester);
    await tester.tap(find.text('Delivery').last);
    await Harness.settle(tester);
    await tester.enterText(find.byKey(const ValueKey('ticket-subject')), 'Where is my parcel?');
    await tester.enterText(find.byKey(const ValueKey('ticket-body')), 'My parcel has not arrived yet.');

    await reveal(tester, find.byKey(const ValueKey('attachment-add')));
    await tester.tap(find.byKey(const ValueKey('attachment-add')));
    await Harness.settle(tester);
    // big.pdf (4 KB) is over the 1 KB limit; the third file would exceed 2 files.
    expect(find.byKey(const ValueKey('attachment-photo.jpg')), findsOneWidget);
    expect(find.byKey(const ValueKey('attachment-note.pdf')), findsOneWidget);
    expect(find.byKey(const ValueKey('attachment-big.pdf')), findsNothing);
    expect(find.text('big.pdf is larger than 1 KB.'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('ticket-send')));
    await Harness.settle(tester);

    final sent = harness.adapter.requests.singleWhere((r) => r.path == 'store/support/tickets' && r.method == 'POST');
    final form = sent.body as FormData;
    expect(Map.fromEntries(form.fields), {'topic': 'delivery', 'subject': 'Where is my parcel?', 'body': 'My parcel has not arrived yet.', 'order_uuid': orderUuid});
    expect(form.files.map((f) => '${f.key}:${f.value.filename}'), ['files[]:photo.jpg', 'files[]:note.pdf']);
    // Replaced by the conversation of the new ticket.
    expect(find.text('C-1041'), findsWidgets);
    expect(find.text('My parcel has not arrived.'), findsOneWidget);
  });

  testWidgets('API field errors land on the form', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(),
      tokens: TokenPair.fromJson(tokensJson()),
      handler: (request) async => request.path == 'store/support/tickets' && request.method == 'POST'
          ? FakeReply.validation({'subject': ['The subject must be at least 3 characters.']})
          : ticketsHandler(request),
    );
    await harness.pump(tester);
    await go(harness, tester, Routes.newTicket);

    await tester.tap(find.byKey(const ValueKey('ticket-topic')));
    await Harness.settle(tester);
    await tester.tap(find.text('Something else'));
    await Harness.settle(tester);
    await tester.enterText(find.byKey(const ValueKey('ticket-subject')), 'Hey');
    await tester.enterText(find.byKey(const ValueKey('ticket-body')), 'Question about sizes');
    await tester.tap(find.byKey(const ValueKey('ticket-send')));
    await Harness.settle(tester);

    expect(find.text('The subject must be at least 3 characters.'), findsOneWidget);
  });

  testWidgets('thread: signed file link, reply with a file, close', (tester) async {
    final opened = <String>[];
    final files = tempFiles([('receipt.png', 300)]);
    final harness = ticketsHarness(opened: opened, pick: files);
    await harness.pump(tester);
    await go(harness, tester, Routes.ticket(41));

    expect(find.text('It leaves the warehouse today.'), findsOneWidget);
    expect(find.text('Elora Support'), findsOneWidget);
    expect(find.text('You'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('ticket-file-9')));
    await Harness.settle(tester);
    expect(opened.single, contains('signature=abc'));

    await reveal(tester, find.byKey(const ValueKey('ticket-reply')));
    await tester.enterText(find.byKey(const ValueKey('ticket-reply')), 'Thanks!');
    await reveal(tester, find.byKey(const ValueKey('attachment-add')));
    await tester.tap(find.byKey(const ValueKey('attachment-add')));
    await Harness.settle(tester);
    await reveal(tester, find.byKey(const ValueKey('ticket-reply-send')));
    await tester.tap(find.byKey(const ValueKey('ticket-reply-send')));
    await Harness.settle(tester);

    final reply = harness.adapter.to('store/support/tickets/41/messages').single.body as FormData;
    expect(Map.fromEntries(reply.fields), {'body': 'Thanks!'});
    expect(reply.files.single.value.filename, 'receipt.png');
    expect(find.text('Reply sent.'), findsOneWidget);
    // Thread re-read after the reply.
    expect(harness.adapter.to('store/support/tickets/41').length, greaterThanOrEqualTo(2));

    await tester.tap(find.byKey(const ValueKey('ticket-close')));
    await Harness.settle(tester);
    expect(find.text('Close this ticket?'), findsOneWidget);
    await tester.tap(find.text('Close ticket').last);
    await Harness.settle(tester);
    expect(harness.adapter.to('store/support/tickets/41/close'), hasLength(1));
  });

  testWidgets('empty reply is refused locally', (tester) async {
    final harness = ticketsHarness();
    await harness.pump(tester);
    await go(harness, tester, Routes.ticket(41));

    await reveal(tester, find.byKey(const ValueKey('ticket-reply-send')));
    await tester.tap(find.byKey(const ValueKey('ticket-reply-send')));
    await tester.pump();

    expect(find.text('This field is required.'), findsOneWidget);
    expect(harness.adapter.to('store/support/tickets/41/messages'), isEmpty);
  });

  testWidgets('closed ticket: no composer, no close, "new ticket" note', (tester) async {
    final harness = ticketsHarness(status: 'closed');
    await harness.pump(tester);
    await go(harness, tester, Routes.ticket(41));

    expect(find.byKey(const ValueKey('ticket-reply')), findsNothing);
    expect(find.byKey(const ValueKey('ticket-close')), findsNothing);
    await reveal(tester, find.byKey(const ValueKey('ticket-closed-note')));
    expect(find.text('This ticket is closed. Open a new ticket if you still need help.'), findsOneWidget);
  });

  testWidgets('Arabic: ticket list is right-to-left with Arabic store texts', (tester) async {
    final harness = Harness(
      prefs: Harness.returning(locale: 'ar'),
      locale: 'ar',
      tokens: TokenPair.fromJson(tokensJson()),
      handler: ticketsHandler,
    );
    await harness.pump(tester);
    await go(harness, tester, Routes.support);

    expect(find.text('تذاكر الدعم'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('Where is my parcel?'))), TextDirection.rtl);
  });
}
