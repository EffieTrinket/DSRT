/**
 * DSRT - Reusable Table Pagination
 * Usage: paginateTable('tbodyId', 'paginationDivId', rowsPerPage)
 */
function paginateTable(tbodyId, paginationId, rowsPerPage) {
    const tbody = document.getElementById(tbodyId);
    const paginationContainer = document.getElementById(paginationId);
    if (!tbody || !paginationContainer) return;

    const rows = Array.from(tbody.getElementsByTagName('tr'));

    // Don't paginate if it's only an empty state row
    if (rows.length <= 1 && rows[0] && rows[0].querySelector('.table-empty, .empty-state')) return;
    if (rows.length === 0) return;

    const totalRows = rows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    let currentPage = 1;

    function showPage(page) {
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        currentPage = page;

        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;

        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });

        renderPaginationControls();
    }

    function renderPaginationControls() {
        paginationContainer.innerHTML = '';
        if (totalPages <= 1) return;

        // Prev Button
        const prevBtn = document.createElement('button');
        prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
        prevBtn.disabled = currentPage === 1;
        prevBtn.onclick = () => showPage(currentPage - 1);
        paginationContainer.appendChild(prevBtn);

        // Page number buttons (show up to 5)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        if (endPage - startPage < 4) startPage = Math.max(1, endPage - 4);

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.textContent = i;
            btn.className = i === currentPage ? 'active' : '';
            btn.onclick = ((p) => () => showPage(p))(i);
            paginationContainer.appendChild(btn);
        }

        // Info
        const info = document.createElement('span');
        info.className = 'pagination-info';
        const from = (currentPage - 1) * rowsPerPage + 1;
        const to = Math.min(currentPage * rowsPerPage, totalRows);
        info.textContent = `${from}–${to} of ${totalRows}`;
        paginationContainer.appendChild(info);

        // Next Button
        const nextBtn = document.createElement('button');
        nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        nextBtn.disabled = currentPage === totalPages;
        nextBtn.onclick = () => showPage(currentPage + 1);
        paginationContainer.appendChild(nextBtn);
    }

    showPage(1);
}
