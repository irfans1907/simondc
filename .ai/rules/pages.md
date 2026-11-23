---
paths:
  - 'resources/js/pages/**'
---

# Pages

## Empty table cells use the short hyphen via displayValue()
Never render an em dash (—) as an empty-cell placeholder. Use the short hyphen '-' or displayValue(value) from '@/lib/format', which also normalizes stored '-', '–', '—', and blank strings. ESLint no-restricted-syntax in eslint.config.js rejects em dash literals and lone em dash JSX text in resources/js/pages; this lint rule is the enforcement point.
