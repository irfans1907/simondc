import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export function AppearanceSwitch() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    const isDark = mounted ? resolvedAppearance === 'dark' : false;

    return (
        <button
            type="button"
            role="switch"
            aria-checked={isDark}
            aria-label="Toggle dark mode"
            onClick={(e) => {
                e.stopPropagation();
                updateAppearance(isDark ? 'light' : 'dark');
            }}
            className="relative inline-flex h-5 w-11 shrink-0 cursor-pointer items-center rounded-full border-2 border-sidebar-border bg-neutral-100 transition-colors focus-visible:ring-0 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 dark:bg-neutral-800"
        >
            <span
                className={cn(
                    'pointer-events-none flex size-7 items-center justify-center rounded-full border border-sidebar-border bg-white text-neutral-500 shadow-xs transition-transform dark:bg-neutral-700 dark:text-neutral-100',
                    isDark ? 'translate-x-3' : 'translate-x-0',
                )}
            >
                {isDark ? (
                    <Moon className="size-4" />
                ) : (
                    <Sun className="size-4" />
                )}
            </span>
        </button>
    );
}
