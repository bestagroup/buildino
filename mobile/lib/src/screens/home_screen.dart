import 'dart:async';

import 'package:flutter/material.dart';

import '../api/buildino_api.dart';
import 'invoice_screen.dart';
import 'loyalty_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    required this.api,
    required this.onLogout,
    super.key,
  });

  final BuildinoApi api;
  final Future<void> Function() onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? _bootstrap;
  List<Map<String, dynamic>> _invoices = const <Map<String, dynamic>>[];
  int? _unitId;
  int _tab = 0;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final bootstrap = await widget.api.bootstrap();
      final contexts = _contexts(bootstrap);
      final selected = contexts.any(
        (context) => _contextUnitId(context) == _unitId,
      )
          ? _unitId
          : (contexts.isEmpty ? null : _contextUnitId(contexts.first));
      final invoices = selected == null
          ? <Map<String, dynamic>>[]
          : await widget.api.invoicesForUnit(selected);

      if (!mounted) {
        return;
      }
      setState(() {
        _bootstrap = bootstrap;
        _unitId = selected;
        _invoices = invoices;
      });
    } on ApiException catch (error) {
      if (error.statusCode == 401) {
        await widget.onLogout();
        return;
      }
      if (mounted) {
        setState(() => _error = error.message);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'بارگذاری اطلاعات انجام نشد.');
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  Future<void> _selectUnit(int? unitId) async {
    if (unitId == null || unitId == _unitId) {
      return;
    }
    setState(() {
      _unitId = unitId;
      _loading = true;
    });
    try {
      final invoices = await widget.api.invoicesForUnit(unitId);
      if (mounted) {
        setState(() => _invoices = invoices);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _loading = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bootstrap = _bootstrap;
    final user = bootstrap?['user'] as Map<String, dynamic>? ??
        const <String, dynamic>{};

    return Scaffold(
      appBar: AppBar(
        title: Text(
          _tab == 0 ? 'خانه من' : 'باشگاه وفاداری',
        ),
        actions: <Widget>[
          IconButton(
            tooltip: 'خروج',
            onPressed: widget.onLogout,
            icon: const Icon(Icons.logout),
          ),
        ],
      ),
      body: IndexedStack(
        index: _tab,
        children: <Widget>[
          RefreshIndicator(
            onRefresh: _load,
            child: _dashboard(user),
          ),
          LoyaltyScreen(api: widget.api),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (value) => setState(() => _tab = value),
        destinations: const <NavigationDestination>[
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'خانه',
          ),
          NavigationDestination(
            icon: Icon(Icons.stars_outlined),
            selectedIcon: Icon(Icons.stars),
            label: 'امتیازها',
          ),
        ],
      ),
    );
  }

  Widget _dashboard(Map<String, dynamic> user) {
    if (_loading && _bootstrap == null) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null && _bootstrap == null) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: <Widget>[
          _ErrorCard(message: _error!, onRetry: _load),
        ],
      );
    }

    final contexts = _contexts(_bootstrap ?? const <String, dynamic>{});
    final wallet = _bootstrap?['wallet'] as Map<String, dynamic>? ??
        const <String, dynamic>{};
    final notifications =
        _bootstrap?['notifications'] as Map<String, dynamic>? ??
            const <String, dynamic>{};

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      children: <Widget>[
        if (_error != null) ...<Widget>[
          _ErrorCard(message: _error!, onRetry: _load),
          const SizedBox(height: 12),
        ],
        Text(
          'سلام ${_userName(user)}',
          style: Theme.of(context).textTheme.headlineSmall,
        ),
        const SizedBox(height: 16),
        Row(
          children: <Widget>[
            Expanded(
              child: _MetricCard(
                title: 'موجودی کیف پول',
                value: _money(wallet['available_balance']),
                icon: Icons.account_balance_wallet_outlined,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MetricCard(
                title: 'اعلان خوانده‌نشده',
                value: '${notifications['unread_count'] ?? 0}',
                icon: Icons.notifications_none,
              ),
            ),
          ],
        ),
        const SizedBox(height: 20),
        if (contexts.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(20),
              child: Text('هیچ واحد فعال مرتبط با این حساب پیدا نشد.'),
            ),
          )
        else
          DropdownButtonFormField<int>(
            initialValue: _unitId,
            decoration: const InputDecoration(
              labelText: 'واحد فعال',
              prefixIcon: Icon(Icons.apartment),
            ),
            items: contexts
                .map(
                  (item) => DropdownMenuItem<int>(
                    value: _contextUnitId(item),
                    child: Text(_contextLabel(item)),
                  ),
                )
                .toList(growable: false),
            onChanged: _selectUnit,
          ),
        const SizedBox(height: 24),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            Text('صورتحساب‌ها', style: Theme.of(context).textTheme.titleLarge),
            if (_loading)
              const SizedBox.square(
                dimension: 20,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
          ],
        ),
        const SizedBox(height: 10),
        if (!_loading && _invoices.isEmpty)
          const Card(
            child: Padding(
              padding: EdgeInsets.all(20),
              child: Text('صورتحسابی برای این واحد وجود ندارد.'),
            ),
          )
        else
          ..._invoices.map(
            (invoice) => Card(
              child: ListTile(
                leading: const CircleAvatar(child: Icon(Icons.receipt_long)),
                title: Text('${invoice['invoice_number'] ?? 'صورتحساب'}'),
                subtitle: Text(
                  'مانده: ${_money(invoice['outstanding_amount'])} ریال',
                ),
                trailing: _StatusChip(status: '${invoice['status'] ?? ''}'),
                onTap: () => Navigator.of(context).push<void>(
                  MaterialPageRoute<void>(
                    builder: (_) => Directionality(
                      textDirection: TextDirection.rtl,
                      child: InvoiceScreen(
                        api: widget.api,
                        invoiceId: invoice['id'] as int,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ),
      ],
    );
  }

  List<Map<String, dynamic>> _contexts(Map<String, dynamic> source) {
    final values = source['contexts'];
    return values is List<dynamic>
        ? values.whereType<Map<String, dynamic>>().toList(growable: false)
        : const <Map<String, dynamic>>[];
  }

  int _contextUnitId(Map<String, dynamic> context) {
    final unit = context['unit'] as Map<String, dynamic>?;
    return unit?['id'] as int;
  }

  String _contextLabel(Map<String, dynamic> context) {
    final unit = context['unit'] as Map<String, dynamic>? ??
        const <String, dynamic>{};
    final building = context['building'] as Map<String, dynamic>? ??
        const <String, dynamic>{};
    return '${building['title'] ?? 'ساختمان'} — ${unit['title'] ?? unit['unit_number']}';
  }

  String _userName(Map<String, dynamic> user) {
    final name = '${user['first_name'] ?? ''} ${user['last_name'] ?? ''}'.trim();
    return name.isEmpty ? '${user['mobile'] ?? ''}' : name;
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.title,
    required this.value,
    required this.icon,
  });

  final String title;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Icon(icon),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(value, style: Theme.of(context).textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}

class _ErrorCard extends StatelessWidget {
  const _ErrorCard({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: Theme.of(context).colorScheme.errorContainer,
      child: ListTile(
        title: Text(message),
        trailing: IconButton(
          tooltip: 'تلاش دوباره',
          onPressed: onRetry,
          icon: const Icon(Icons.refresh),
        ),
      ),
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    const labels = <String, String>{
      'draft': 'پیش‌نویس',
      'issued': 'صادرشده',
      'partial': 'پرداخت جزئی',
      'paid': 'پرداخت‌شده',
      'overdue': 'سررسید گذشته',
      'pending': 'در انتظار',
      'approved': 'تأییدشده',
      'rejected': 'ردشده',
    };
    return Chip(label: Text(labels[status] ?? status));
  }
}

String _money(Object? value) {
  final number = int.tryParse('$value') ?? 0;
  final raw = number.toString();
  final result = StringBuffer();
  for (var index = 0; index < raw.length; index++) {
    if (index > 0 && (raw.length - index) % 3 == 0) {
      result.write(',');
    }
    result.write(raw[index]);
  }
  return result.toString();
}
