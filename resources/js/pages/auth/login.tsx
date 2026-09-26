import { Form, Head } from '@inertiajs/react';
import { CheckboxField } from '@/components/form/checkbox-field';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import { StatusMessage } from '@/components/form/status-message';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

const LABELS = { email: 'Alamat email', password: 'Kata sandi' };

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Masuk" />

            {status && <StatusMessage>{status}</StatusMessage>}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
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
                                    autoFocus
                                    autoComplete="email"
                                    placeholder="email@example.com"
                                />
                            )}
                        </Field>

                        <Field
                            id="password"
                            label={LABELS.password}
                            required
                            error={errors.password}
                            labelAside={
                                canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                    >
                                        Lupa kata sandi?
                                    </TextLink>
                                )
                            }
                        >
                            {(aria) => (
                                <PasswordInput
                                    {...aria}
                                    name="password"
                                    autoComplete="current-password"
                                />
                            )}
                        </Field>

                        <CheckboxField
                            id="remember"
                            name="remember"
                            label="Ingat saya di perangkat ini"
                        />

                        <Button
                            type="submit"
                            className="mt-2 w-full"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            {processing ? 'Memproses…' : 'Masuk'}
                        </Button>
                    </div>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Masuk ke akun Anda',
    description: 'Masukkan alamat email dan kata sandi Anda untuk masuk',
};
