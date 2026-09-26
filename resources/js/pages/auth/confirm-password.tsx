import { Form, Head } from '@inertiajs/react';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

const LABELS = { password: 'Kata sandi' };

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Konfirmasi kata sandi" />

            <Form {...store.form()} resetOnSuccess={['password']} noValidate>
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <ErrorSummary errors={errors} labels={LABELS} />

                        <Field
                            id="password"
                            label={LABELS.password}
                            required
                            error={errors.password}
                        >
                            {(aria) => (
                                <PasswordInput
                                    {...aria}
                                    name="password"
                                    autoComplete="current-password"
                                    autoFocus
                                />
                            )}
                        </Field>

                        <Button
                            className="w-full"
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Memeriksa…'
                                : 'Konfirmasi kata sandi'}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ConfirmPassword.layout = {
    title: 'Konfirmasi kata sandi',
    description:
        'Ini area aman aplikasi. Konfirmasi kata sandi Anda sebelum melanjutkan.',
};
