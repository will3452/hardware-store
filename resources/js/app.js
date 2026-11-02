// Keep supplied brand files in the manifest for Blade image and favicon URLs.
window.pyesioBrandAssets = import.meta.glob('../images/brand/*.png', { eager: true, query: '?url', import: 'default' });

window.hmsProductForm = (units, unit) => ({
    units, unit, busy: false,
    get fractional() { return this.units[this.unit]?.fractional || false; },
    submit(event) {
        if (this.busy) event.preventDefault();
        else this.busy = true;
    }
});

window.hmsCart = (endpoint, mode, initial = [], initialPayments = [], methods = {cash: 'Cash', ewallet: 'E-wallet', bank: 'Bank transfer', card: 'Card'}) => ({
    query: '', results: [], lines: initial, busy: false, searching: false, searchError: '', sequence: 0,
    methods, payments: Array.isArray(initialPayments) && initialPayments.length ? initialPayments : [{method: Object.keys(methods)[0], amount: '', reference: '', confirmed: false}], scanning: false,
    init() {
        this.lines.forEach(line => { line.catalog_price_minor ??= line.basePrice; });
        this.search();
    },
    submit(event) { if (this.busy) event.preventDefault(); else this.busy = true; },
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
    async scan() {
        if (this.scanning || !this.query.trim()) return;
        this.scanning = true;
        const query = this.query.trim();
        try {
            await this.search();
            if (query !== this.query.trim() || this.searchError) return;
            const product = this.results.find(item => item.barcode === query || item.sku.toLowerCase() === query.toLowerCase()) || (this.results.length === 1 ? this.results[0] : null);
            if (product) { this.add(product); this.query = ''; this.search(); }
        } finally { this.scanning = false; }
    },
    add(product) {
        const existing = this.lines.find(line => line.product_id === product.id);
        if (existing) { existing.quantity = String(Number(existing.quantity) + 1); return; }
        if (this.lines.length >= 100) return;
        const unit = product.units.find(unit => Number(unit.factor_micro) === 1000000) || product.units[0];
        this.lines.push({product_id: product.id, name: product.name, sku: product.sku, base_unit: product.base_unit, fractional: product.fractional, units: product.units, unit_id: unit.id, quantity: '1', price: (Number(product.price_minor) / 100).toFixed(2), cost: '', discount: '0', discount_type: 'amount', basePrice: Number(product.price_minor), catalog_price_minor: Number(product.price_minor)});
    },
    changeUnit(line) {
        const unit = line.units.find(unit => unit.id === line.unit_id);
        if (mode === 'sale' && line.basePrice !== undefined) line.price = (line.basePrice * Number(unit.factor_micro) / 100000000).toFixed(2);
    },
    useCurrentPrice(line) { this.changeUnit(line); line.catalog_price_minor = line.basePrice; },
    addPayment() {
        const method = Object.keys(this.methods).find(method => !this.payments.some(payment => payment.method === method));
        if (method) this.payments.push({method, amount: '', reference: '', confirmed: false});
    },
    exactPayment(payment) {
        const others = this.payments.filter(item => item !== payment).reduce((sum, item) => sum + Math.round(Number(item.amount || 0) * 100), 0);
        payment.amount = (Math.max(0, Math.round(this.total * 100) - others) / 100).toFixed(2);
    },
    get noncashMinor() { return this.payments.filter(item => item.method !== 'cash').reduce((sum, item) => sum + Math.round(Number(item.amount || 0) * 100), 0); },
    get cashMinor() { return this.payments.filter(item => item.method === 'cash').reduce((sum, item) => sum + Math.round(Number(item.amount || 0) * 100), 0); },
    get due() { return Math.max(0, Math.round(this.total * 100) - this.noncashMinor - this.cashMinor) / 100; },
    get change() { return Math.max(0, this.cashMinor - Math.max(0, Math.round(this.total * 100) - this.noncashMinor)) / 100; },
    get noncashExcess() { return Math.max(0, this.noncashMinor - Math.round(this.total * 100)) / 100; },
    lineTotal(line) {
        const gross = Math.round(Number(line.quantity || 0) * Number(mode === 'sale' ? line.price : line.cost || 0) * 100);
        const discount = mode === 'sale' ? (line.discount_type === 'percent' ? Math.round(gross * Number(line.discount || 0) / 100) : Math.round(Number(line.discount || 0) * 100)) : 0;
        return Math.max(0, gross - discount) / 100;
    },
    get total() { return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0); },
    money(value) { return Number(value).toLocaleString('en-PH', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
});
