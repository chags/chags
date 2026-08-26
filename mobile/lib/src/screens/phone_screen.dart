import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:developer' as developer;

import '../core/api_client.dart';
import '../core/device_identity_service.dart';

class PhoneScreen extends StatefulWidget {
  const PhoneScreen({
    super.key,
    required this.api,
    required this.onAuthenticated,
  });
  final ApiClient api;
  final VoidCallback onAuthenticated;
  @override
  State<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends State<PhoneScreen> {
  final phone = TextEditingController();
  final code = TextEditingController();
  String? challengeId;
  bool loading = false;
  String? error;

  Future<void> submit() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      if (challengeId == null) {
        final data = await widget.api.requestCode(phone.text);
        setState(() => challengeId = data['challenge_id'] as String);
      } else {
        final data = await widget.api.verifyCode(challengeId!, code.text);
        if (data['requires_face_enrollment'] == true) {
          throw ApiException(
            'O cadastro facial do primeiro acesso ainda precisa ser concluído.',
          );
        }
        await DeviceIdentityService(
          widget.api,
          widget.api.session,
        ).ensureRegistered();
        widget.onAuthenticated();
      }
    } catch (exception, stackTrace) {
      developer.log(
        'Falha no fluxo de liberação do aplicativo.',
        name: 'chags.app_unlock',
        error: exception,
        stackTrace: stackTrace,
      );
      setState(
        () => error = exception is ApiException
            ? exception.message
            : exception is DeviceProtectionException
            ? 'Não foi possível proteger este dispositivo '
                  '(etapa: ${exception.stage}). Tente novamente.'
            : 'Não foi possível concluir a liberação. Tente novamente.',
      );
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 440),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Icon(
                  Icons.access_time_filled,
                  size: 72,
                  color: Color(0xFF16A34A),
                ),
                const SizedBox(height: 20),
                Text(
                  'Chags Ponto',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  challengeId == null
                      ? 'Informe seu WhatsApp cadastrado para liberar o aplicativo.'
                      : 'Digite o código de 6 dígitos enviado ao seu WhatsApp.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 32),
                if (challengeId == null)
                  TextField(
                    controller: phone,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'WhatsApp',
                      hintText: '(11) 99999-9999',
                      prefixIcon: Icon(Icons.phone_android),
                    ),
                  ),
                if (challengeId != null)
                  TextField(
                    controller: code,
                    keyboardType: TextInputType.number,
                    textAlign: TextAlign.center,
                    maxLength: 6,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                    style: Theme.of(
                      context,
                    ).textTheme.headlineSmall?.copyWith(letterSpacing: 10),
                    decoration: const InputDecoration(
                      labelText: 'Código de liberação',
                    ),
                  ),
                if (error != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 16),
                    child: Text(
                      error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: loading ? null : submit,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: loading
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(
                            challengeId == null
                                ? 'Enviar código'
                                : 'Liberar aplicativo',
                          ),
                  ),
                ),
                if (challengeId != null)
                  TextButton(
                    onPressed: loading
                        ? null
                        : () => setState(() {
                            challengeId = null;
                            code.clear();
                          }),
                    child: const Text('Alterar telefone'),
                  ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
