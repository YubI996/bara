import { Form, Head } from '@inertiajs/react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { ErrorSummary } from '@/components/form/error-summary';
import { Field } from '@/components/form/field';
import Heading from '@/components/heading';
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/security';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} &
    ManageTwoFactorProps;

const LABELS = {
    current_password: 'Kata sandi saat ini',
    password: 'Kata sandi baru',
    password_confirmation: 'Ulangi kata sandi baru',
};

export default function Security(props: Props) {
    return (
        <>
            <Head title="Pengaturan keamanan" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Ganti kata sandi"
                    description="Gunakan kata sandi panjang dan acak (minimal 12 karakter) agar akun tetap aman"
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{ preserveScroll: true }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    className="space-y-6"
                    noValidate
                >
                    {({ errors, processing }) => (
                        <>
                            <ErrorSummary errors={errors} labels={LABELS} />

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
                                        passwordrules={props.passwordRules}
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
                                        passwordrules={props.passwordRules}
                                    />
                                )}
                            </Field>

                            <Button
                                disabled={processing}
                                data-test="update-password-button"
                            >
                                {processing ? 'Menyimpan…' : 'Simpan'}
                            </Button>
                        </>
                    )}
                </Form>
            </div>

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />
        </>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            title: 'Pengaturan keamanan',
            href: edit(),
        },
    ],
};
