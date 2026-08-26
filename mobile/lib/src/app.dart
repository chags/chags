import 'package:flutter/material.dart';

import 'core/api_client.dart';
import 'core/device_identity_service.dart';
import 'core/session_store.dart';
import 'screens/home_screen.dart';
import 'screens/phone_screen.dart';

class ChagsPontoApp extends StatefulWidget {
  const ChagsPontoApp({super.key});
  @override
  State<ChagsPontoApp> createState() => _ChagsPontoAppState();
}

class _ChagsPontoAppState extends State<ChagsPontoApp> {
  late final SessionStore store = SessionStore();
  late final ApiClient api = ApiClient(store);
  bool? authenticated;

  @override
  void initState() {
    super.initState();
    _restoreSession();
  }

  Future<void> _restoreSession() async {
    final token = await store.token;
    if (token != null) {
      try {
        await DeviceIdentityService(api, store).ensureRegistered();
      } catch (_) {
        await store.clearSession();
      }
    }
    final deviceId = await store.deviceId;
    if (mounted) {
      setState(() => authenticated = token != null && deviceId != null);
    }
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    debugShowCheckedModeBanner: false,
    title: 'Chags Ponto',
    theme: ThemeData(
      colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF16A34A)),
      scaffoldBackgroundColor: const Color(0xFFF5F7F6),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(),
      ),
      cardTheme: const CardThemeData(elevation: 0, margin: EdgeInsets.zero),
      useMaterial3: true,
    ),
    home: authenticated == null
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : authenticated!
        ? HomeScreen(
            api: api,
            onLogout: () => setState(() => authenticated = false),
          )
        : PhoneScreen(
            api: api,
            onAuthenticated: () => setState(() => authenticated = true),
          ),
  );
}
