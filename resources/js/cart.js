export const createCart = (endpoint, mode, initial = [], initialPayments = [], methods = {cash: 'Cash', ewallet: 'E-wallet', bank: 'Bank transfer', card: 'Card'}) => ({
    settlement: 'paid', dueDate: '', query: '', results: [], lines: initial, busy: false, searching: false, searchError: '', sequence: 0,
    methods, payments: Array.isArray(initialPayments) && initialPayments.length ? initialPayments : [{method: Object.keys(methods)[0], amount: '', reference: '', confirmed: false}], scanning: false, scanQueue: [], scanMessage: '', scanError: '',
    init() {
        this.lines.forEach(line => { line.catalog_price_minor ??= line.basePrice; });
        this.search();
    },
    submit(event) { if (this.busy || this.scanning) event.preventDefault(); else this.busy = true; },
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
        const code = this.query.trim();
        if (!code) return;
        // Capture each Enter immediately; lookup never reads a later scan's query.
        this.query = '';
        this.scanQueue.push(code);
        if (this.scanning) return;
        this.scanning = true;
        try {
            while (this.scanQueue.length) {
                const captured = this.scanQueue.shift();
                try {
                    const response = await fetch(endpoint + '?exact=1&q=' + encodeURIComponent(captured), {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
                    if (!response.ok) throw new Error('Lookup unavailable. Check your connection and sign-in, then rescan.');
                    const products = await response.json();
                    const matches = products.filter(item => item.barcode === captured || item.sku.toLowerCase() === captured.toLowerCase());
                    if (matches.length !== 1) throw new Error(matches.length ? 'This code matches more than one product. Select the correct item manually.' : 'Unknown code. Search by product name or check its barcode.');
                    const product = matches[0];
                    this.add(product, true);
                    this.scanMessage = 'Added ' + product.name + ' · 1 ' + product.base_unit + '. Review measured quantities before posting.';
                } catch (error) {
                    // Keep errors visible even if a later queued scan succeeds.
                    this.scanError = captured + ': ' + error.message;
                }
            }
        } finally { this.scanning = false; }
    },
    add(product, fromScan = false) {
        const unit = product.units.find(unit => Number(unit.factor_micro) === 1000000) || product.units[0];
        const existing = this.lines.find(line => line.product_id === product.id);
        if (existing) {
            if (fromScan && existing.unit_id !== unit.id) throw new Error('This item uses a pack in the cart. Adjust its quantity manually; the product barcode represents one base unit.');
            existing.quantity = String(Math.round((Number(existing.quantity) + 1) * 1000000) / 1000000);
            return;
        }
        if (this.lines.length >= 100) { this.scanError = 'The cart already contains 100 different products.'; if (fromScan) throw new Error(this.scanError); return; }
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
