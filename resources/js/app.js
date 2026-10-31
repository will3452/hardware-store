window.hmsCart = (endpoint, mode, initial = []) => ({
    query: '', results: [], lines: initial, busy: false, searching: false, searchError: '', sequence: 0,
    init() { this.search(); },
    async search() {
        const sequence = ++this.sequence;
        this.searching = true;
        this.searchError = '';
        try {
            const response = await fetch(endpoint + '?q=' + encodeURIComponent(this.query), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (!response.ok) throw new Error('Search unavailable. Refresh and sign in again if your session expired.');
            const products = await response.json();
            if (sequence === this.sequence) this.results = products;
        } catch (error) { if (sequence === this.sequence) this.searchError = error.message; }
        finally { if (sequence === this.sequence) this.searching = false; }
    },
    add(product) {
        const existing = this.lines.find(line => line.product_id === product.id);
        if (existing) { existing.quantity = String(Number(existing.quantity) + 1); return; }
        if (this.lines.length >= 100) return;
        const unit = product.units.find(unit => Number(unit.factor_micro) === 1000000) || product.units[0];
        this.lines.push({product_id: product.id, name: product.name, sku: product.sku, base_unit: product.base_unit, units: product.units, unit_id: unit.id, quantity: '1', price: (Number(product.price_minor) / 100).toFixed(2), cost: '', discount: '0', discount_type: 'amount', basePrice: Number(product.price_minor)});
    },
    changeUnit(line) {
        const unit = line.units.find(unit => unit.id === line.unit_id);
        if (mode === 'sale' && line.basePrice !== undefined) line.price = (line.basePrice * Number(unit.factor_micro) / 100000000).toFixed(2);
    },
    lineTotal(line) {
        const gross = Math.round(Number(line.quantity || 0) * Number(mode === 'sale' ? line.price : line.cost || 0) * 100);
        const discount = mode === 'sale' ? (line.discount_type === 'percent' ? Math.round(gross * Number(line.discount || 0) / 100) : Math.round(Number(line.discount || 0) * 100)) : 0;
        return Math.max(0, gross - discount) / 100;
    },
    get total() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0); },
    money(value) { return Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
});
