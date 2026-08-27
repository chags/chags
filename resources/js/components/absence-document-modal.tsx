import { FileText, X } from 'lucide-react';
import { useRef, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';

type DocumentType = 'medical_certificate' | 'absence_declaration';

export default function AbsenceDocumentModal() {
    const dialog = useRef<HTMLDialogElement>(null);
    const [documentType, setDocumentType] =
        useState<DocumentType>('medical_certificate');
    const [processing, setProcessing] = useState(false);
    const [message, setMessage] = useState('');

    const open = () => {
        setMessage('');
        dialog.current?.showModal();
    };

    const submit = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setProcessing(true);
        setMessage('');
        const form = event.currentTarget;
        const data = new FormData(form);
        if (documentType === 'medical_certificate') {
            data.delete('starts_at');
            data.delete('ends_at');
        } else {
            data.set('ends_on', String(data.get('starts_on') ?? ''));
        }

        try {
            const response = await fetch(
                '/virtual-office/medical-certificates',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN':
                            document.querySelector<HTMLMetaElement>(
                                'meta[name="csrf-token"]',
                            )?.content ?? '',
                    },
                    body: data,
                },
            );
            const result = await response.json();
            if (!response.ok) {
                throw new Error(
                    Object.values(result.errors ?? {})
                        .flat()
                        .join(' ') || result.message,
                );
            }

            form.reset();
            setDocumentType('medical_certificate');
            dialog.current?.close();
            toast.success(result.message, { duration: 18_000 });
        } catch (error) {
            setMessage(
                error instanceof Error
                    ? error.message
                    : 'Não foi possível enviar o documento.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <button type="button" className="btn btn-outline" onClick={open}>
                <FileText className="size-4" />
                Enviar atestado ou declaração
            </button>
            <dialog ref={dialog} className="modal">
                <div className="modal-box max-w-3xl">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-xl font-bold">
                                Documento de ausência
                            </h2>
                            <p className="mt-1 text-sm text-base-content/60">
                                O documento ficará pendente até a análise do
                                gestor.
                            </p>
                        </div>
                        <button
                            type="button"
                            className="btn btn-circle btn-ghost btn-sm"
                            aria-label="Fechar"
                            onClick={() => dialog.current?.close()}
                        >
                            <X className="size-4" />
                        </button>
                    </div>

                    <form className="mt-5 space-y-4" onSubmit={submit}>
                        {message && (
                            <div className="alert alert-error">{message}</div>
                        )}
                        <label className="fieldset">
                            <span className="fieldset-legend">
                                Tipo de documento
                            </span>
                            <select
                                name="type"
                                className="select w-full"
                                value={documentType}
                                onChange={(event) =>
                                    setDocumentType(
                                        event.target.value as DocumentType,
                                    )
                                }
                            >
                                <option value="medical_certificate">
                                    Atestado médico — dia inteiro
                                </option>
                                <option value="absence_declaration">
                                    Declaração de comparecimento — por horas
                                </option>
                            </select>
                        </label>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className="fieldset">
                                <span className="fieldset-legend">
                                    {documentType === 'medical_certificate'
                                        ? 'Data inicial'
                                        : 'Data da declaração'}
                                </span>
                                <input
                                    name="starts_on"
                                    type="date"
                                    className="input w-full"
                                    required
                                />
                            </label>
                            {documentType === 'medical_certificate' ? (
                                <label className="fieldset">
                                    <span className="fieldset-legend">
                                        Data final
                                    </span>
                                    <input
                                        name="ends_on"
                                        type="date"
                                        className="input w-full"
                                        required
                                    />
                                </label>
                            ) : (
                                <>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Início da ausência
                                        </span>
                                        <input
                                            name="starts_at"
                                            type="time"
                                            className="input w-full"
                                            required
                                        />
                                    </label>
                                    <label className="fieldset">
                                        <span className="fieldset-legend">
                                            Fim da ausência
                                        </span>
                                        <input
                                            name="ends_at"
                                            type="time"
                                            className="input w-full"
                                            required
                                        />
                                    </label>
                                </>
                            )}
                        </div>
                        <label className="fieldset">
                            <span className="fieldset-legend">Justificativa</span>
                            <textarea
                                name="reason"
                                className="textarea min-h-24 w-full"
                                minLength={10}
                                required
                            />
                        </label>
                        <label className="fieldset">
                            <span className="fieldset-legend">
                                Documento (PDF, JPG ou PNG; até 5 MB)
                            </span>
                            <input
                                name="document"
                                type="file"
                                accept="application/pdf,image/jpeg,image/png"
                                className="file-input w-full"
                                required
                            />
                        </label>
                        <div className="modal-action">
                            <button
                                type="button"
                                className="btn btn-ghost"
                                onClick={() => dialog.current?.close()}
                            >
                                Fechar
                            </button>
                            <button
                                className="btn btn-primary"
                                disabled={processing}
                            >
                                {processing ? 'Enviando…' : 'Enviar para análise'}
                            </button>
                        </div>
                    </form>
                </div>
                <form method="dialog" className="modal-backdrop">
                    <button>Fechar</button>
                </form>
            </dialog>
        </>
    );
}
