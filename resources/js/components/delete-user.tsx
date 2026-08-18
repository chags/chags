import { Form } from '@inertiajs/react';
import { destroy } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export default function DeleteUser() {
    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Excluir conta"
                description="Exclua sua conta e todos os dados associados"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                    <p className="font-medium">Atenção</p>
                    <p className="text-sm">
                        Prossiga com cuidado. Esta ação não pode ser desfeita.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="destructive">Excluir conta</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Tem certeza de que deseja excluir sua conta?
                        </DialogTitle>
                        <DialogDescription>
                            Ao excluir sua conta, todos os dados associados
                            também serão removidos permanentemente. Confirme que
                            deseja excluir sua conta.
                        </DialogDescription>
                        <Form
                            {...destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing }) => (
                                <>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button type="submit">
                                                Excluir conta
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
