import { createCart } from './cart.js';
import { printRecord } from './printing.js';
window.hmsPrint = printRecord;
// Keep supplied brand files in the manifest for Blade image and favicon URLs.
window.pyesioBrandAssets = import.meta.glob('../images/brand/*.png', { eager: true, query: '?url', import: 'default' });

window.hmsProductForm = (units, unit, templates = {}, template = '', specs = {}, productName = '', catalogEndpoint = null) => ({
    catalogEndpoint, similar: [], checking: false, similarMessage: '', units, unit, templates, template, specs: specs || {}, productName, busy: false,
    init() { this.syncName(); this.$watch('specs', () => this.syncName()); },
    changeTemplate() {
        this.specs = {};
        if (this.template && !this.templates[this.template].units.includes(this.unit)) this.unit = this.templates[this.template].units[0];
        this.productName = '';
        this.syncName();
    },
    syncName() {
        if (!this.template || !this.templates[this.template]) return;
        this.productName = Object.keys(this.templates[this.template].fields).map(key => {
            let value = String(this.specs[key] || '').trim().replace(/\s+/g, ' ');
            if (/^\d+(\.\d+)?$/.test(value)) value = String(Number(value));
            return value;
        }).filter(Boolean).join(' · ');
    },
    async checkSimilar() {
        const term = String(this.specs.type || this.productName).trim().slice(0, 100);
        if (!term || !this.catalogEndpoint) { this.similarMessage = 'Enter a product name or item type first.'; return; }
        this.checking = true; this.similarMessage = ''; this.similar = [];
        try {
            const response = await fetch(this.catalogEndpoint + '?include_archived=1&q=' + encodeURIComponent(term), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Could not check existing products. Refresh and try again.');
            this.similar = await response.json();
            this.similarMessage = this.similar.length ? 'Review these existing products, including archived items, before adding another.' : 'No matching names found. The server also checks exact duplicates when saving.';
        } catch (error) { this.similarMessage = error.message; }
        finally { this.checking = false; }
    },
    get fractional() { return this.units[this.unit]?.fractional || false; },
    submit(event) {
        if (this.busy) event.preventDefault();
        else this.busy = true;
    }
});

window.hmsCart = createCart;

window.hmsCustomerPicker = (endpoint, selected = null) => ({
    selected, query: '', results: [], error: '', sequence: 0,
    async search() {
        const sequence = ++this.sequence;
        this.error = '';
        if (!this.query.trim()) { this.results = []; return; }
        try {
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(this.query), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Customer search is unavailable. Refresh and check your access.');
            const rows = await response.json();
            if (sequence === this.sequence) { this.results = rows; if (!rows.length) this.error = 'No matching active customers.'; }
        } catch (error) { if (sequence === this.sequence) this.error = error.message; }
    }
});

window.hmsRelease = (rows, methods, payments) => {
    const form = window.hmsCart('', 'sale', [], payments, methods);
    form.rows = rows;
    form.init = () => {};
    Object.defineProperty(form, 'total', {get() {
        return this.rows.reduce((sum, row) => {
            const quantity = Math.round(Number(row.quantity || 0) * 1000000);
            if (quantity <= 0) return sum;
            return sum + Math.round(row.total * (row.released + quantity) / row.ordered) - row.releasedTotal;
        }, 0) / 100;
    }});
    return form;
};

window.hmsDueDate = (today, days) => { const date = new Date(today + 'T00:00:00Z'); date.setUTCDate(date.getUTCDate() + Number(days)); return date.toISOString().slice(0, 10); };
