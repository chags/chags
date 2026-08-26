import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';
import 'adjustments_screen.dart';
import 'time_card_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api, required this.onLogout});
  final ApiClient api;
  final VoidCallback onLogout;
  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? user;
  Map<String, dynamic>? status;
  String? error;
  bool loading = true;
  bool punching = false;
  Timer? timer;
  DateTime now = DateTime.now();

  @override
  void initState() {
    super.initState();
    load();
    timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => now = DateTime.now());
    });
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  Future<void> load() async {
    try {
      final values = await Future.wait([
        widget.api.me(),
        widget.api.punchStatus(),
      ]);
      setState(() {
        user = values[0];
        status = values[1];
        error = null;
      });
    } catch (exception) {
      setState(() => error = exception.toString());
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> punch() async {
    setState(() => punching = true);
    try {
      final expectedType = status?['next_type'] as String?;
      if (expectedType == null) return;
      final intent = await widget.api.session.pendingPunchIntent(expectedType);
      await widget.api.punch(intent.idempotencyKey, intent.expectedType);
      await widget.api.session.clearPendingPunch();
      final updated = await widget.api.punchStatus();
      setState(() => status = updated);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Ponto registrado com sucesso.'),
            backgroundColor: Color(0xFF16A34A),
          ),
        );
      }
    } catch (exception) {
      if (exception is ApiException &&
          exception.statusCode != null &&
          exception.statusCode! < 500 &&
          exception.statusCode != 429) {
        await widget.api.session.clearPendingPunch();
      }
      if (exception is ApiException && exception.statusCode == 409) {
        await load();
      }
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(exception.toString())));
      }
    } finally {
      if (mounted) setState(() => punching = false);
    }
  }

  String label(String? type) =>
      const {
        'clock_in': 'Entrada',
        'break_start': 'Início do intervalo',
        'break_end': 'Fim do intervalo',
        'clock_out': 'Saída',
        'overtime_start': 'Hora extra — entrada',
        'overtime_end': 'Hora extra — saída',
      }[type] ??
      'Jornada concluída';

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Chags Ponto'),
      actions: [
        IconButton(
          onPressed: () async {
            await widget.api.logout();
            widget.onLogout();
          },
          icon: const Icon(Icons.logout),
          tooltip: 'Sair',
        ),
      ],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  'Olá, ${user?['name'] ?? ''}',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(DateFormat("EEEE, d 'de' MMMM", 'pt_BR').format(now)),
                const SizedBox(height: 24),
                Card(
                  color: const Color(0xFF123524),
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      children: [
                        Text(
                          DateFormat('HH:mm:ss').format(now),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 44,
                            fontWeight: FontWeight.bold,
                            fontFeatures: [FontFeature.tabularFigures()],
                          ),
                        ),
                        const Text(
                          'Horário de Brasília',
                          style: TextStyle(color: Colors.white70),
                        ),
                        const SizedBox(height: 24),
                        Text(
                          'Próximo registro',
                          style: TextStyle(color: Colors.green.shade100),
                        ),
                        Text(
                          label(status?['next_type'] as String?),
                          textAlign: TextAlign.center,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 22,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 20),
                        SizedBox(
                          width: double.infinity,
                          child: FilledButton(
                            onPressed: punching || status?['next_type'] == null
                                ? null
                                : punch,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(vertical: 14),
                              child: punching
                                  ? const SizedBox.square(
                                      dimension: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Text('Registrar ponto'),
                            ),
                          ),
                        ),
                      ],
                    ),
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
                const SizedBox(height: 24),
                Text(
                  'Batidas de hoje',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                ...((status?['entries'] as List?) ?? []).map((raw) {
                  final entry = Map<String, dynamic>.from(raw as Map);
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: _statusIcon(entry['status'] as String?),
                      title: Text(label(entry['type'] as String?)),
                      trailing: Text(
                        entry['time']?.toString() ?? '--:--',
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      subtitle: Text(_statusLabel(entry['status'] as String?)),
                    ),
                  );
                }),
                if (((status?['entries'] as List?) ?? []).isEmpty)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text('Nenhuma batida registrada hoje.'),
                    ),
                  ),
                const SizedBox(height: 24),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => TimeCardScreen(api: widget.api),
                          ),
                        ),
                        icon: const Icon(Icons.calendar_month),
                        label: const Text('Cartão'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => AdjustmentsScreen(api: widget.api),
                          ),
                        ),
                        icon: const Icon(Icons.edit_calendar),
                        label: const Text('Ajustes'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
  );

  Widget _statusIcon(String? value) => Icon(
    Icons.circle,
    color: value == 'approved'
        ? Colors.green
        : value == 'pending'
        ? Colors.amber
        : Colors.red,
    size: 16,
  );
  String _statusLabel(String? value) => value == 'approved'
      ? 'Aprovada'
      : value == 'pending'
      ? 'Pendente'
      : 'Cancelada';
}
