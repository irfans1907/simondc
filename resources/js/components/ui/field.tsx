import * as React from "react"

import { cn } from "@/lib/utils"

function Field({ className, ...props }: React.ComponentProps<"div">) {
    return <div data-slot="field" className={cn("flex flex-col gap-2", className)} {...props} />
}

function FieldLabel({ className, ...props }: React.ComponentProps<"label">) {
    return (
        // eslint-disable-next-line jsx-a11y/label-has-associated-control
        <label
            data-slot="field-label"
            className={cn(
                "text-sm leading-none font-medium peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
                className,
            )}
            {...props}
        />
    )
}

export { Field, FieldLabel }
