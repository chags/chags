<?php

namespace App\Services;

use App\Models\DiscAssessment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DiscScoringService
{
    private const DESCRIPTIONS = [
        'D' => ['label' => 'Dominância', 'description' => 'Você tende a agir com objetividade, iniciativa e foco em resultados. Desafios, autonomia e metas claras podem estimular seu desempenho. Como ponto de atenção, reserve espaço para ouvir perspectivas diferentes e avaliar riscos antes de avançar.'],
        'I' => ['label' => 'Influência', 'description' => 'Você tende a demonstrar energia social, facilidade de comunicação e disposição para envolver pessoas. Como ponto de atenção, registre combinados, acompanhe detalhes e equilibre otimismo com análise prática.'],
        'S' => ['label' => 'Estabilidade', 'description' => 'Você tende a valorizar cooperação, confiança, constância e relações respeitosas. Como ponto de atenção, expresse discordâncias, sinalize limites e aja mesmo quando nem todas as condições parecem ideais.'],
        'C' => ['label' => 'Conformidade', 'description' => 'Você tende a valorizar precisão, qualidade, lógica e critérios definidos. Como ponto de atenção, equilibre aprofundamento com prazos e compartilhe suas conclusões de forma acessível.'],
    ];

    public function complete(DiscAssessment $assessment): DiscAssessment
    {
        return DB::transaction(function () use ($assessment) {
            $assessment = DiscAssessment::query()
                ->lockForUpdate()
                ->findOrFail($assessment->id);
            if ($assessment->status === 'completed') {
                throw new RuntimeException('Este teste já foi concluído e não pode ser refeito.');
            }

            $answers = $assessment->answers()->with('option')->get();
            if ($answers->count() !== 20) {
                throw new RuntimeException('Responda às 20 perguntas antes de concluir o teste.');
            }

            $scores = ['D' => 0, 'I' => 0, 'S' => 0, 'C' => 0];
            foreach ($answers as $answer) {
                $scores[$answer->option->dimension] += $answer->option->weight;
            }
            arsort($scores);
            $highest = max($scores);
            $dominants = array_keys(array_filter($scores, fn (int $score) => $score === $highest));
            $profile = implode('', $dominants);
            $secondary = array_keys($scores)[count($dominants)] ?? null;
            $snapshot = $this->snapshot($profile, $scores);

            $assessment->update([
                'status' => 'completed',
                'd_score' => $scores['D'], 'i_score' => $scores['I'],
                's_score' => $scores['S'], 'c_score' => $scores['C'],
                'dominant_profile' => $profile,
                'secondary_profile' => $secondary,
                'result_snapshot' => $snapshot,
                'completed_at' => now(),
                'current_position' => 20,
            ]);

            return $assessment->refresh();
        });
    }

    private function snapshot(string $profile, array $scores): array
    {
        $dimensions = str_split($profile);
        if (count($dimensions) === 1) {
            $description = self::DESCRIPTIONS[$profile]['description'];
            $label = 'Perfil '.$profile.' — '.self::DESCRIPTIONS[$profile]['label'];
        } else {
            $names = array_map(fn (string $item) => self::DESCRIPTIONS[$item]['label'], $dimensions);
            $label = 'Perfil '.$profile.' — '.implode(' e ', $names);
            $description = 'Suas respostas indicam tendências com intensidade semelhante: '.implode(', ', $names).'. Seu comportamento pode combinar características desses estilos conforme o contexto.';
        }

        return [
            'profile' => $profile,
            'label' => $label,
            'description' => $description,
            'scores' => $scores,
            'percentages' => collect($scores)->map(fn (int $score) => $score * 5)->all(),
            'disclaimer' => 'Este resultado descreve preferências comportamentais e não constitui diagnóstico psicológico nem determina capacidade profissional.',
        ];
    }
}
