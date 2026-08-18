<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\RecruitmentStage;
use Illuminate\Database\Seeder;

class RecruitmentStagesSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            ['name' => 'Teste DISC', 'public_name' => 'Teste comportamental DISC', 'public_description' => 'Questionário para apoiar o entendimento do seu perfil comportamental.', 'type' => 'assessment', 'candidate_action' => 'disc'],
            ['name' => 'Entrevista com RH', 'public_name' => 'Conversa com nossa equipe de RH', 'public_description' => 'Momento para conhecermos melhor sua experiência e suas expectativas.', 'type' => 'interview', 'candidate_action' => null],
            ['name' => 'Entrevista técnica com IA', 'public_name' => 'Entrevista técnica', 'public_description' => 'Etapa de perguntas relacionadas aos conhecimentos necessários para a oportunidade.', 'type' => 'interview', 'candidate_action' => 'ai_interview'],
            ['name' => 'Entrevista com gestor ou cliente', 'public_name' => 'Entrevista com o gestor da área', 'public_description' => 'Apresentação da área, da rotina e dos desafios da oportunidade.', 'type' => 'interview', 'candidate_action' => null],
            ['name' => 'Avaliação final', 'public_name' => 'Avaliação final', 'public_description' => 'A equipe responsável está consolidando as avaliações do processo.', 'type' => 'review', 'candidate_action' => null],
        ];

        Company::query()->each(function (Company $company) use ($stages): void {
            foreach ($stages as $index => $stage) {
                RecruitmentStage::query()->updateOrCreate(
                    ['company_id' => $company->id, 'name' => $stage['name']],
                    [...$stage, 'position' => $index + 1, 'active' => true, 'candidate_visible' => true],
                );
            }
        });
    }
}
