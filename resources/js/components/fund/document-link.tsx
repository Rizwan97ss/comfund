import { useQuery } from '@tanstack/react-query';
import { Download, FileText, Loader2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { api } from '@/lib/api';
import { errorMessage } from '@/lib/forms';
import type { Document } from '@/lib/types';

function saveBlob(blob: Blob, name: string): void {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = name;
    link.click();
    URL.revokeObjectURL(url);
}

/**
 * Receipts are private, so they are fetched through the authorised, audited endpoint
 * and shown from memory. There is no URL that could be shared or cached.
 */
export function DocumentDownload({ document: file }: { document: Document }) {
    const [isLoading, setIsLoading] = useState(false);

    async function download() {
        setIsLoading(true);

        try {
            saveBlob(
                await api.blob(`/api/v1/documents/${file.uuid}`),
                file.name,
            );
        } catch (error) {
            toast.error(errorMessage(error));
        } finally {
            setIsLoading(false);
        }
    }

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={download}
            disabled={isLoading}
        >
            {isLoading ? <Loader2 className="animate-spin" /> : <Download />}
            {file.name}
        </Button>
    );
}

export function DocumentPreview({ document: file }: { document: Document }) {
    const isImage = file.mime_type.startsWith('image/');
    const [objectUrl, setObjectUrl] = useState<string | null>(null);

    const {
        data: blob,
        isLoading,
        isError,
    } = useQuery({
        queryKey: ['document', file.uuid],
        queryFn: () => api.blob(`/api/v1/documents/${file.uuid}`),
        enabled: isImage,
        staleTime: Infinity,
        gcTime: 0,
    });

    useEffect(() => {
        if (!blob) {
            return;
        }

        const url = URL.createObjectURL(blob);
        setObjectUrl(url);

        return () => URL.revokeObjectURL(url);
    }, [blob]);

    if (!isImage) {
        return (
            <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-8 text-center">
                <FileText className="size-8 text-emerald-deep" />
                <p className="text-sm text-muted-foreground">
                    PDF documents open after downloading.
                </p>
                <DocumentDownload document={file} />
            </div>
        );
    }

    return (
        <div className="grid gap-3">
            <div className="flex min-h-48 items-center justify-center overflow-hidden rounded-xl border bg-muted/40">
                {isLoading && (
                    <Loader2 className="size-6 animate-spin text-muted-foreground" />
                )}
                {isError && (
                    <p className="p-6 text-sm text-destructive">
                        The receipt could not be loaded.
                    </p>
                )}
                {objectUrl && (
                    <img
                        src={objectUrl}
                        alt={`Receipt: ${file.name}`}
                        className="max-h-[32rem] w-full object-contain"
                    />
                )}
            </div>
            <div>
                <DocumentDownload document={file} />
            </div>
        </div>
    );
}
