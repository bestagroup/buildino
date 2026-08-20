import 'dart:async';

import 'package:flutter/material.dart';

import '../api/buildino_api.dart';

class LoyaltyScreen extends StatefulWidget {
  const LoyaltyScreen({required this.api, super.key});

  final BuildinoApi api;

  @override
  State<LoyaltyScreen> createState() => _LoyaltyScreenState();
}

class _LoyaltyScreenState extends State<LoyaltyScreen> {
  Map<String, dynamic>? _account;
  List<Map<String, dynamic>> _rewards = const <Map<String, dynamic>>[];
  List<Map<String, dynamic>> _claims = const <Map<String, dynamic>>[];
  bool _loading = true;
  int? _claiming;
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
      final results = await Future.wait<Object>(<Future<Object>>[
        widget.api.loyaltyAccount(),
        widget.api.rewards(),
        widget.api.claims(),
      ]);
      if (mounted) {
        setState(() {
          _account = results[0] as Map<String, dynamic>;
          _rewards = results[1] as List<Map<String, dynamic>>;
          _claims = results[2] as List<Map<String, dynamic>>;
        });
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

  Future<void> _claim(Map<String, dynamic> reward) async {
    final id = reward['id'] as int;
    setState(() => _claiming = id);
    try {
      await widget.api.claimReward(id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('درخواست جایزه ثبت شد.')),
      );
      await _load();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _claiming = null);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _account == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _account == null) {
      return Center(
        child: FilledButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh),
          label: Text(_error!),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: <Widget>[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Row(
                children: <Widget>[
                  const CircleAvatar(
                    radius: 28,
                    child: Icon(Icons.stars, size: 30),
                  ),
                  const SizedBox(width: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Text('امتیاز قابل استفاده'),
                      Text(
                        '${_account?['balance'] ?? 0}',
                        style: Theme.of(context).textTheme.headlineMedium,
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text('جوایز', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          if (_rewards.isEmpty)
            const Card(
              child: Padding(
                padding: EdgeInsets.all(18),
                child: Text('جایزه فعالی وجود ندارد.'),
              ),
            )
          else
            ..._rewards.map(
              (reward) => Card(
                child: ListTile(
                  title: Text('${reward['title']}'),
                  subtitle: Text('${reward['required_points']} امتیاز'),
                  trailing: FilledButton(
                    onPressed: _claiming == reward['id']
                        ? null
                        : () => _claim(reward),
                    child: Text(
                      _claiming == reward['id'] ? '...' : 'دریافت',
                    ),
                  ),
                ),
              ),
            ),
          const SizedBox(height: 20),
          Text('درخواست‌های من', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          ..._claims.take(10).map(
                (claim) => Card(
                  child: ListTile(
                    title: Text(
                      '${(claim['reward'] as Map<String, dynamic>?)?['title'] ?? 'جایزه'}',
                    ),
                    subtitle: Text('${claim['claimed_at'] ?? ''}'),
                    trailing: Chip(label: Text('${claim['status']}')),
                  ),
                ),
              ),
        ],
      ),
    );
  }
}
