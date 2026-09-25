import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

/**
 * <select> asli: dukungan pembaca layar, keyboard, dan autofill paling konsisten
 * dibanding select kustom.
 */
export function NativeSelect({
    className,
    ...props
}: ComponentProps<'select'>) {
    return (
        <select
            className={cn(
                'flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-xs md:h-9 md:text-sm',
                'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                'disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive',
                className,
            )}
            {...props}
        />
    );
}
