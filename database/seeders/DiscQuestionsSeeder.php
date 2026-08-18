<?php

namespace Database\Seeders;

use App\Models\DiscOption;
use App\Models\DiscQuestion;
use Illuminate\Database\Seeder;

class DiscQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            ['Quando surge um desafio inesperado no trabalho, minha tendência inicial é:', ['D' => 'Assumir a frente e buscar uma solução rápida.', 'I' => 'Conversar com as pessoas e mobilizar o grupo.', 'S' => 'Manter a calma e ajudar a equipe a se organizar.', 'C' => 'Levantar os fatos antes de decidir o caminho.']],
            ['Ao receber uma tarefa nova, eu prefiro:', ['C' => 'Entender critérios, detalhes e resultado esperado.', 'D' => 'Ter autonomia para decidir como executá-la.', 'S' => 'Saber como ela se integra à rotina da equipe.', 'I' => 'Trocar ideias e explorar possibilidades com outras pessoas.']],
            ['Durante uma reunião, geralmente contribuo mais quando:', ['I' => 'Posso apresentar ideias e estimular a participação.', 'C' => 'Analiso as propostas e identifico inconsistências.', 'D' => 'Ajudo o grupo a tomar uma decisão objetiva.', 'S' => 'Escuto os envolvidos e procuro construir consenso.']],
            ['Quando o prazo está apertado, costumo:', ['S' => 'Preservar a cooperação e manter um ritmo constante.', 'D' => 'Priorizar o essencial e avançar com rapidez.', 'C' => 'Organizar etapas para reduzir erros mesmo sob pressão.', 'I' => 'Manter o entusiasmo e pedir apoio quando necessário.']],
            ['Em um projeto com pouca orientação, eu normalmente:', ['D' => 'Defino uma direção e começo a agir.', 'S' => 'Busco alinhar expectativas antes de avançar.', 'I' => 'Procuro pessoas para pensar em alternativas.', 'C' => 'Reúno informações e estabeleço um método.']],
            ['Quando preciso convencer alguém sobre uma proposta, eu:', ['C' => 'Apresento evidências, riscos e critérios objetivos.', 'I' => 'Uso entusiasmo e adapto a conversa à pessoa.', 'S' => 'Demonstro como a proposta beneficia o grupo.', 'D' => 'Destaco resultados e a necessidade de agir.']],
            ['Em mudanças de processo, o que mais me ajuda é:', ['S' => 'Ter tempo para compreender e ajustar a rotina.', 'I' => 'Participar das conversas e compartilhar expectativas.', 'D' => 'Entender rapidamente o objetivo e partir para a execução.', 'C' => 'Receber regras, impactos e procedimentos bem definidos.']],
            ['Quando identifico um erro importante, minha reação mais comum é:', ['D' => 'Corrigir imediatamente e evitar impacto no resultado.', 'C' => 'Investigar a causa e revisar o procedimento.', 'I' => 'Conversar de forma aberta para encontrar uma saída.', 'S' => 'Apoiar os envolvidos e corrigir sem gerar conflito.']],
            ['Em atividades de equipe, sinto-me mais confortável:', ['I' => 'Criando conexões e mantendo todos engajados.', 'S' => 'Oferecendo suporte e promovendo colaboração.', 'C' => 'Cuidando da organização e da qualidade das entregas.', 'D' => 'Direcionando prioridades e cobrando avanços.']],
            ['Ao tomar uma decisão relevante, eu valorizo mais:', ['C' => 'Dados confiáveis e análise cuidadosa.', 'S' => 'Estabilidade e impacto sobre as pessoas.', 'D' => 'Velocidade e potencial de resultado.', 'I' => 'Opiniões, possibilidades e aceitação do grupo.']],
            ['Quando recebo uma crítica, geralmente prefiro:', ['S' => 'Uma conversa respeitosa e reservada.', 'D' => 'Uma mensagem direta com o que precisa mudar.', 'I' => 'Um diálogo aberto que também reconheça os acertos.', 'C' => 'Exemplos específicos e critérios claros.']],
            ['Ao iniciar o dia de trabalho, minha prioridade costuma ser:', ['D' => 'Atacar primeiro o objetivo de maior impacto.', 'I' => 'Alinhar pessoas e assuntos que dependem de interação.', 'C' => 'Organizar tarefas, prazos e padrões necessários.', 'S' => 'Dar continuidade ao combinado e apoiar demandas do time.']],
            ['Em uma negociação, minha abordagem natural é:', ['I' => 'Criar proximidade e manter a conversa dinâmica.', 'D' => 'Defender o objetivo e buscar um acordo rápido.', 'S' => 'Procurar uma solução equilibrada para todos.', 'C' => 'Examinar condições e evitar compromissos imprecisos.']],
            ['Quando uma equipe está desmotivada, eu tendo a:', ['C' => 'Identificar problemas concretos no processo.', 'S' => 'Escutar as pessoas e oferecer apoio consistente.', 'I' => 'Reanimar o grupo com energia e reconhecimento.', 'D' => 'Reforçar metas e provocar uma reação prática.']],
            ['Se preciso aprender algo novo, prefiro:', ['S' => 'Avançar gradualmente com orientação disponível.', 'C' => 'Estudar materiais e compreender os fundamentos.', 'D' => 'Experimentar na prática e ajustar rapidamente.', 'I' => 'Aprender por conversas, exemplos e troca de experiências.']],
            ['Quando há opiniões muito diferentes, eu normalmente:', ['D' => 'Enfrento a questão e conduzo para uma decisão.', 'C' => 'Comparo argumentos e procuro coerência.', 'S' => 'Reduzo tensões e busco pontos em comum.', 'I' => 'Facilito a conversa e incentivo novas ideias.']],
            ['Meu ambiente de trabalho ideal oferece:', ['I' => 'Contato com pessoas, variedade e espaço para expressão.', 'C' => 'Clareza, qualidade e oportunidade de aprofundamento.', 'D' => 'Autonomia, desafios e metas ambiciosas.', 'S' => 'Cooperação, previsibilidade e relações de confiança.']],
            ['Quando delego uma atividade, costumo:', ['C' => 'Explicar critérios e acompanhar a qualidade.', 'D' => 'Definir o resultado e dar liberdade para executar.', 'I' => 'Transmitir entusiasmo e manter comunicação frequente.', 'S' => 'Garantir que a pessoa tenha apoio e segurança.']],
            ['Diante de uma oportunidade arriscada, eu:', ['S' => 'Avalio como preservar estabilidade durante a mudança.', 'I' => 'Exploro o potencial com outras pessoas.', 'D' => 'Considero o ganho e aceito agir com incerteza.', 'C' => 'Analiso cenários, dados e possíveis consequências.']],
            ['Ao concluir um projeto, o que mais me satisfaz é:', ['D' => 'Ver que uma meta desafiadora foi alcançada.', 'S' => 'Perceber que a equipe trabalhou de forma harmoniosa.', 'C' => 'Entregar algo correto, consistente e bem executado.', 'I' => 'Celebrar o resultado e o envolvimento das pessoas.']],
        ];

        foreach ($questions as $index => [$prompt, $options]) {
            $position = $index + 1;
            $question = DiscQuestion::query()->updateOrCreate(
                ['code' => sprintf('disc_q%02d', $position)],
                ['position' => $position, 'prompt' => $prompt, 'active' => true, 'version' => '1.0'],
            );
            foreach ($options as $order => $text) {
                $dimension = $order;
                DiscOption::query()->updateOrCreate(
                    ['code' => sprintf('disc_q%02d_%s', $position, strtolower($dimension))],
                    ['disc_question_id' => $question->id, 'text' => $text, 'dimension' => $dimension, 'weight' => 1, 'display_order' => array_search($dimension, array_keys($options), true) + 1],
                );
            }
        }
    }
}
