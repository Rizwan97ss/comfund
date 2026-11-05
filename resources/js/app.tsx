import '../css/app.css';
import {
    MutationCache,
    QueryCache,
    QueryClient,
    QueryClientProvider,
} from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { Toaster } from '@/components/ui/sonner';
import { ME_KEY } from '@/hooks/use-auth';
import { ApiError } from '@/lib/api';
import { router } from '@/router';

/** A session that ended mid-use sends the person back to sign in, wherever it happens. */
function handleExpiredSession(error: unknown): void {
    if (error instanceof ApiError && error.status === 401) {
        queryClient.setQueryData(ME_KEY, null);
    }
}

const queryClient = new QueryClient({
    queryCache: new QueryCache({ onError: handleExpiredSession }),
    mutationCache: new MutationCache({ onError: handleExpiredSession }),
    defaultOptions: {
        queries: {
            staleTime: 15_000,
            refetchOnWindowFocus: false,
            retry: (failureCount, error) =>
                !(error instanceof ApiError && error.status < 500) &&
                failureCount < 2,
        },
    },
});

createRoot(document.getElementById('root') as HTMLElement).render(
    <StrictMode>
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
            <Toaster />
        </QueryClientProvider>
    </StrictMode>,
);
