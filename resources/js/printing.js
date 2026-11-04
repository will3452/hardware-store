export function printRecord(target = window, status = document.getElementById('print-status')) {
    try {
        target.print();
        if (status) status.textContent = 'Print dialog closed. Check your printer or saved PDF; this does not confirm a physical print. Your sale is unchanged.';
        return true;
    } catch {
        if (status) status.textContent = 'Printing is unavailable. Use your browser’s Print menu or save a PDF and retry. Your sale is unchanged.';
        return false;
    }
}
