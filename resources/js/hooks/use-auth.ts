import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { QueryClient } from '@tanstack/react-query';
import { ApiError, api } from '@/lib/api';
import type { CurrentUser } from '@/lib/types';

export const ME_KEY = ['me'];

async function fetchCurrentUser(): Promise<CurrentUser | null> {
    try {
        return (await api.get<{ data: CurrentUser }>('/api/v1/me')).data;
    } catch (error) {
        if (
            error instanceof ApiError &&
            (error.status === 401 || error.status === 403)
        ) {
            return null;
        }

        throw error;
    }
}

/**
 * Confirms that a successful sign in really produced a usable session. It will not if
 * this web address is missing from Sanctum's stateful domains, which comes from APP_URL.
 */
export async function confirmSignedIn(queryClient: QueryClient): Promise<void> {
    const user = await queryClient.fetchQuery({
        queryKey: ME_KEY,
        queryFn: fetchCurrentUser,
        staleTime: 0,
    });

    if (!user) {
        throw new Error(
            'Your password was accepted, but this web address cannot keep you signed in. An administrator needs to set APP_URL to the address you are using.',
        );
    }
}

export function useCurrentUser() {
    return useQuery<CurrentUser | null>({
        queryKey: ME_KEY,
        queryFn: fetchCurrentUser,
        retry: false,
        staleTime: 60_000,
    });
}

export function useLogout() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: () => api.post('/logout'),
        onSettled: () => {
            // Financial data must not outlive the session in memory.
            queryClient.clear();
            window.location.assign('/login');
        },
    });
}
