import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useMemo, useState } from 'react';
import { ErrorSummary } from '@/components/form/error-summary';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { store } from '@/routes/two-factor/login';

const LABELS = { code: 'Kode autentikasi', recovery_code: 'Kode pemulihan' };

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Kode pemulihan',
                description:
                    'Konfirmasi akses ke akun Anda dengan memasukkan salah satu kode pemulihan darurat.',
                toggleText: 'masuk memakai kode autentikasi',
            };
        }

        return {
            title: 'Kode autentikasi',
            description:
                'Masukkan kode autentikasi dari aplikasi autentikator Anda.',
            toggleText: 'masuk memakai kode pemulihan',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Autentikasi dua faktor" />

            <div className="space-y-6">
                <Form
                    {...store.form()}
                    className="space-y-4"
                    noValidate
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            <ErrorSummary errors={errors} labels={LABELS} />

                            {showRecoveryInput ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="recovery_code">
                                        {LABELS.recovery_code}
                                    </Label>
                                    <Input
                                        id="recovery_code"
                                        name="recovery_code"
                                        autoComplete="one-time-code"
                                        type="text"
                                        autoFocus={showRecoveryInput}
                                        aria-describedby={
                                            errors.recovery_code
                                                ? 'recovery_code-error'
                                                : undefined
                                        }
                                        aria-invalid={
                                            errors.recovery_code
                                                ? true
                                                : undefined
                                        }
                                    />
                                    <InputError
                                        id="recovery_code-error"
                                        message={errors.recovery_code}
                                    />
                                </div>
                            ) : (
                                <div className="flex flex-col items-center justify-center space-y-3 text-center">
                                    <div className="flex w-full items-center justify-center">
                                        <InputOTP
                                            id="code"
                                            name="code"
                                            maxLength={OTP_MAX_LENGTH}
                                            value={code}
                                            onChange={(value) => setCode(value)}
                                            disabled={processing}
                                            pattern={REGEXP_ONLY_DIGITS}
                                            aria-label="Kode autentikasi 6 digit"
                                            aria-describedby={
                                                errors.code
                                                    ? 'code-error'
                                                    : undefined
                                            }
                                            aria-invalid={
                                                errors.code ? true : undefined
                                            }
                                            autoComplete="one-time-code"
                                            autoFocus
                                        >
                                            <InputOTPGroup>
                                                {Array.from(
                                                    { length: OTP_MAX_LENGTH },
                                                    (_, index) => (
                                                        <InputOTPSlot
                                                            key={index}
                                                            index={index}
                                                        />
                                                    ),
                                                )}
                                            </InputOTPGroup>
                                        </InputOTP>
                                    </div>
                                    <InputError
                                        id="code-error"
                                        message={errors.code}
                                    />
                                </div>
                            )}

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing ? 'Memeriksa…' : 'Lanjut'}
                            </Button>

                            <div className="text-center text-sm text-muted-foreground">
                                <span>atau Anda dapat </span>
                                <button
                                    type="button"
                                    className="cursor-pointer text-foreground underline underline-offset-4"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                >
                                    {authConfigContent.toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
