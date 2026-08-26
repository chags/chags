import 'dart:convert';
import 'dart:developer' as developer;
import 'dart:io';
import 'dart:math';
import 'dart:typed_data';

import 'package:asn1lib/asn1lib.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:pointycastle/export.dart';

import 'api_client.dart';
import 'session_store.dart';

class DeviceIdentityService {
  DeviceIdentityService(this.api, this.store);
  final ApiClient api;
  final SessionStore store;

  Future<void> ensureRegistered() async {
    if (await store.deviceId != null) return;
    final platform = Platform.isIOS ? 'ios' : 'android';
    try {
      _log('challenge.start', 'Solicitando desafio de registro.');
      final challenge = await api.createDeviceChallenge('register', platform);

      _log('identity.start', 'Preparando identidade criptográfica.');
      final identity = await _identity();

      _log('device_info.start', 'Coletando informações do dispositivo.');
      final device = await _deviceData(platform);
      final app = await PackageInfo.fromPlatform();

      _log('signature.start', 'Codificando chave pública e assinatura.');
      late final String publicKey;
      late final String signature;
      try {
        publicKey = identity.publicPem;
        signature = identity.sign(challenge['nonce'] as String);
      } catch (error, stackTrace) {
        _log(
          'signature.failed',
          'Falha ao codificar chave pública ou assinatura.',
          error: error,
          stackTrace: stackTrace,
        );
        throw DeviceProtectionException('signature', error);
      }

      _log('registration.start', 'Registrando dispositivo na API.');
      final registered = await api.registerDevice({
        'challenge_id': challenge['challenge_id'],
        'nonce': challenge['nonce'],
        'installation_id': await store.installationId(),
        'device_name': device['model'] ?? 'Dispositivo móvel',
        'public_key': {'algorithm': 'ES256', 'value': publicKey},
        'challenge_signature': signature,
        'app': {
          'version': app.version,
          'build': app.buildNumber,
          'package_name': app.packageName,
          'signing_digest': null,
        },
        'device': device,
        // Driver temporário até Play Integrity e App Attest serem configurados.
        'attestation': {'provider': 'fake', 'token': 'valid-test-attestation'},
      });
      await store.saveDeviceId(registered['id'] as String);
      _log('registration.success', 'Dispositivo registrado com sucesso.');
    } on DeviceProtectionException {
      rethrow;
    } catch (error, stackTrace) {
      _log(
        'registration.failed',
        'Falha ao proteger o dispositivo.',
        error: error,
        stackTrace: stackTrace,
      );
      throw DeviceProtectionException('registration', error);
    }
  }

  Future<DeviceIdentityMaterial> _identity() async {
    try {
      final domain = ECDomainParameters('prime256v1');
      final saved = await store.devicePrivateKey;
      ECPrivateKey privateKey;
      ECPublicKey publicKey;
      if (saved == null) {
        final random = FortunaRandom()..seed(KeyParameter(_randomBytes(32)));
        final generator = ECKeyGenerator()
          ..init(
            ParametersWithRandom(ECKeyGeneratorParameters(domain), random),
          );
        final pair = generator.generateKeyPair();
        privateKey = pair.privateKey;
        publicKey = pair.publicKey;
        await store.saveDevicePrivateKey(privateKey.d!.toRadixString(16));
      } else {
        privateKey = ECPrivateKey(BigInt.parse(saved, radix: 16), domain);
        publicKey = ECPublicKey(domain.G * privateKey.d, domain);
      }
      return DeviceIdentityMaterial(privateKey, publicKey, domain);
    } catch (error, stackTrace) {
      _log(
        'identity.failed',
        'Falha ao gerar ou armazenar a identidade.',
        error: error,
        stackTrace: stackTrace,
      );
      throw DeviceProtectionException('identity', error);
    }
  }

  void _log(
    String stage,
    String message, {
    Object? error,
    StackTrace? stackTrace,
  }) => developer.log(
    '[$stage] $message',
    name: 'chags.device_protection',
    error: error,
    stackTrace: stackTrace,
  );

  Future<Map<String, dynamic>> _deviceData(String platform) async {
    final info = DeviceInfoPlugin();
    if (platform == 'ios') {
      final value = await info.iosInfo;
      return {
        'platform': platform,
        'manufacturer': 'Apple',
        'model': value.utsname.machine,
        'os_version': value.systemVersion,
        'security_patch': null,
        'locale': Platform.localeName.replaceAll('_', '-'),
        'timezone': DateTime.now().timeZoneName,
        'biometric_available': true,
      };
    }
    final value = await info.androidInfo;
    return {
      'platform': platform,
      'manufacturer': value.manufacturer,
      'model': value.model,
      'os_version': value.version.release,
      'security_patch': value.version.securityPatch,
      'locale': Platform.localeName.replaceAll('_', '-'),
      'timezone': DateTime.now().timeZoneName,
      'biometric_available': true,
    };
  }

  Uint8List _randomBytes(int length) {
    final random = Random.secure();
    return Uint8List.fromList(
      List.generate(length, (_) => random.nextInt(256)),
    );
  }
}

class DeviceIdentityMaterial {
  DeviceIdentityMaterial(this.privateKey, this.publicKey, this.domain);
  final ECPrivateKey privateKey;
  final ECPublicKey publicKey;
  final ECDomainParameters domain;

  String get publicPem {
    final point = publicKey.Q!.getEncoded(false);
    final algorithm = ASN1Sequence()
      ..add(ASN1ObjectIdentifier.fromComponentString('1.2.840.10045.2.1'))
      ..add(ASN1ObjectIdentifier.fromComponentString('1.2.840.10045.3.1.7'));
    final sequence = ASN1Sequence()
      ..add(algorithm)
      ..add(ASN1BitString(Uint8List.fromList(point)));
    final base64Value = base64.encode(sequence.encodedBytes);
    final lines = RegExp(
      '.{1,64}',
    ).allMatches(base64Value).map((match) => match.group(0)).join('\n');
    return '-----BEGIN PUBLIC KEY-----\n$lines\n-----END PUBLIC KEY-----';
  }

  String sign(String nonce) {
    final random = FortunaRandom()
      ..seed(KeyParameter(_cryptographicRandomBytes(32)));
    final signer = Signer('SHA-256/ECDSA')
      ..init(
        true,
        ParametersWithRandom(
          PrivateKeyParameter<ECPrivateKey>(privateKey),
          random,
        ),
      );
    final signature =
        signer.generateSignature(Uint8List.fromList(utf8.encode(nonce)))
            as ECSignature;
    final sequence = ASN1Sequence()
      ..add(ASN1Integer(signature.r))
      ..add(ASN1Integer(signature.s));
    return base64.encode(sequence.encodedBytes);
  }

  Uint8List _cryptographicRandomBytes(int length) {
    final random = Random.secure();
    return Uint8List.fromList(
      List.generate(length, (_) => random.nextInt(256)),
    );
  }
}

class DeviceProtectionException implements Exception {
  DeviceProtectionException(this.stage, this.cause);

  final String stage;
  final Object cause;

  @override
  String toString() => 'Falha na proteção do dispositivo ($stage).';
}
