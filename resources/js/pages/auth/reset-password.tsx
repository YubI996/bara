import { Form, Head } from '@inertiajs/react';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

const LABELS = {
    email: 'Alamat email',
    password: 'Kata sandi baru',
    password_confirmation: 'Ulangi kata sandi baru',
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Atur ulang kata sandi" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                noValidate
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
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
                                    value={email}
                                    readOnly
                                />
                            )}
                        </Field>

                        <Field
                            id="password"
                            label={LABELS.password}
                            required
                            hint="Minimal 12 karakter."
                            error={errors.password}
                        >
                            {(aria) => (
                                <PasswordInput
                                    {...aria}
                                    name="password"
                                    autoComplete="new-password"
                                    autoFocus
                                    passwordrules={passwordRules}
                                />
                            )}
                        </Field>

                        <Field
                            id="password_confirmation"
                            label={LABELS.password_confirmation}
                            required
                            error={errors.password_confirmation}
                        >
                            {(aria) => (
                                <PasswordInput
                                    {...aria}
                                    name="password_confirmation"
                                    autoComplete="new-password"
                                    passwordrules={passwordRules}
                                />
                            )}
                        </Field>

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            {processing
                                ? 'Menyimpan…'
                                : 'Atur ulang kata sandi'}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Atur ulang kata sandi',
    description: 'Masukkan kata sandi baru Anda',
};
