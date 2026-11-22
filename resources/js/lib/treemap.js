/**
 * Treemap "squarified" (Bruls et al.) en porcentajes del contenedor.
 * items: [{valor, ...}] ordenados desc. Devuelve [{...item, left, top, width, height}] en %.
 * ancho/alto: proporción del contenedor (para que los bloques salgan cuadrados).
 */
export function squarify(items, ancho = 1.6, alto = 1) {
    const total = items.reduce((s, i) => s + i.valor, 0);
    if (!total) return [];
    const area = ancho * alto;
    const datos = items.map((i) => ({ ...i, _a: (i.valor / total) * area }));
    const out = [];
    let x = 0, y = 0, w = ancho, h = alto;
    let fila = [];

    const peor = (fila, lado) => {
        const s = fila.reduce((a, b) => a + b._a, 0);
        const max = Math.max(...fila.map((f) => f._a));
        const min = Math.min(...fila.map((f) => f._a));
        return Math.max((lado * lado * max) / (s * s), (s * s) / (lado * lado * min));
    };
    const colocar = (fila) => {
        const s = fila.reduce((a, b) => a + b._a, 0);
        if (w >= h) {
            const ancho_c = s / h;
            let yy = y;
            for (const f of fila) {
                const hh = f._a / ancho_c;
                out.push({ ...f, left: (x / ancho) * 100, top: (yy / alto) * 100, width: (ancho_c / ancho) * 100, height: (hh / alto) * 100 });
                yy += hh;
            }
            x += ancho_c;
            w -= ancho_c;
        } else {
            const alto_f = s / w;
            let xx = x;
            for (const f of fila) {
                const ww = f._a / alto_f;
                out.push({ ...f, left: (xx / ancho) * 100, top: (y / alto) * 100, width: (ww / ancho) * 100, height: (alto_f / alto) * 100 });
                xx += ww;
            }
            y += alto_f;
            h -= alto_f;
        }
    };

    for (const d of datos) {
        const lado = Math.min(w, h);
        if (!fila.length || peor([...fila, d], lado) <= peor(fila, lado)) {
            fila.push(d);
        } else {
            colocar(fila);
            fila = [d];
        }
    }
    if (fila.length) colocar(fila);
    return out;
}
