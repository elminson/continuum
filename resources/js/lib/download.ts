const SOURCES = 'Continuum · NASA FIRMS · MODIS C6.1 + VIIRS 375 m';

type PanelImage = {
    title: string;
    subtitle?: string;
    filename: string;
};

/**
 * Saves every canvas inside `root` (ECharts or MapLibre) as one PNG, framed
 * with a title and a source line so the image documents itself when shared.
 * Returns false when there is nothing to draw or the canvas is tainted.
 */
export function downloadPanelPng(
    root: HTMLElement,
    image: PanelImage,
): boolean {
    const canvases = Array.from(root.querySelectorAll('canvas')).filter(
        (canvas) => canvas.width > 0 && canvas.height > 0,
    );

    if (canvases.length === 0) {
        return false;
    }

    const box = root.getBoundingClientRect();
    const scale = window.devicePixelRatio || 1;
    const pad = 24;
    const header = image.subtitle ? 56 : 40;
    const footer = 32;
    const dark = document.documentElement.classList.contains('dark');

    const output = document.createElement('canvas');
    output.width = Math.round((box.width + pad * 2) * scale);
    output.height = Math.round(
        (box.height + pad * 2 + header + footer) * scale,
    );

    const context = output.getContext('2d');

    if (!context) {
        return false;
    }

    context.scale(scale, scale);
    context.fillStyle = dark ? '#0a0a0a' : '#ffffff';
    context.fillRect(0, 0, output.width, output.height);

    const font =
        'ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif';
    context.fillStyle = dark ? '#fafafa' : '#0a0a0a';
    context.font = `600 16px ${font}`;
    context.fillText(image.title, pad, pad + 16);

    if (image.subtitle) {
        context.fillStyle = dark ? '#a1a1aa' : '#52525b';
        context.font = `12px ${font}`;
        context.fillText(image.subtitle, pad, pad + 36);
    }

    for (const canvas of canvases) {
        const rect = canvas.getBoundingClientRect();
        context.drawImage(
            canvas,
            pad + rect.left - box.left,
            pad + header + rect.top - box.top,
            rect.width,
            rect.height,
        );
    }

    context.fillStyle = dark ? '#a1a1aa' : '#52525b';
    context.font = `11px ${font}`;
    context.fillText(
        `${SOURCES} · ${window.location.host} · ${new Date().toISOString().slice(0, 10)}`,
        pad,
        pad + header + box.height + 22,
    );

    let url: string;

    try {
        url = output.toDataURL('image/png');
    } catch {
        return false;
    }

    const link = document.createElement('a');
    link.href = url;
    link.download = `${image.filename}.png`;
    link.click();

    return true;
}
