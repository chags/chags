import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';

class AdjustmentsScreen extends StatefulWidget {
  const AdjustmentsScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<AdjustmentsScreen> createState() => _AdjustmentsScreenState();
}

class _AdjustmentsScreenState extends State<AdjustmentsScreen> {
  List<dynamic>? items;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    try {
      final response = await widget.api.adjustments();
      items = response['data'] as List? ?? [];
      error = null;
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Ajustes de ponto')),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: () async {
        final created = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
            builder: (_) => NewAdjustmentScreen(api: widget.api),
          ),
        );
        if (created == true) load();
      },
      icon: const Icon(Icons.add),
      label: const Text('Novo ajuste'),
    ),
    body: items == null && error == null
        ? const Center(child: CircularProgressIndicator())
        : RefreshIndicator(
            onRefresh: load,
            child: ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (error != null)
                  Text(
                    error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                if (items?.isEmpty == true)
                  const Card(
                    child: Padding(
                      padding: EdgeInsets.all(20),
                      child: Text('Nenhuma solicitação de ajuste.'),
                    ),
                  ),
                ...?items?.map((raw) {
                  final item = Map<String, dynamic>.from(raw as Map);
                  final status = item['status']?.toString();
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: Icon(
                        Icons.circle,
                        size: 14,
                        color: status == 'approved'
                            ? Colors.green
                            : status == 'pending'
                            ? Colors.amber
                            : Colors.red,
                      ),
                      title: Text(item['work_date']?.toString() ?? ''),
                      subtitle: Text(item['reason']?.toString() ?? ''),
                      trailing: Text(
                        status == 'approved'
                            ? 'Aprovado'
                            : status == 'pending'
                            ? 'Pendente'
                            : 'Cancelado',
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 72),
              ],
            ),
          ),
  );
}

class NewAdjustmentScreen extends StatefulWidget {
  const NewAdjustmentScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<NewAdjustmentScreen> createState() => _NewAdjustmentScreenState();
}

class _NewAdjustmentScreenState extends State<NewAdjustmentScreen> {
  final form = GlobalKey<FormState>();
  DateTime date = DateTime.now();
  TimeOfDay time = TimeOfDay.now();
  String type = 'clock_in';
  final reason = TextEditingController();
  bool loading = false;
  static const types = {
    'clock_in': 'Entrada',
    'break_start': 'Início do intervalo',
    'break_end': 'Fim do intervalo',
    'clock_out': 'Saída',
    'overtime_start': 'Hora extra — entrada',
    'overtime_end': 'Hora extra — saída',
  };
  Future<void> submit() async {
    if (!form.currentState!.validate()) return;
    setState(() => loading = true);
    try {
      await widget.api.createAdjustment(
        date: DateFormat('yyyy-MM-dd').format(date),
        type: type,
        time:
            '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}',
        reason: reason.text,
      );
      if (mounted) {
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.toString())));
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Novo ajuste')),
    body: Form(
      key: form,
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          DropdownButtonFormField(
            initialValue: type,
            decoration: const InputDecoration(labelText: 'Tipo de batida'),
            items: types.entries
                .map(
                  (entry) => DropdownMenuItem(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: (value) => setState(() => type = value!),
          ),
          const SizedBox(height: 16),
          ListTile(
            shape: RoundedRectangleBorder(
              side: BorderSide(color: Theme.of(context).colorScheme.outline),
              borderRadius: BorderRadius.circular(4),
            ),
            title: const Text('Data'),
            subtitle: Text(DateFormat('dd/MM/yyyy').format(date)),
            trailing: const Icon(Icons.calendar_today),
            onTap: () async {
              final selected = await showDatePicker(
                context: context,
                firstDate: DateTime(2020),
                lastDate: DateTime.now(),
                initialDate: date,
              );
              if (selected != null) setState(() => date = selected);
            },
          ),
          const SizedBox(height: 16),
          ListTile(
            shape: RoundedRectangleBorder(
              side: BorderSide(color: Theme.of(context).colorScheme.outline),
              borderRadius: BorderRadius.circular(4),
            ),
            title: const Text('Horário'),
            subtitle: Text(time.format(context)),
            trailing: const Icon(Icons.schedule),
            onTap: () async {
              final selected = await showTimePicker(
                context: context,
                initialTime: time,
              );
              if (selected != null) setState(() => time = selected);
            },
          ),
          const SizedBox(height: 16),
          TextFormField(
            controller: reason,
            minLines: 3,
            maxLines: 6,
            maxLength: 1000,
            decoration: const InputDecoration(labelText: 'Motivo'),
            validator: (value) => (value?.trim().length ?? 0) < 10
                ? 'Informe pelo menos 10 caracteres.'
                : null,
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
                  : const Text('Enviar para aprovação'),
            ),
          ),
        ],
      ),
    ),
  );
}
