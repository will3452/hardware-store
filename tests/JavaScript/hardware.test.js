import test from 'node:test';
import assert from 'node:assert/strict';
import {createCart} from '../../resources/js/cart.js';
import {printRecord} from '../../resources/js/printing.js';
const product = {id:'p1', sku:'SKU-1', barcode:'00123', name:'Wire', base_unit:'meter', fractional:true, price_minor:1250, units:[{id:'u1',label:'meter',factor_micro:1000000},{id:'u2',label:'roll',factor_micro:100000000}]};
const response = rows => ({ok:true,json:async()=>rows});
test('rapid repeated scans are queued exactly once with leading zeroes', async () => {
    const calls=[]; let complete;
    globalThis.fetch = async url => { calls.push(url); if (calls.length===1) await new Promise(resolve=>{complete=resolve;}); return response([product]); };
    const cart=createCart('/catalog','sale'); cart.query='00123'; const first=cart.scan();
    cart.query='00123'; await cart.scan(); cart.query='00123'; await cart.scan(); complete(); await first;
    assert.equal(cart.lines[0].quantity,'3'); assert.equal(calls.length,3); assert.ok(calls.every(url=>url.includes('exact=1&q=00123')));
});
test('an exact scan is not affected by an out-of-order manual search', async () => {
    let finish; globalThis.fetch = async url => { if(url.includes('exact=1')) { await new Promise(r=>{finish=r;}); return response([product]); } return response([{...product,id:'p2',name:'Other'}]); };
    const cart=createCart('/catalog','sale'); cart.query='00123'; const scan=cart.scan(); cart.query='Other'; await cart.search(); finish(); await scan;
    assert.equal(cart.lines[0].product_id,'p1'); assert.equal(cart.query,'Other'); assert.equal(cart.results[0].id,'p2');
});
test('partial single result, unknown and ambiguous scans never add products', async () => {
    for(const rows of [[product],[],[product,{...product,id:'p2'}]]) {
        globalThis.fetch=async()=>response(rows); const cart=createCart('/catalog','sale'); cart.query=rows.length===1?'SKU':'00123'; await cart.scan(); assert.equal(cart.lines.length,0); assert.ok(cart.scanError);
    }
});
test('failed scanner lookup preserves manual entry and later success leaves error visible', async () => {
    let count=0; globalThis.fetch=async()=>{if(!count++) throw Error('Disconnected');return response([product]);};
    const cart=createCart('/catalog','sale');cart.query='00123';await cart.scan();cart.query='00123';await cart.scan();assert.equal(cart.lines.length,1);assert.match(cart.scanError,/Disconnected/);
    cart.query='Wire';await cart.search();cart.add(cart.results[0]);assert.equal(cart.lines[0].quantity,'2');
});
test('scanning a product already in pack units cannot add an unintended whole pack', async () => {
    globalThis.fetch=async()=>response([product]);const cart=createCart('/catalog','sale');cart.add(product);cart.lines[0].unit_id='u2';cart.changeUnit(cart.lines[0]);
    cart.query='00123';await cart.scan();assert.equal(cart.lines[0].quantity,'1');assert.match(cart.scanError,/pack/);assert.equal(cart.lines[0].price,'1250.00');
});
test('manual measured quantities, payments and checkout work without any hardware API', () => {
    const cart=createCart('/catalog','sale');cart.add(product);cart.lines[0].quantity='2.5';assert.equal(cart.total,31.25);cart.exactPayment(cart.payments[0]);assert.equal(cart.payments[0].amount,'31.25');assert.equal(cart.due,0);
    let prevented=false;cart.submit({preventDefault:()=>{prevented=true;}});assert.equal(prevented,false);assert.equal(cart.busy,true);
});
test('posting waits for queued scans', () => {const cart=createCart('/catalog','sale');cart.scanning=true;let prevented=false;cart.submit({preventDefault:()=>{prevented=true;}});assert.equal(prevented,true);assert.equal(cart.busy,false);});
test('print failures/cancellation/no device never mutate sales or claim physical success', () => {
    const status={textContent:''};let calls=0;assert.equal(printRecord({print(){calls++;}},status),true);assert.match(status.textContent,/does not confirm/);assert.equal(calls,1);
    assert.equal(printRecord({print(){throw new DOMException('Denied','NotAllowedError');}},status),false);assert.match(status.textContent,/unchanged/);
    assert.equal(printRecord({},status),false);assert.match(status.textContent,/browser/);
});
