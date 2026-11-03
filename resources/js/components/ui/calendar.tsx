import { ChevronDown, ChevronLeft, ChevronRight, ChevronUp } from "lucide-react"
import type * as React from "react"
import { DayPicker } from "react-day-picker"

import { cn } from "@/lib/utils"

function Calendar({
  className,
  classNames,
  showOutsideDays = true,
  ...props
}: React.ComponentProps<typeof DayPicker>) {
  return (
    <DayPicker
      showOutsideDays={showOutsideDays}
      className={cn("p-3", className)}
      classNames={{
        months: "flex flex-col sm:flex-row gap-2",
        month: "flex flex-col gap-4",
        month_caption: "relative flex items-center justify-center pt-1 z-10",
        caption_label: "inline-flex items-center gap-1 text-sm font-medium",
        dropdowns: "flex items-center gap-4",
        dropdown_root: "relative inline-flex items-center",
        dropdown: "absolute inset-0 opacity-0 bg-popover text-popover-foreground dark:bg-neutral-900 dark:text-white [&_option]:bg-popover dark:[&_option]:bg-neutral-900",
        chevron: "text-muted-foreground",
        button_previous:
          "absolute top-1.5 left-0 z-20 flex size-9 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground",
        button_next:
          "absolute top-1.5 right-0 z-20 flex size-9 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-accent hover:text-foreground",
        month_grid: "w-full border-collapse space-y-1",
        weekdays: "flex",
        weekday: "text-muted-foreground rounded-md w-9 font-normal text-[0.8rem]",
        week: "flex w-full mt-2",
        day: "relative p-0 text-center text-sm focus-within:relative focus-within:z-20 [&:has([aria-selected])]:bg-accent",
        day_button: "size-9 font-normal rounded-full hover:bg-accent hover:text-foreground",
        selected: "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground rounded-full",
        today: "bg-accent text-accent-foreground rounded-full",
        outside: "text-muted-foreground opacity-50",
        disabled: "text-muted-foreground opacity-50",
        range_middle: "bg-accent text-accent-foreground",
        range_end: "bg-primary text-primary-foreground hover:bg-primary hover:text-primary-foreground",
        ...classNames,
      }}
      components={{
        Chevron: ({ orientation }) => {
            if (orientation === "left") {
                return <ChevronLeft className="size-4" />;
            }

            if (orientation === "right") {
                return <ChevronRight className="size-4" />;
            }

            if (orientation === "up") {
                return <ChevronUp className="size-4" />;
            }

            return <ChevronDown className="size-4" />;
        },
      }}
      {...props}
    />
  )
}

export { Calendar }