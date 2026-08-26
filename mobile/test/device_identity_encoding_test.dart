import 'dart:convert';

import 'package:chags_ponto/src/core/device_identity_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:pointycastle/export.dart';

void main() {
  test('codifica chave pública e assinatura ES256 em DER', () {
    final domain = ECDomainParameters('prime256v1');
    final privateKey = ECPrivateKey(BigInt.one, domain);
    final publicKey = ECPublicKey(domain.G * privateKey.d, domain);
    final identity = DeviceIdentityMaterial(privateKey, publicKey, domain);

    expect(identity.publicPem, startsWith('-----BEGIN PUBLIC KEY-----'));
    expect(base64.decode(identity.sign('nonce-de-teste')), isNotEmpty);
  });
}
