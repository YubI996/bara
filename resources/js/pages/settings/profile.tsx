import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { StatusMessage } from '@/components/form/status-message';
import Heading from '@/components/heading';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
};

const LABELS = {
    name: 'Nama',
    email: 'Alamat email',
    current_password: 'Kata sandi saat ini',
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const [email, setEmail] = useState(auth.user.email);
    const emailChanged =
        email.trim().toLowerCase() !== auth.user.email.toLowerCase();

    return (
        <>
            <Head title="Pengaturan profil" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profil"
                    description="Perbarui nama dan alamat email Anda"
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['current_password']}
                    className="space-y-6"
                    noValidate
                >
                    {({ processing, errors }) => (
                        <>
                            <ErrorSummary errors={errors} labels={LABELS} />

                            <Field
                                id="name"
                                label={LABELS.name}
                                required
                                error={errors.name}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        defaultValue={auth.user.name}
                                        name="name"
                                        autoComplete="name"
                                    />
                                )}
                            </Field>

                            <Field
                                id="email"
                                label={LABELS.email}
                                required
                                hint="Mengganti alamat email memerlukan kata sandi saat ini dan verifikasi ulang."
                                error={errors.email}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        type="email"
                                        value={email}
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                        name="email"
                                        autoComplete="username"
                                    />
                                )}
                            </Field>

                            {emailChanged && (
                                <Field
                                    id="current_password"
                                    label={LABELS.current_password}
                                    required
                                    error={errors.current_password}
                                >
                                    {(aria) => (
                                        <PasswordInput
                                            {...aria}
                                            name="current_password"
                                            autoComplete="current-password"
                                        />
                                    )}
                                </Field>
                            )}

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div className="space-y-2">
                                        <p className="text-sm text-muted-foreground">
                                            Alamat email Anda belum
                                            terverifikasi.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline underline-offset-4"
                                            >
                                                Kirim ulang email verifikasi
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <StatusMessage>
                                                Tautan verifikasi baru sudah
                                                dikirim ke alamat email Anda.
                                            </StatusMessage>
                                        )}
                                    </div>
                                )}

                            <Button
                                disabled={processing}
                                data-test="update-profile-button"
                            >
                                {processing ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan profil',
            href: edit(),
        },
    ],
};
