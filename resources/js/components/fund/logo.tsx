import { cn } from '@/lib/utils';

export function LogoMark({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 32 32"
            aria-hidden="true"
            className={cn('size-9', className)}
        >
            <rect width="32" height="32" rx="9" fill="#176B52" />
            <path
                d="M16 5l3.2 7.8L27 16l-7.8 3.2L16 27l-3.2-7.8L5 16l7.8-3.2z"
                fill="#C5A46D"
            />
            <path
                d="M8.2 8.2h15.6v15.6H8.2z"
                fill="none"
                stroke="#C5A46D"
                strokeOpacity=".55"
            />
            <circle cx="16" cy="16" r="3" fill="#176B52" />
        </svg>
    );
}
