import { Head, router } from '@inertiajs/react';
import jsQR from 'jsqr';
import { Upload, Video, VideoOff } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import AssetScanController from '@/actions/App/Http/Controllers/AssetScanController';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import type { MenuRow } from '@/lib/menu-breadcrumbs';

const scannerPath = AssetScanController.index.url();

/**
 * Accept only this app's own scan URLs (same origin, one asset id) so a
 * random QR code can never bounce the user off-site.
 */
function resolveScanTarget(text: string): string | null {
    const pattern = /^\/assets\/\d+\/scan\/?$/;

    try {
        const url = new URL(text);

        if (url.origin === window.location.origin) {
            return pattern.test(url.pathname) ? url.pathname : null;
        }

        return null;
    } catch {
        return pattern.test(text) ? text : null;
    }
}

export default function AssetQrScanner() {
    const videoRef = useRef<HTMLVideoElement | null>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const rafRef = useRef<number | null>(null);
    const fileInputRef = useRef<HTMLInputElement | null>(null);
    const [cameraOn, setCameraOn] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function stopCamera(): void {
        if (rafRef.current !== null) {
            cancelAnimationFrame(rafRef.current);
            rafRef.current = null;
        }

        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;

        if (videoRef.current) {
            videoRef.current.srcObject = null;
        }

        setCameraOn(false);
    }

    function handleDecoded(text: string): boolean {
        const target = resolveScanTarget(text);

        if (target) {
            router.visit(target);

            return true;
        }

        setError(
            'QR terbaca, tetapi bukan QR aset aplikasi ini. Arahkan ke QR aset.',
        );

        return false;
    }

    async function startCamera(): Promise<void> {
        setError(null);

        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' },
            });

            streamRef.current = stream;

            const video = videoRef.current;

            if (!video) {
                return;
            }

            video.srcObject = stream;
            await video.play();
            setCameraOn(true);
            tick();
        } catch {
            setError(
                'Kamera tidak dapat diakses (izin ditolak atau tidak tersedia). Gunakan Unggah Gambar sebagai gantinya.',
            );
        }
    }

    function tick(): void {
        const video = videoRef.current;

        if (!video || !streamRef.current) {
            return;
        }

        if (video.readyState === video.HAVE_ENOUGH_DATA) {
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            });

            if (context && canvas.width > 0 && canvas.height > 0) {
                context.drawImage(video, 0, 0, canvas.width, canvas.height);

                const imageData = context.getImageData(
                    0,
                    0,
                    canvas.width,
                    canvas.height,
                );
                const code = jsQR(
                    imageData.data,
                    imageData.width,
                    imageData.height,
                );

                if (code?.data && handleDecoded(code.data)) {
                    stopCamera();

                    return;
                }
            }
        }

        rafRef.current = requestAnimationFrame(tick);
    }

    async function handleFile(file: File): Promise<void> {
        setError(null);

        try {
            const bitmap = await createImageBitmap(file);
            const canvas = document.createElement('canvas');
            canvas.width = bitmap.width;
            canvas.height = bitmap.height;

            const context = canvas.getContext('2d', {
                willReadFrequently: true,
            });

            if (!context) {
                setError('Gambar tidak dapat dibaca.');

                return;
            }

            context.drawImage(bitmap, 0, 0);
            bitmap.close();

            const imageData = context.getImageData(
                0,
                0,
                canvas.width,
                canvas.height,
            );
            const code = jsQR(
                imageData.data,
                imageData.width,
                imageData.height,
            );

            if (!code?.data) {
                setError('Tidak ditemukan QR di gambar tersebut.');

                return;
            }

            handleDecoded(code.data);
        } catch {
            setError('Gambar gagal dibaca.');
        }
    }

    useEffect(() => stopCamera, []);

    return (
        <>
            <Head title="Pindai QR Aset" />

            <h1 className="sr-only">Pindai QR Aset</h1>

            <div className="flex h-full flex-1 flex-col overflow-x-auto rounded-xl p-4">
                <Heading
                    variant="default"
                    title="Pindai QR Aset"
                    description="Arahkan kamera ke QR aset atau unggah foto QR untuk membuka halaman aset"
                />

                <div className="flex w-full max-w-xl flex-col gap-4">
                    <div className="overflow-hidden rounded-xl border border-sidebar-border/70 bg-black">
                        <video
                            ref={videoRef}
                            playsInline
                            muted
                            className={`h-72 w-full object-cover ${
                                cameraOn ? '' : 'hidden'
                            }`}
                        />
                        {!cameraOn && (
                            <div className="flex h-72 items-center justify-center text-sm text-muted-foreground">
                                Kamera nonaktif
                            </div>
                        )}
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {cameraOn ? (
                            <Button
                                variant="outline"
                                onClick={() => stopCamera()}
                            >
                                <VideoOff className="size-4" />
                                Matikan Kamera
                            </Button>
                        ) : (
                            <Button onClick={() => void startCamera()}>
                                <Video className="size-4" />
                                Buka Kamera
                            </Button>
                        )}

                        <Button
                            variant="outline"
                            onClick={() => fileInputRef.current?.click()}
                        >
                            <Upload className="size-4" />
                            Unggah Gambar
                        </Button>

                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                event.target.value = '';

                                if (file) {
                                    void handleFile(file);
                                }
                            }}
                        />
                    </div>

                    {error && (
                        <p className="text-sm text-destructive">{error}</p>
                    )}

                    <p className="text-sm text-muted-foreground">
                        Kamera hanya tersedia lewat HTTPS atau localhost.
                        Perangkat lain dapat memakai Unggah Gambar.
                    </p>
                </div>
            </div>
        </>
    );
}

AssetQrScanner.layout = (props: { menus?: MenuRow[] }) => {
    const parent = props.menus?.find((menu) => menu.url === '/assets');

    return {
        breadcrumbs: [
            {
                title: parent?.name ?? 'Asset Management',
                href: parent?.url ?? '/assets',
            },
            { title: 'Pindai QR', href: scannerPath },
        ],
    };
};
