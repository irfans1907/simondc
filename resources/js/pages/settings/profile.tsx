import { Form, Head, router, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import AvatarCropDialog from '@/components/avatar-crop-dialog';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/hooks/use-initials';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';
import { Camera, Trash2 } from 'lucide-react';

type PageProps = {
    auth: Auth;
};

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const avatarInputRef = useRef<HTMLInputElement>(null);
    const getInitials = useInitials();
    const [uploading, setUploading] = useState(false);
    const [cropDialogOpen, setCropDialogOpen] = useState(false);
    const [imageSrc, setImageSrc] = useState<string | null>(null);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = () => {
            setImageSrc(reader.result as string);
            setCropDialogOpen(true);
        };
        reader.readAsDataURL(file);

        if (avatarInputRef.current) {
            avatarInputRef.current.value = '';
        }
    };

    const handleCropUpload = (blob: Blob) => {
        const formData = new FormData();
        formData.append('avatar', blob, 'avatar.jpg');

        setUploading(true);

        router.post(ProfileController.updateAvatar.url(), formData, {
            preserveScroll: true,
            onFinish: () => {
                setUploading(false);
                setCropDialogOpen(false);
                setImageSrc(null);
            },
        });
    };

    const handleAvatarRemove = () => {
        router.delete(ProfileController.destroyAvatar.url(), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Profile"
                    description="Update your name and email address"
                />

                <div className="flex items-center gap-4">
                    <Avatar className="h-20 w-20 rounded-full">
                        <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />
                        <AvatarFallback className="rounded-lg bg-neutral-200 text-lg text-black dark:bg-neutral-700 dark:text-white">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="flex gap-2">
                        <input
                            ref={avatarInputRef}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={handleFileChange}
                        />
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            disabled={uploading}
                            onClick={() => avatarInputRef.current?.click()}
                        >
                            <Camera className="size-4" />
                            {uploading ? 'Uploading...' : 'Change avatar'}
                        </Button>
                        {auth.user.avatar && (
                            <Button
                                type="button"
                                variant="destructive"
                                size="sm"
                                onClick={handleAvatarRemove}
                            >
                                <Trash2 className="size-4" />
                                Remove
                            </Button>
                        )}
                    </div>
                </div>

                <AvatarCropDialog
                    open={cropDialogOpen}
                    onOpenChange={setCropDialogOpen}
                    imageSrc={imageSrc}
                    onUpload={handleCropUpload}
                    processing={uploading}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder="Full name"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email address</Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder="Email address"
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            Your email address is unverified.{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                Click here to re-send the
                                                verification email.
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-green-600">
                                                A new verification link has been
                                                sent to your email address.
                                            </div>
                                        )}
                                    </div>
                                )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    Save
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <DeleteUser />
        </>
    );
}

Profile.layout = {
    breadcrumbs: [
        {
            title: 'Profile settings',
            href: edit(),
        },
    ],
};
