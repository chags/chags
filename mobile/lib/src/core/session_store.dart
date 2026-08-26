import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

class SessionStore {
  static const _tokenKey = 'jwt_access_token';
  static const _installationKey = 'device_installation_id';
  static const _deviceKey = 'trusted_device_id';
  static const _privateKeyKey = 'device_private_key';
  static const _pendingPunchKey = 'pending_punch_idempotency_key';
  static const _pendingPunchTypeKey = 'pending_punch_expected_type';
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  Future<String?> get token => _storage.read(key: _tokenKey);
  Future<String?> get deviceId => _storage.read(key: _deviceKey);

  Future<String> installationId() async {
    final existing = await _storage.read(key: _installationKey);
    if (existing != null) return existing;
    final generated = const Uuid().v4();
    await _storage.write(key: _installationKey, value: generated);
    return generated;
  }

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);
  Future<void> saveDeviceId(String id) =>
      _storage.write(key: _deviceKey, value: id);
  Future<String?> get devicePrivateKey => _storage.read(key: _privateKeyKey);
  Future<void> saveDevicePrivateKey(String value) =>
      _storage.write(key: _privateKeyKey, value: value);

  Future<PendingPunchIntent> pendingPunchIntent(String expectedType) async {
    final existingKey = await _storage.read(key: _pendingPunchKey);
    final existingType = await _storage.read(key: _pendingPunchTypeKey);
    if (existingKey != null && existingType != null) {
      return PendingPunchIntent(existingKey, existingType);
    }
    final generated = 'punch-${const Uuid().v4()}';
    await _storage.write(key: _pendingPunchKey, value: generated);
    await _storage.write(key: _pendingPunchTypeKey, value: expectedType);
    return PendingPunchIntent(generated, expectedType);
  }

  Future<void> clearPendingPunch() async {
    await _storage.delete(key: _pendingPunchKey);
    await _storage.delete(key: _pendingPunchTypeKey);
  }

  Future<void> clearSession() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _deviceKey);
    await clearPendingPunch();
  }
}

class PendingPunchIntent {
  const PendingPunchIntent(this.idempotencyKey, this.expectedType);

  final String idempotencyKey;
  final String expectedType;
}
