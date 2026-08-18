import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ShieldCheck } from 'lucide-react';
import { PublicSiteShell } from '@/components/public-site-shell';

export default function Privacy() {
    return (
        <PublicSiteShell>
            <Head title="Privacidade e LGPD" />
            <main className="bg-base-100">
                <section className="bg-primary px-4 py-16 text-center text-primary-content">
                    <ShieldCheck className="mx-auto size-14 opacity-80" />
                    <h1 className="mt-4 text-4xl font-black">
                        Aviso de Privacidade e LGPD
                    </h1>
                    <p className="mx-auto mt-3 max-w-2xl text-primary-content/75">
                        Transparência sobre o tratamento de dados em processos
                        seletivos.
                    </p>
                </section>
                <article className="prose-headings:text-base-content prose-p:text-base-content/75 prose-li:text-base-content/75 mx-auto prose max-w-4xl px-4 py-12">
                    <Link
                        href="/trabalhe-conosco"
                        className="not-prose btn mb-6 btn-ghost btn-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Voltar às vagas
                    </Link>
                    <h2>1. Finalidade</h2>
                    <p>
                        Os dados informados na candidatura são utilizados para
                        identificar o candidato, avaliar sua adequação à vaga,
                        realizar comunicações sobre o processo seletivo e,
                        quando aplicável, conduzir etapas de admissão.
                    </p>
                    <h2>2. Dados tratados</h2>
                    <p>
                        Podemos tratar nome, e-mail, telefone, cidade, estado,
                        currículo, apresentação profissional, histórico da
                        candidatura e informações fornecidas durante entrevistas
                        e avaliações.
                    </p>
                    <h2>3. Consentimento</h2>
                    <p>
                        O envio da candidatura exige uma manifestação livre e
                        inequívoca por meio da caixa de aceite. Registramos a
                        data, a versão deste aviso e o endereço IP associado ao
                        aceite para fins de comprovação.
                    </p>
                    <h2>4. Armazenamento e segurança</h2>
                    <p>
                        Currículos são mantidos em armazenamento privado e
                        acessados apenas por pessoas autorizadas. Aplicamos
                        controles de acesso, registro de operações e medidas
                        técnicas compatíveis com a natureza dos dados.
                    </p>
                    <h2>5. Retenção</h2>
                    <p>
                        Os dados serão mantidos por até 12 meses após o
                        encerramento do processo seletivo. Após esse prazo,
                        serão excluídos ou anonimizados, salvo consentimento
                        específico para permanência adicional no banco de
                        talentos ou obrigação legal de conservação.
                    </p>
                    <h2>6. Compartilhamento</h2>
                    <p>
                        Os dados podem ser compartilhados internamente com
                        profissionais de RH e gestores envolvidos na vaga,
                        sempre no limite necessário ao processo. Fornecedores
                        essenciais somente poderão tratar dados sob obrigações
                        de segurança e confidencialidade.
                    </p>
                    <h2>7. Direitos do titular</h2>
                    <p>
                        Você pode solicitar confirmação do tratamento, acesso,
                        correção, informação sobre compartilhamento,
                        anonimização, bloqueio ou eliminação quando aplicável,
                        além da revogação do consentimento. Certas solicitações
                        podem estar sujeitas às hipóteses legais de conservação.
                    </p>
                    <h2>8. Referências oficiais</h2>
                    <ul>
                        <li>
                            <a
                                href="https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados-1"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Autoridade Nacional de Proteção de Dados —
                                Titular de Dados
                            </a>
                        </li>
                        <li>
                            <a
                                href="https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Lei nº 13.709/2018 — texto oficial
                            </a>
                        </li>
                    </ul>
                    <p className="text-sm">
                        Versão deste aviso: 11 de agosto de 2026.
                    </p>
                </article>
            </main>
        </PublicSiteShell>
    );
}
