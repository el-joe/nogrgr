import 'dart:async';

import 'package:flutter/material.dart';

import '../../../core/theme/app_tokens.dart';
import '../../../core/widgets/app_widgets.dart';

/// "Resend code in 00:45" (13 medium muted), then a link that asks for a new code.
class ResendCountdown extends StatefulWidget {
  const ResendCountdown({
    super.key,
    required this.seconds,
    required this.waitingLabel,
    required this.resendLabel,
    required this.onResend,
  });

  final int seconds;

  /// Receives "mm:ss".
  final String Function(String time) waitingLabel;
  final String resendLabel;

  /// Returns the new wait in seconds.
  final Future<int> Function() onResend;

  @override
  State<ResendCountdown> createState() => _ResendCountdownState();
}

class _ResendCountdownState extends State<ResendCountdown> {
  late int _left = widget.seconds;
  Timer? _timer;
  bool _sending = false;

  @override
  void initState() {
    super.initState();
    _tick();
  }

  @override
  void didUpdateWidget(ResendCountdown old) {
    super.didUpdateWidget(old);

    if (old.seconds != widget.seconds) {
      _left = widget.seconds;
      _tick();
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _tick() {
    _timer?.cancel();

    if (_left <= 0) {
      return;
    }

    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      setState(() => _left--);

      if (_left <= 0) {
        timer.cancel();
      }
    });
  }

  Future<void> _resend() async {
    setState(() => _sending = true);

    try {
      final wait = await widget.onResend();
      setState(() => _left = wait);
      _tick();
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_left > 0) {
      final time = '${(_left ~/ 60).toString().padLeft(2, '0')}:${(_left % 60).toString().padLeft(2, '0')}';

      return Text(
        widget.waitingLabel(time),
        style: Theme.of(context).textTheme.labelMedium?.copyWith(color: context.colors.muted),
      );
    }

    return Align(
      alignment: AlignmentDirectional.centerStart,
      child: _sending
          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          : AppLink(label: widget.resendLabel, onTap: _resend),
    );
  }
}
