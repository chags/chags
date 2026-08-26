import 'package:chags_ponto/src/config/app_config.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('a URL padrão aponta para a API versionada', () {
    expect(AppConfig.apiBaseUrl, endsWith('/api/v1'));
  });
}
