document.addEventListener("DOMContentLoaded", () => {
    const domainsContainer = document.getElementById("domains-container");
    const prevPageBtn = document.getElementById("prev-page");
    const nextPageBtn = document.getElementById("next-page");
    const pageInfo = document.getElementById("page-info");

    let currentPage = 1;
    const itemsPerPage = 20;

    // Функция загрузки данных
    const loadDomains = (page) => {
        fetch(`fetch_domains.php?page=${page}&limit=${itemsPerPage}`)
            .then((response) => response.json())
            .then((data) => {
                domainsContainer.innerHTML = ""; // Очистка контейнера
                data.domains.forEach((domain) => {
                    const domainItem = document.createElement("div");
                    domainItem.classList.add("domain-item");
                    domainItem.innerHTML = `
                        <p><strong>Domain:</strong> ${domain.domain_name}</p>
                        <p><strong>Updated At:</strong> ${domain.updated_at || "Not updated"}</p>
                    `;
                    domainsContainer.appendChild(domainItem);
                });

                // Обновляем состояние кнопок пагинации
                currentPage = data.currentPage;
                pageInfo.textContent = `Page ${currentPage} of ${data.totalPages}`;
                prevPageBtn.disabled = currentPage === 1;
                nextPageBtn.disabled = currentPage === data.totalPages;
            })
            .catch((error) => console.error("Error loading domains:", error));
    };

    // Навешиваем события на кнопки пагинации
    prevPageBtn.addEventListener("click", () => {
        if (currentPage > 1) loadDomains(currentPage - 1);
    });

    nextPageBtn.addEventListener("click", () => {
        loadDomains(currentPage + 1);
    });

    // Загрузка первой страницы
    loadDomains(currentPage);
});
