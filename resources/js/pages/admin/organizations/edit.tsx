import { Form, Head } from '@inertiajs/react';
import OrganizationController from '@/actions/App/Modules/Organization/Http/Controllers/OrganizationController';
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
import type { Option, Organization, ParentOption } from '@/types';
import { OrganizationForm } from './organization-form';

type Props = {
    organization: Organization;
    parents: ParentOption[];
    kinds: Option[];
    can: { deactivate: boolean };
};

export default function EditOrganization({
    organization,
    parents,
    kinds,
    can,
}: Props) {
    return (
        <>
            <Head title={`Ubah ${organization.name}`} />
            <div className="space-y-8 p-4 md:p-6">
                <header className="space-y-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Ubah unit: {organization.name}
                    </h1>
                    <p className="text-muted-foreground">
                        Memindahkan unit ke induk lain ikut memindahkan seluruh
                        unit di bawahnya beserta datanya.
                    </p>
                </header>

                <OrganizationForm
                    action={OrganizationController.update.form(organization)}
                    parents={parents}
                    kinds={kinds}
                    organization={organization}
                    submitLabel="Simpan perubahan"
                />

                {can.deactivate && (
                    <section
                        aria-labelledby="deactivate-title"
                        className="max-w-2xl space-y-3 rounded-lg border border-red-300 p-4 dark:border-red-800"
                    >
                        <h2 id="deactivate-title" className="font-semibold">
                            Nonaktifkan unit
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Unit nonaktif tidak bisa dipilih untuk data baru dan
                            penugasan di unit ini berhenti berlaku. Data
                            historis tetap tersimpan. Semua unit di bawahnya
                            harus dinonaktifkan atau dipindahkan terlebih
                            dahulu.
                        </p>
                        <Dialog>
                            <DialogTrigger asChild>
                                <Button
                                    variant="destructive"
                                    className="min-h-11 md:min-h-9"
                                >
                                    Nonaktifkan unit…
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogTitle>
                                    Nonaktifkan {organization.name}?
                                </DialogTitle>
                                <DialogDescription>
                                    Tindakan ini tercatat di audit log. Unit
                                    dapat dibuat ulang dengan kode lain bila
                                    diperlukan.
                                </DialogDescription>
                                <Form
                                    {...OrganizationController.deactivate.form(
                                        organization,
                                    )}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            {errors.organization && (
                                                <p
                                                    role="alert"
                                                    className="mb-4 text-sm font-medium text-red-700 dark:text-red-300"
                                                >
                                                    {errors.organization}
                                                </p>
                                            )}
                                            <DialogFooter className="gap-2">
                                                <DialogClose asChild>
                                                    <Button
                                                        type="button"
                                                        variant="secondary"
                                                        className="min-h-11 md:min-h-9"
                                                    >
                                                        Batal
                                                    </Button>
                                                </DialogClose>
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    disabled={processing}
                                                    className="min-h-11 md:min-h-9"
                                                >
                                                    Ya, nonaktifkan
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    </section>
                )}
            </div>
        </>
    );
}

EditOrganization.layout = {
    breadcrumbs: [
        { title: 'Organisasi', href: OrganizationController.index() },
        { title: 'Ubah unit', href: '#' },
    ],
};
