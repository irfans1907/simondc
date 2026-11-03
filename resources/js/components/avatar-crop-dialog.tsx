import { useCallback, useState } from 'react';
import Cropper from 'react-easy-crop';
import type { Area } from 'react-easy-crop';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';

function getCroppedImg(imageSrc: string, pixelCrop: Area): Promise<Blob | null> {
    return new Promise((resolve) => {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = pixelCrop.width;
            canvas.height = pixelCrop.height;
            const ctx = canvas.getContext('2d');
            if (!ctx) {
                resolve(null);

                return;
            }

            ctx.beginPath();
            ctx.arc(
                pixelCrop.width / 2,
                pixelCrop.height / 2,
                Math.min(pixelCrop.width, pixelCrop.height) / 2,
                0,
                2 * Math.PI,
            );
            ctx.clip();

            ctx.drawImage(
                image,
                pixelCrop.x,
                pixelCrop.y,
                pixelCrop.width,
                pixelCrop.height,
                0,
                0,
                pixelCrop.width,
                pixelCrop.height,
            );

            canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.9);
        };
        image.src = imageSrc;
    });
}

export default function AvatarCropDialog({
    open,
    onOpenChange,
    imageSrc,
    onUpload,
    processing,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    imageSrc: string | null;
    onUpload: (blob: Blob) => void;
    processing: boolean;
}) {
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<Area | null>(null);

    const onCropComplete = useCallback((_: Area, croppedPixels: Area) => {
        setCroppedAreaPixels(croppedPixels);
    }, []);

    const handleUpload = async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        const blob = await getCroppedImg(imageSrc, croppedAreaPixels);
        if (blob) {
            onUpload(blob);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogTitle>Crop avatar</DialogTitle>
                <DialogDescription>
                    Adjust your avatar position and zoom level.
                </DialogDescription>

                <div className="relative h-80 w-full overflow-hidden rounded-lg bg-neutral-900">
                    {imageSrc && (
                        <Cropper
                            image={imageSrc}
                            crop={crop}
                            zoom={zoom}
                            aspect={1}
                            cropShape="round"
                            showGrid={false}
                            onCropChange={setCrop}
                            onZoomChange={setZoom}
                            onCropComplete={onCropComplete}
                        />
                    )}
                </div>

                <div className="space-y-2">
                    <label
                        htmlFor="zoom"
                        className="text-sm font-medium text-muted-foreground"
                    >
                        Zoom
                    </label>
                    <input
                        id="zoom"
                        type="range"
                        min={1}
                        max={3}
                        step={0.01}
                        value={zoom}
                        onChange={(e) => setZoom(Number(e.target.value))}
                        className="w-full accent-primary"
                    />
                </div>

                <DialogFooter className="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={handleUpload}
                    >
                        {processing ? 'Uploading...' : 'Upload'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
