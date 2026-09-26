import type { HTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    return message ? (
        <p
            {...props}
            className={cn(
                'text-sm font-medium text-red-700 dark:text-red-300',
                className,
            )}
        >
            {message}
        </p>
    ) : null;
}
