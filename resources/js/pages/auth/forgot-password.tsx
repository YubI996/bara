import { Form, Head } from '@inertiajs/react';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { StatusMessage } from '@/components/form/status-message';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

const LABELS = { email: 'Alamat email' };

export default function ForgotPassword({ status }: { status?: string }) {
    return (
        <>
            <Head title="Lupa kata sandi" />

            <div className="space-y-6">
                {status && <StatusMessage>{status}</StatusMessage>}

                <Form {...email.form()} className="grid gap-6" noValidate>
                    {({ processing, errors }) => (
                        <>
                            <ErrorSummary errors={errors} labels={LABELS} />
                            <Field
                                id="email"
                                label={LABELS.email}
                                required
                                error={errors.email}
                            >
                                {(aria) => (
                                    <Input
                                        {...aria}
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        autoFocus
                                        placeholder="email@example.com"
                                    />
                                )}
                            </Field>

                            <Button
                                className="w-full"
                                disabled={processing}
                                data-test="email-password-reset-link-button"
                            >
                                {processing && <Spinner />}
                                {processing
                                    ? 'Mengirim…'
                                    : 'Kirim tautan atur ulang kata sandi'}
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-sm text-muted-foreground">
                    Atau, kembali ke{' '}
                    <TextLink href={login()}>halaman masuk</TextLink>
                </p>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    title: 'Lupa kata sandi',
    description:
        'Masukkan alamat email Anda untuk menerima tautan atur ulang kata sandi',
};
