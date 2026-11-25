// Number formats shared by the charts and stat tiles.
export const compact = (n) => {
    if (n == null) return '—';
    const a = Math.abs(n);
    if (a >= 10000000) return `${(n / 10000000).toFixed(a >= 100000000 ? 0 : 1)}Cr`;
    if (a >= 100000) return `${(n / 100000).toFixed(a >= 1000000 ? 0 : 1)}L`;
    if (a >= 10000) return `${Math.round(n / 1000)}K`;
    return Math.round(n).toLocaleString('en-PK');
};
export const fmt = (n, kind = 'num') => {
    if (n == null) return '—';
    if (kind === 'pkr') return `PKR ${Math.round(n).toLocaleString('en-PK')}`;
    if (kind === 'pct') return `${Number(n).toFixed(Number.isInteger(n) ? 0 : 1)}%`;
    return Math.round(n).toLocaleString('en-PK');
};
// Clean axis ticks: 0 and 3-4 round steps up to the max.
export const ticks = (max, pct = false) => {
    if (pct) return [0, 25, 50, 75, 100];
    if (!max || max <= 0) return [0, 1];
    const raw = max / 4;
    const mag = 10 ** Math.floor(Math.log10(raw));
    const step = [1, 2, 2.5, 5, 10].map((m) => m * mag).find((s) => s >= raw);
    const top = Math.ceil(max / step) * step;
    const out = [];
    for (let v = 0; v <= top + 1e-9; v += step) out.push(v);
    return out;
};
