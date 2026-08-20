import 'dart:async';

import 'package:flutter/material.dart';

import '../api/buildino_api.dart';

class InvoiceScreen extends StatefulWidget {
  const InvoiceScreen({
    required this.api,
    required this.invoiceId,
    super.key,
  });

  final BuildinoApi api;
  final int invoiceId;

  @override
  State<InvoiceScreen> createState() => _InvoiceScreenState();
}

class _InvoiceScreenState extends State<InvoiceScreen> {
  Map<String, dynamic>? _invoice;
  String? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final invoice = await widget.api.invoice(widget.invoiceId);
      if (mounted) {
        setState(() => _invoice = invoice);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final invoice = _invoice;
    return Scaffold(
      appBar: AppBar(title: const Text('جزئیات صورتحساب')),
      body: invoice == null
          ? Center(
              child: _error == null
                  ? const CircularProgressIndicator()
                  : FilledButton.icon(
                      onPressed: _load,
                      icon: const Icon(Icons.refresh),
                      label: Text(_error!),
                    ),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.all(16),
                children: <Widget>[
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Text(
                            '${invoice['invoice_number']}',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                          const Divider(height: 28),
                          _line('مبلغ کل', invoice['total_amount']),
                          _line('پرداخت‌شده', invoice['paid_amount']),
                          _line('مانده', invoice['outstanding_amount']),
                          _line('جریمه فعال', invoice['penalty_amount']),
                          _line(
                            'جریمه بخشوده',
                            invoice['waived_penalty_amount'],
                          ),
                          _textLine('وضعیت', '${invoice['status']}'),
                          _textLine('سررسید', '${invoice['due_date'] ?? '—'}'),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text('اقساط', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 8),
                  ..._items(invoice['installments']).map(
                    (item) => Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          child: Text('${item['installment_number']}'),
                        ),
                        title: Text(
                          '${_money(item['outstanding_amount'])} ریال مانده',
                        ),
                        subtitle: Text(
                          'سررسید ${item['due_date'] ?? '—'} · ${item['status']}',
                        ),
                      ),
                    ),
                  ),
                  if (_items(invoice['installments']).isEmpty)
                    const Card(
                      child: Padding(
                        padding: EdgeInsets.all(18),
                        child: Text('برنامه اقساط برای این صورتحساب ثبت نشده است.'),
                      ),
                    ),
                ],
              ),
            ),
    );
  }

  Widget _line(String title, Object? amount) => _textLine(
        title,
        '${_money(amount)} ریال',
      );

  Widget _textLine(String title, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[Text(title), Text(value)],
        ),
      );

  List<Map<String, dynamic>> _items(Object? value) => value is List<dynamic>
      ? value.whereType<Map<String, dynamic>>().toList(growable: false)
      : const <Map<String, dynamic>>[];
}

String _money(Object? value) {
  final number = int.tryParse('$value') ?? 0;
  return number.toString().replaceAllMapped(
        RegExp(r'\B(?=(\d{3})+(?!\d))'),
        (_) => ',',
      );
}
