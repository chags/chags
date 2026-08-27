<?php

namespace App\Console\Commands;

use App\Models\AbsenceJustification;
use App\Services\VirtualOffice\MedicalCertificateApprovalService;
use Illuminate\Console\Command;
use Throwable;

class SyncApprovedMedicalCertificates extends Command
{
    protected $signature = 'medical-certificates:sync-approved {--id=* : IDs específicos dos atestados}';

    protected $description = 'Cria marcações abonadas ausentes para atestados médicos já aprovados';

    public function handle(MedicalCertificateApprovalService $service): int
    {
        $query = AbsenceJustification::query()
            ->where('type', 'medical_certificate')
            ->where('status', 'approved')
            ->with(['user', 'reviewer']);

        $ids = array_filter(array_map('intval', $this->option('id')));
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $created = 0;
        $failed = 0;
        $query->orderBy('id')->each(function (AbsenceJustification $certificate) use ($service, &$created, &$failed): void {
            try {
                $count = $service->syncApproved($certificate);
                $created += $count;
                $this->line("Atestado #{$certificate->id}: {$count} marcação(ões) criada(s).");
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Atestado #{$certificate->id}: {$exception->getMessage()}");
            }
        });

        $this->newLine();
        $this->info("Sincronização concluída: {$created} marcação(ões) criada(s), {$failed} atestado(s) com conflito.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
