import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { CheckCircle } from 'lucide-react';

export default function EmailVerified() {
    const [countdown, setCountdown] = useState(10);

    useEffect(() => {
        const timer = setInterval(() => {
            setCountdown((prev) => {
                if (prev <= 1) {
                    clearInterval(timer);
                    window.close();
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => clearInterval(timer);
    }, []);

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-6">
            <Head title="Email Verified" />

            <div className="flex flex-col items-center gap-4 text-center">
                <CheckCircle className="size-16 text-green-500" />
                <h1 className="text-2xl font-bold">Email Verified!</h1>
                <p className="text-sm text-muted-foreground">
                    Your email address has been successfully verified.
                </p>
                <p className="text-sm text-muted-foreground">
                    You can now close this tab and return to the application.
                </p>
                <p className="text-xs text-muted-foreground">
                    This tab will close automatically in {countdown} seconds...
                </p>
            </div>
        </div>
    );
}
