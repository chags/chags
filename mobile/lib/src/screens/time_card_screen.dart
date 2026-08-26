import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';

class TimeCardScreen extends StatefulWidget {
  const TimeCardScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<TimeCardScreen> createState() => _TimeCardScreenState();
}

class _TimeCardScreenState extends State<TimeCardScreen> {
  DateTime month = DateTime(DateTime.now().year, DateTime.now().month);
  Map<String, dynamic>? data;
  String? error;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      data = null;
      error = null;
    });
    try {
      data = await widget.api.timeCard(DateFormat('yyyy-MM').format(month));
    } catch (e) {
      error = e.toString();
    }
    if (mounted) setState(() {});
  }

  void change(int delta) {
    month = DateTime(month.year, month.month + delta);
    load();
  }

  @override
  Widget build(BuildContext context) {
    final days =
        (data?['days'] as List?) ?? (data?['records'] as List?) ?? const [];
    return Scaffold(
      appBar: AppBar(title: const Text('Cartão de ponto')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  onPressed: () => change(-1),
                  icon: const Icon(Icons.chevron_left),
                ),
                Text(
                  DateFormat('MMMM yyyy', 'pt_BR').format(month),
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                IconButton(
                  onPressed: () => change(1),
                  icon: const Icon(Icons.chevron_right),
                ),
              ],
            ),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(16),
              child: Text(
                error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          if (data == null && error == null)
            const Expanded(child: Center(child: CircularProgressIndicator()))
          else
            Expanded(
              child: RefreshIndicator(
                onRefresh: load,
                child: days.isEmpty
                    ? ListView(
                        children: const [
                          Padding(
                            padding: EdgeInsets.all(24),
                            child: Text('Não há registros para este mês.'),
                          ),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                        itemCount: days.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 8),
                        itemBuilder: (_, index) {
                          final day = Map<String, dynamic>.from(
                            days[index] as Map,
                          );
                          final entries = (day['entries'] as List?) ?? const [];
                          return Card(
                            child: ExpansionTile(
                              title: Text(
                                day['date']?.toString() ??
                                    day['work_date']?.toString() ??
                                    'Dia',
                              ),
                              subtitle: Text(
                                day['schedule']?.toString() ??
                                    day['journey']?.toString() ??
                                    '',
                              ),
                              trailing: Text(
                                day['worked']?.toString() ??
                                    day['worked_time']?.toString() ??
                                    '',
                              ),
                              children: entries.map((raw) {
                                final entry = Map<String, dynamic>.from(
                                  raw as Map,
                                );
                                return ListTile(
                                  dense: true,
                                  title: Text(entry['type']?.toString() ?? ''),
                                  trailing: Text(
                                    entry['time']?.toString() ?? '--:--',
                                  ),
                                );
                              }).toList(),
                            ),
                          );
                        },
                      ),
              ),
            ),
        ],
      ),
    );
  }
}
