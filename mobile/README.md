# Chags Ponto Mobile

Aplicativo Flutter Android/iOS conectado à API de ponto do Laravel.

## Executar no emulador Android

```bash
docker compose --profile mobile run --rm flutter flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:9080/api/v1
```

Em aparelho físico, troque `10.0.2.2` pelo IP da máquina que publica a porta
`9080`. Em produção, informe a URL HTTPS por `--dart-define`.

## Validar no Docker

```bash
docker compose --profile mobile run --rm flutter flutter analyze
docker compose --profile mobile run --rm flutter flutter test
```

O JWT, o identificador da instalação e o dispositivo confiável são persistidos
com `flutter_secure_storage`. FACEIO, Play Integrity e App Attest ainda exigem
credenciais e configuração nativa antes de serem ativados.
