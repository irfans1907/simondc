<?php

namespace App\Concerns;

trait DescribesActivityChanges
{
    /**
     * Build the "field from old to new" fragments for an update activity
     * description, one per changed attribute except the timestamps.
     *
     * @param  array<string, callable(mixed): string>  $resolvers
     * @param  array<string, string>  $descriptors  attribute => human label
     * @return list<string>
     */
    protected function describeChangedAttributes(array $resolvers = [], array $descriptors = []): array
    {
        $changes = $this->getChanges();

        unset($changes['updated_at']);

        ksort($changes);

        $parts = [];

        foreach ($changes as $attribute => $newValue) {
            $oldValue = $this->oldAttributes[$attribute] ?? null;

            $label = $descriptors[$attribute] ?? $attribute;

            $parts[] = sprintf(
                '%s from %s to %s',
                $label,
                $this->formatActivityValue($attribute, $oldValue, $resolvers),
                $this->formatActivityValue($attribute, $newValue, $resolvers),
            );
        }

        return $parts;
    }

    /**
     * Render a single attribute value for an activity log description:
     * null and empty strings become "none", booleans become Yes/No, and
     * foreign keys are resolved to their related name when a resolver
     * is registered for the attribute.
     *
     * @param  array<string, callable(mixed): string>  $resolvers
     */
    protected function formatActivityValue(string $attribute, mixed $value, array $resolvers = []): string
    {
        if ($value === null || $value === '') {
            return 'none';
        }

        if ($this->hasCast($attribute, ['bool', 'boolean'])) {
            return $value ? 'Yes' : 'No';
        }

        if (isset($resolvers[$attribute])) {
            return $resolvers[$attribute]($value);
        }

        return (string) $value;
    }
}
