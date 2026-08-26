import 'dart:convert';
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
    final challenge = await api.createDeviceChallenge('register', platform);
    final identity = await _identity();
    final device = await _deviceData(platform);
    final app = await PackageInfo.fromPlatform();
    final registered = await api.registerDevice({
      'challenge_id': challenge['challenge_id'],
      'nonce': challenge['nonce'],
      'installation_id': await store.installationId(),
      'device_name': device['model'] ?? 'Dispositivo móvel',
      'public_key': {'algorithm': 'ES256', 'value': identity.publicPem},
      'challenge_signature': identity.sign(challenge['nonce'] as String),
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
  }

  Future<_DeviceIdentity> _identity() async {
    final domain = ECDomainParameters('prime256v1');
    final saved = await store.devicePrivateKey;
    ECPrivateKey privateKey;
    ECPublicKey publicKey;
    if (saved == null) {
      final random = FortunaRandom()..seed(KeyParameter(_randomBytes(32)));
      final generator = ECKeyGenerator()
        ..init(ParametersWithRandom(ECKeyGeneratorParameters(domain), random));
      final pair = generator.generateKeyPair();
      privateKey = pair.privateKey;
      publicKey = pair.publicKey;
      await store.saveDevicePrivateKey(privateKey.d!.toRadixString(16));
    } else {
      privateKey = ECPrivateKey(BigInt.parse(saved, radix: 16), domain);
      publicKey = ECPublicKey(domain.G * privateKey.d, domain);
    }
    return _DeviceIdentity(privateKey, publicKey, domain);
  }

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

class _DeviceIdentity {
  _DeviceIdentity(this.privateKey, this.publicKey, this.domain);
  final ECPrivateKey privateKey;
  final ECPublicKey publicKey;
  final ECDomainParameters domain;

  String get publicPem {
    final point = publicKey.Q!.getEncoded(false);
    final algorithm = ASN1Sequence()
      ..add(ASN1ObjectIdentifier.fromName('ecPublicKey'))
      ..add(ASN1ObjectIdentifier.fromName('prime256v1'));
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
    final signer = Signer('SHA-256/ECDSA')
      ..init(true, PrivateKeyParameter<ECPrivateKey>(privateKey));
    final signature =
        signer.generateSignature(Uint8List.fromList(utf8.encode(nonce)))
            as ECSignature;
    final sequence = ASN1Sequence()
      ..add(ASN1Integer(signature.r))
      ..add(ASN1Integer(signature.s));
    return base64.encode(sequence.encodedBytes);
  }
}
