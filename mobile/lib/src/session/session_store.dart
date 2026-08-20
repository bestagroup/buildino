import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStore extends ChangeNotifier {
  SessionStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _tokenKey = 'buildino_access_token';
  static const _deviceKey = 'buildino_device_id';

  final FlutterSecureStorage _storage;
  String? _token;
  String? _deviceId;

  String? get token => _token;
  String get deviceId => _deviceId ?? '';
  bool get authenticated => _token != null && _token!.isNotEmpty;

  Future<void> restore() async {
    _token = await _storage.read(key: _tokenKey);
    _deviceId = await _storage.read(key: _deviceKey);

    if (_deviceId == null || _deviceId!.isEmpty) {
      _deviceId = _newDeviceId();
      await _storage.write(key: _deviceKey, value: _deviceId);
    }

    notifyListeners();
  }

  Future<void> signIn(String token) async {
    _token = token;
    await _storage.write(key: _tokenKey, value: token);
    notifyListeners();
  }

  Future<void> signOut() async {
    _token = null;
    await _storage.delete(key: _tokenKey);
    notifyListeners();
  }

  String _newDeviceId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    return bytes.map((byte) => byte.toRadixString(16).padLeft(2, '0')).join();
  }
}
