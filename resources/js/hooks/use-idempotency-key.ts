import { useCallback, useRef } from 'react';
import { newUuid } from '@/lib/api';

/**
 * One key per intended payment: it survives double clicks and retries, and is
 * renewed only after the server has confirmed the request.
 */
export function useIdempotencyKey() {
    const key = useRef(newUuid());

    const renew = useCallback(() => {
        key.current = newUuid();
    }, []);

    return { current: () => key.current, renew };
}
