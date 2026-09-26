export default function Heading({
    title,
    description,
    variant = 'default',
    as: Tag = 'h2',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    as?: 'h1' | 'h2';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-0.5'}>
            <Tag
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-medium'
                        : 'text-xl font-semibold tracking-tight'
                }
            >
                {title}
            </Tag>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
