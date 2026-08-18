<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Curriculum;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ResumeExtractionService
{
    public function extract(Application $application): Curriculum
    {
        $curriculum = Curriculum::query()->firstOrCreate(
            ['application_id' => $application->id],
            ['status' => 'pending'],
        );
        $curriculum->forceFill([
            'extraction_status' => 'processing',
            'extraction_attempts' => $curriculum->extraction_attempts + 1,
            'extraction_error' => null,
            'last_attempted_at' => now(),
        ])->save();

        try {
            if (! $application->resume_path || ! Storage::disk('local')->exists($application->resume_path)) {
                throw new RuntimeException('O arquivo do currículo não foi encontrado.');
            }

            $extension = strtolower(pathinfo($application->resume_path, PATHINFO_EXTENSION));
            $path = Storage::disk('local')->path($application->resume_path);
            $text = match ($extension) {
                'pdf' => $this->extractWithCommand(['pdftotext', '-layout', $path, '-']),
                'doc' => $this->extractWithCommand(['antiword', $path]),
                'docx' => $this->extractDocx($path),
                default => throw new RuntimeException('Formato de currículo não suportado para extração.'),
            };
            $text = $this->normalize($text);

            if (mb_strlen($text) < 30) {
                throw new RuntimeException('Não foi possível identificar texto suficiente no currículo. O PDF pode ser uma imagem digitalizada.');
            }

            $curriculum->forceFill([
                'extraction_status' => 'completed',
                'evaluation_status' => 'pending',
                'extracted_text' => $text,
                'extracted_data' => $this->structure($text),
                'extracted_at' => now(),
                'extraction_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);
            $curriculum->forceFill([
                'extraction_status' => 'failed',
                'extraction_error' => Str::limit($exception->getMessage(), 1000),
            ])->save();
        }

        return $curriculum->refresh();
    }

    private function extractWithCommand(array $command): string
    {
        $result = Process::timeout(30)->run($command);

        if ($result->failed()) {
            throw new RuntimeException('O extrator não conseguiu ler o arquivo enviado.');
        }

        return $result->output();
    }

    private function extractDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não foi possível abrir o arquivo DOCX.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (! is_string($xml)) {
            throw new RuntimeException('O documento DOCX não contém texto legível.');
        }

        $xml = str_replace(['</w:p>', '</w:tr>', '<w:tab/>'], ["\n", "\n", "\t"], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalize(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\R{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function structure(string $text): array
    {
        preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $email);
        preg_match('/(?:\+?55\s*)?(?:\(?\d{2}\)?\s*)?9?\d{4}[\s.\-]?\d{4}/', $text, $phone);
        preg_match_all('/https?:\/\/[^\s]+|(?:www\.)?linkedin\.com\/[^\s]+/i', $text, $links);
        $lines = collect(preg_split('/\R/', $text))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();
        $name = $lines->first(fn (string $line) => mb_strlen($line) >= 3
            && mb_strlen($line) <= 100
            && ! str_contains($line, '@')
            && ! preg_match('/\d{4}/', $line));

        $sections = ['summary' => [], 'experience' => [], 'education' => [], 'courses' => [], 'skills' => [], 'languages' => []];
        $current = 'summary';
        $headings = [
            'experience' => '/experi[eê]ncia|hist[oó]rico profissional|atua[cç][aã]o/i',
            'education' => '/forma[cç][aã]o|escolaridade|acad[eê]mic/i',
            'courses' => '/cursos?|certifica[cç][oõ]es?/i',
            'skills' => '/habilidades?|compet[eê]ncias?|conhecimentos?/i',
            'languages' => '/idiomas?/i',
            'summary' => '/resumo|perfil|objetivo/i',
        ];

        foreach ($lines as $line) {
            $heading = collect($headings)->search(fn (string $pattern) => mb_strlen($line) < 60 && preg_match($pattern, $line));
            if ($heading !== false) {
                $current = $heading;

                continue;
            }
            $sections[$current][] = $line;
        }

        return [
            'personal' => ['name' => $name, 'email' => $email[0] ?? null, 'phone' => $phone[0] ?? null, 'links' => array_values(array_unique($links[0] ?? []))],
            'professional_summary' => $sections['summary'],
            'experience' => $sections['experience'],
            'education' => $sections['education'],
            'courses_and_certifications' => $sections['courses'],
            'skills' => $sections['skills'],
            'languages' => $sections['languages'],
            'metadata' => ['characters' => mb_strlen($text), 'extractor_version' => 1],
        ];
    }
}
