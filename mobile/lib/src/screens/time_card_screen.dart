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
    } catch (exception) {
      error = exception.toString();
    }
    if (mounted) setState(() {});
  }

  void change(int delta) {
    month = DateTime(month.year, month.month + delta);
    load();
  }

  @override
  Widget build(BuildContext context) {
    final days = (data?['days'] as List?) ?? const [];
    return Scaffold(
      appBar: AppBar(title: const Text('Cartão de ponto')),
      body: Column(
        children: [
          _MonthSelector(month: month, onChange: change),
          if (data != null) _MonthSummary(data: data!),
          if (error != null)
            Padding(
              padding: const EdgeInsets.all(20),
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
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const [
                          SizedBox(height: 80),
                          Icon(Icons.event_busy_outlined, size: 48),
                          SizedBox(height: 12),
                          Center(child: Text('Não há registros neste mês.')),
                        ],
                      )
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                        itemCount: days.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 10),
                        itemBuilder: (_, index) => _DayCard(
                          day: Map<String, dynamic>.from(days[index] as Map),
                        ),
                      ),
              ),
            ),
        ],
      ),
    );
  }
}

class _MonthSelector extends StatelessWidget {
  const _MonthSelector({required this.month, required this.onChange});
  final DateTime month;
  final ValueChanged<int> onChange;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
    child: Row(
      children: [
        IconButton.filledTonal(
          onPressed: () => onChange(-1),
          tooltip: 'Mês anterior',
          icon: const Icon(Icons.chevron_left),
        ),
        Expanded(
          child: Text(
            _capitalize(DateFormat('MMMM yyyy', 'pt_BR').format(month)),
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
          ),
        ),
        IconButton.filledTonal(
          onPressed: () => onChange(1),
          tooltip: 'Próximo mês',
          icon: const Icon(Icons.chevron_right),
        ),
      ],
    ),
  );
}

class _MonthSummary extends StatelessWidget {
  const _MonthSummary({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final worked = (data['workedMinutes'] as num?)?.toInt() ?? 0;
    final expected = (data['expectedMinutes'] as num?)?.toInt() ?? 0;
    final balance = (data['monthBalanceMinutes'] as num?)?.toInt() ?? 0;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        decoration: BoxDecoration(
          color: const Color(0xFF123524),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Row(
          children: [
            _SummaryItem(label: 'Trabalhado', value: _minutes(worked)),
            const _SummaryDivider(),
            _SummaryItem(label: 'Previsto', value: _minutes(expected)),
            const _SummaryDivider(),
            _SummaryItem(
              label: 'Saldo',
              value: '${balance >= 0 ? '+' : '-'}${_minutes(balance.abs())}',
              highlight: true,
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.label,
    required this.value,
    this.highlight = false,
  });
  final String label;
  final String value;
  final bool highlight;
  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          label,
          style: const TextStyle(color: Colors.white70, fontSize: 11),
        ),
        const SizedBox(height: 3),
        Text(
          value,
          style: TextStyle(
            color: highlight ? const Color(0xFF86EFAC) : Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 13,
            fontFeatures: const [FontFeature.tabularFigures()],
          ),
        ),
      ],
    ),
  );
}

class _SummaryDivider extends StatelessWidget {
  const _SummaryDivider();
  @override
  Widget build(BuildContext context) => Container(
    height: 32,
    width: 1,
    color: Colors.white.withValues(alpha: .18),
  );
}

class _DayCard extends StatelessWidget {
  const _DayCard({required this.day});
  final Map<String, dynamic> day;

  @override
  Widget build(BuildContext context) {
    final date = DateTime.tryParse(day['date']?.toString() ?? '');
    final entries = ((day['entries'] as List?) ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final pending = ((day['pendingEntries'] as List?) ?? const [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    return Card(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(color: Colors.black.withValues(alpha: .05)),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 58,
              child: Column(
                children: [
                  Text(
                    date == null ? '--' : DateFormat('dd').format(date),
                    style: const TextStyle(
                      fontSize: 30,
                      height: 1,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF123524),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    date == null
                        ? '---'
                        : DateFormat(
                            'MMM',
                            'pt_BR',
                          ).format(date).replaceAll('.', '').toUpperCase(),
                    style: const TextStyle(
                      color: Color(0xFF4D6B59),
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    date == null ? '' : DateFormat('EEE', 'pt_BR').format(date),
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 11),
                  ),
                ],
              ),
            ),
            Container(
              width: 1,
              constraints: const BoxConstraints(minHeight: 62),
              margin: const EdgeInsets.only(right: 14),
              color: Colors.black.withValues(alpha: .08),
            ),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (entries.isNotEmpty || pending.isNotEmpty)
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      children: [
                        ...entries.map((entry) => _EntryChip(entry: entry)),
                        ...pending.map(
                          (entry) => _EntryChip(entry: entry, pending: true),
                        ),
                      ],
                    )
                  else
                    _EmptyDay(occurrence: day['occurrence']?.toString()),
                  if (entries.isNotEmpty && date != null) ...[
                    const SizedBox(height: 10),
                    Text(
                      '${DateFormat('dd/MM/yyyy').format(date)}  •  '
                      '${_minutes((day['workedMinutes'] as num?)?.toInt() ?? 0)} trabalhadas',
                      style: TextStyle(
                        color: Colors.grey.shade600,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _EntryChip extends StatelessWidget {
  const _EntryChip({required this.entry, this.pending = false});
  final Map<String, dynamic> entry;
  final bool pending;

  @override
  Widget build(BuildContext context) {
    final type = entry['type']?.toString();
    final color = _entryColor(type, pending);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: .11),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withValues(alpha: .28)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(_entryIcon(type), size: 15, color: color),
          const SizedBox(width: 6),
          Text(
            entry['time']?.toString() ?? '--:--',
            style: TextStyle(fontWeight: FontWeight.w800, color: color),
          ),
          const SizedBox(width: 5),
          Text(
            pending ? '${_entryLabel(type)} (pendente)' : _entryLabel(type),
            style: TextStyle(color: color, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _EmptyDay extends StatelessWidget {
  const _EmptyDay({required this.occurrence});
  final String? occurrence;
  @override
  Widget build(BuildContext context) {
    final label = switch (occurrence) {
      'missing' => 'Batidas não registradas',
      'holiday' => 'Feriado',
      'medical_leave' => 'Afastamento justificado',
      'medical_pending' => 'Justificativa pendente',
      'hour_bank_leave' => 'Folga por banco de horas',
      _ => 'Sem batidas',
    };
    return Row(
      children: [
        Icon(Icons.schedule_outlined, size: 18, color: Colors.grey.shade500),
        const SizedBox(width: 7),
        Text(label, style: TextStyle(color: Colors.grey.shade600)),
      ],
    );
  }
}

String _entryLabel(String? type) =>
    const {
      'clock_in': 'Entrada',
      'break_start': 'Início intervalo',
      'break_end': 'Fim intervalo',
      'clock_out': 'Saída',
      'overtime_start': 'Início hora extra',
      'overtime_end': 'Fim hora extra',
    }[type] ??
    'Batida';

IconData _entryIcon(String? type) => switch (type) {
  'clock_in' => Icons.login,
  'break_start' => Icons.coffee_outlined,
  'break_end' => Icons.coffee,
  'clock_out' => Icons.logout,
  _ => Icons.schedule,
};

Color _entryColor(String? type, bool pending) {
  if (pending) return const Color(0xFFB45309);
  return switch (type) {
    'clock_in' => const Color(0xFF15803D),
    'break_start' => const Color(0xFFC2410C),
    'break_end' => const Color(0xFF0369A1),
    'clock_out' => const Color(0xFF6D28D9),
    _ => const Color(0xFF475569),
  };
}

String _minutes(int value) =>
    '${value ~/ 60}h ${(value % 60).toString().padLeft(2, '0')}min';

String _capitalize(String value) => value.isEmpty
    ? value
    : '${value.substring(0, 1).toUpperCase()}${value.substring(1)}';
