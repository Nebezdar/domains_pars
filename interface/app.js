document.addEventListener("DOMContentLoaded", () => {
    const domainsContainer = document.getElementById("domains-container");
    const prevPageBtn = document.getElementById("prev-page");
    const nextPageBtn = document.getElementById("next-page");
    const pageInfo = document.getElementById("page-info");

    let currentPage = 1;
    const itemsPerPage = 20;

    const loadDomains = (page) => {
        fetch(`../fetch_domains.php?page=${page}&limit=${itemsPerPage}`)
            .then(response => response.json())
            .then(data => {
                domainsContainer.innerHTML = "";
                data.domains.forEach(domain => {
                    const div = document.createElement("div");
                    div.className = "domain-item";
                    div.innerHTML = `<p><strong>${domain.domain_name}</strong></p>`;
                    domainsContainer.appendChild(div);
                });

                currentPage = data.currentPage;
                pageInfo.textContent = `Page ${currentPage} of ${data.totalPages}`;
                prevPageBtn.disabled = currentPage === 1;
                nextPageBtn.disabled = currentPage === data.totalPages;
            });
    };

    prevPageBtn.addEventListener("click", () => loadDomains(currentPage - 1));
    nextPageBtn.addEventListener("click", () => loadDomains(currentPage + 1));
    loadDomains(currentPage);
});
