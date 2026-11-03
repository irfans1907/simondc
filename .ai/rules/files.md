---
paths:
  - 'resources/js/pages/files/**'
---

# Files

## Echo event names need a leading dot
Laravel events broadcast via broadcastAs() use names like 'files.changed'. In Echo listeners the event name MUST be prefixed with a dot ('.files.changed'); otherwise Echo's event formatter converts dots to backslashes and the listener never fires. The page reloads only ['files'] (Inertia v3 reload options have no preserveScroll).

## PDF thumbnail blur: measure parent, not canvas
In PdfThumbnail, canvas.getBoundingClientRect() returns 300px before canvas.width/height are set (canvas default intrinsic size), causing wrong scale and blurry output. Always measure canvas.parentElement.getBoundingClientRect() for the intended display width, then render at displayWidth * devicePixelRatio * 2 and set style width/height explicitly. pdfjs-dist v6 API: getDocument({ url }) and page.render({ canvas, viewport }) — no canvasContext key.
