import { Check, ChevronDown } from 'lucide-react';
import { useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

export default function ComboboxSelect({
    name,
    id,
    options,
    defaultValue = '',
    value: valueProp,
    onValueChange,
    placeholder = 'Select an option...',
    searchPlaceholder = 'Search...',
    emptyMessage = 'No options.',
    allowCreate = false,
    required = false,
}: {
    name: string;
    id: string;
    options: string[];
    defaultValue?: string;
    value?: string;
    onValueChange?: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyMessage?: string;
    allowCreate?: boolean;
    required?: boolean;
}) {
    const [internalValue, setInternalValue] = useState(defaultValue);
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);

    const isControlled = valueProp !== undefined;
    const value = isControlled ? valueProp : internalValue;

    const setValue = (next: string) => {
        if (!isControlled) {
            setInternalValue(next);
        }

        onValueChange?.(next);
    };

    const filtered = options.filter((option) =>
        option.toLowerCase().includes(query.toLowerCase()),
    );

    const displayValue = value || defaultValue || '';

    return (
        <>
            <input type="hidden" name={name} value={displayValue} required={required} />
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <button
                        type="button"
                        id={id}
                        onClick={() => setOpen(true)}
                        className="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span className={displayValue ? '' : 'text-muted-foreground'}>
                            {displayValue || placeholder}
                        </span>
                        <ChevronDown className="size-4 opacity-50" />
                    </button>
                </PopoverTrigger>
                <PopoverContent className="pointer-events-auto w-[--radix-popover-trigger-width] p-0" align="start">
                    <div className="border-b p-2">
                        <Input
                            ref={inputRef}
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="h-8"
                            autoFocus
                        />
                    </div>
                    <div
                        className="max-h-60 overflow-auto overscroll-contain p-1"
                        onWheel={(e) => e.stopPropagation()}
                        onTouchMove={(e) => e.stopPropagation()}
                    >
                        {allowCreate &&
                            query &&
                            !options.some(
                                (option) =>
                                    option.toLowerCase() === query.toLowerCase(),
                            ) && (
                                <button
                                    type="button"
                                    className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                                    onClick={() => {
                                        setValue(query);
                                        setQuery('');
                                        setOpen(false);
                                    }}
                                >
                                    <Check className="size-4 opacity-0" />
                                    <span>Create &quot;{query}&quot;</span>
                                </button>
                            )}
                        {filtered.map((option) => (
                            <button
                                type="button"
                                key={option}
                                className="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-sm hover:bg-accent hover:text-accent-foreground"
                                onClick={() => {
                                    setValue(option);
                                    setQuery('');
                                    setOpen(false);
                                }}
                            >
                                <Check
                                    className={`size-4 ${value === option ? 'opacity-100' : 'opacity-0'}`}
                                />
                                {option}
                            </button>
                        ))}
                        {filtered.length === 0 && !query && (
                            <p className="px-2 py-1.5 text-sm text-muted-foreground">
                                {emptyMessage}
                            </p>
                        )}
                    </div>
                </PopoverContent>
            </Popover>
        </>
    );
}
